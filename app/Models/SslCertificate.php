<?php

namespace App\Models;

use App\Models\Concerns\HasErrorTracking;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificate extends Model
{
    use HasFactory, HasUuids, LogsActivity, HasErrorTracking;

    public const TYPE_LETSENCRYPT = 'letsencrypt';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUING = 'issuing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RENEWING = 'renewing';

    protected $fillable = [
        'web_app_id',
        'type',
        'domain',
        'status',
        'issued_at',
        'expires_at',
        'certificate',
        'private_key',
        'chain',
        'error_message',
        'last_renewal_attempt',
        'renewal_count',
        'last_error',
        'last_error_at',
        'suggested_action',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_renewal_attempt' => 'datetime',
            'certificate' => 'encrypted',
            'private_key' => 'encrypted',
            'chain' => 'encrypted',
            'last_error_at' => 'datetime',
        ];
    }

    public function webApp(): BelongsTo
    {
        return $this->belongsTo(WebApp::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_at && $this->expires_at->isBefore(now()->addDays(30));
    }

    public function needsRenewal(): bool
    {
        if ($this->type !== self::TYPE_LETSENCRYPT) {
            return false;
        }

        return $this->isExpiringSoon() || $this->isExpired();
    }

    public function dispatchIssue(): AgentJob
    {
        $this->update(['status' => self::STATUS_ISSUING]);

        // Build domains array - primary domain first, then any aliases
        $domains = [$this->domain];
        if ($this->webApp->aliases) {
            $domains = array_merge($domains, $this->webApp->aliases);
        }

        return AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'ssl_issue',
            'payload' => [
                'certificate_id' => $this->id,
                'domains' => $domains,
                'email' => $this->webApp->team->owner->email ?? 'admin@' . $this->domain,
                'webroot' => $this->webApp->document_root,
            ],
        ]);
    }

    public function dispatchRenew(bool $force = false): AgentJob
    {
        $this->update([
            'status' => self::STATUS_RENEWING,
            'last_renewal_attempt' => now(),
        ]);

        return AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'ssl_renew',
            'payload' => [
                'certificate_id' => $this->id,
                'domain' => $this->domain,
                'force' => $force,
            ],
        ]);
    }

    public function dispatchInstall(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'ssl_install',
            'payload' => [
                'certificate_id' => $this->id,
                'domain' => $this->domain,
                'certificate' => $this->certificate,
                'private_key' => $this->private_key,
                'chain' => $this->chain,
            ],
        ]);
    }

    public function markActive(string $certificate, string $privateKey, ?string $chain = null): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'certificate' => $certificate,
            'private_key' => $privateKey,
            'chain' => $chain,
            'issued_at' => now(),
            'expires_at' => now()->addDays(90), // Let's Encrypt certs are 90 days
            'error_message' => null,
            'renewal_count' => $this->renewal_count + 1,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function getCertbotCommand(): string
    {
        $webroot = $this->webApp->document_root;
        $domain = $this->domain;

        return "certbot certonly --webroot -w {$webroot} -d {$domain} --non-interactive --agree-tos --email admin@{$domain}";
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }
}
