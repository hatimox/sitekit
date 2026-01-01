<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SshKey extends Model
{
    use HasFactory;
    use HasUuids;
    use LogsActivity;

    protected $fillable = [
        'team_id',
        'user_id',
        'name',
        'public_key',
        'fingerprint',
    ];

    protected static function booted(): void
    {
        static::creating(function (SshKey $sshKey) {
            if (empty($sshKey->fingerprint)) {
                $sshKey->fingerprint = self::calculateFingerprint($sshKey->public_key);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_ssh_key')
            ->using(ServerSshKey::class)
            ->withPivot('status')
            ->withTimestamps();
    }

    public static function calculateFingerprint(string $publicKey): ?string
    {
        $key = trim($publicKey);

        if (preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp\d+)\s+([A-Za-z0-9+\/=]+)/', $key, $matches)) {
            $keyData = base64_decode($matches[2]);
            if ($keyData !== false) {
                return 'SHA256:' . base64_encode(hash('sha256', $keyData, true));
            }
        }

        return null;
    }

    public static function isValidPublicKey(string $publicKey): bool
    {
        $key = trim($publicKey);

        return preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp\d+)\s+[A-Za-z0-9+\/=]+/', $key) === 1;
    }

    /**
     * Deploy this SSH key to a specific server.
     */
    public function dispatchAddToServer(Server $server, string $username = 'sitekit'): AgentJob
    {
        // Update pivot status
        $this->servers()->syncWithoutDetaching([
            $server->id => ['status' => 'pending'],
        ]);

        return AgentJob::create([
            'server_id' => $server->id,
            'team_id' => $this->team_id,
            'type' => 'ssh_key_add',
            'payload' => [
                'key_id' => $this->id,
                'public_key' => $this->public_key,
                'username' => $username,
            ],
        ]);
    }

    /**
     * Remove this SSH key from a specific server.
     */
    public function dispatchRemoveFromServer(Server $server, string $username = 'sitekit'): AgentJob
    {
        return AgentJob::create([
            'server_id' => $server->id,
            'team_id' => $this->team_id,
            'type' => 'ssh_key_remove',
            'payload' => [
                'key_id' => $this->id,
                'public_key' => $this->public_key,
                'username' => $username,
            ],
        ]);
    }

    /**
     * Deploy this SSH key to all team servers.
     */
    public function dispatchToAllServers(string $username = 'sitekit'): void
    {
        $servers = Server::where('team_id', $this->team_id)
            ->where('status', Server::STATUS_ACTIVE)
            ->get();

        foreach ($servers as $server) {
            $this->dispatchAddToServer($server, $username);
        }
    }

    /**
     * Sync all team SSH keys to a server.
     */
    public static function syncToServer(Server $server, string $username = 'sitekit'): AgentJob
    {
        $keys = self::where('team_id', $server->team_id)->get();

        // Format keys as array of objects for Go agent
        $keysArray = $keys->map(fn ($key) => [
            'key_id' => $key->id,
            'public_key' => $key->public_key,
        ])->toArray();

        // Also keep key_ids for callback handling
        $keyIds = $keys->pluck('id')->toArray();

        return AgentJob::create([
            'server_id' => $server->id,
            'team_id' => $server->team_id,
            'type' => 'ssh_key_sync',
            'payload' => [
                'keys' => $keysArray,
                'key_ids' => $keyIds, // For callback handling
                'username' => $username,
            ],
        ]);
    }

    protected function getLoggableAttributes(): array
    {
        return ['name'];
    }
}
