<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Notifications\ServerOffline;
use Illuminate\Console\Command;

class CheckServerHeartbeats extends Command
{
    protected $signature = 'servers:check-heartbeats {--threshold=5 : Minutes without heartbeat before marking offline}';

    protected $description = 'Check server heartbeats and mark unresponsive servers as offline';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $servers = Server::where('status', Server::STATUS_ACTIVE)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', now()->subMinutes($threshold));
            })
            ->get();

        if ($servers->isEmpty()) {
            $this->info('All servers are responding normally.');
            return self::SUCCESS;
        }

        $this->warn("Found {$servers->count()} unresponsive server(s):");

        foreach ($servers as $server) {
            $lastSeen = $server->last_heartbeat_at?->diffForHumans() ?? 'never';

            $server->update(['status' => Server::STATUS_OFFLINE]);

            $this->line("  - {$server->name} ({$server->ip_address}) - Last seen: {$lastSeen}");

            // Notify team owner
            $owner = $server->team->owner;
            if ($owner) {
                $owner->notify(new ServerOffline($server, "No heartbeat received for {$threshold}+ minutes"));
            }
        }

        return self::SUCCESS;
    }
}
