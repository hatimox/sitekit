<?php

namespace App\Console\Commands;

use App\Services\FirewallSafetyService;
use Illuminate\Console\Command;

class RollbackExpiredFirewallRules extends Command
{
    protected $signature = 'firewall:rollback-expired';

    protected $description = 'Rollback firewall rules that were not confirmed within the timeout period';

    public function handle(FirewallSafetyService $safetyService): int
    {
        $rolledBack = $safetyService->rollbackExpiredRules();

        if ($rolledBack->isEmpty()) {
            $this->info('No expired firewall rules to rollback.');
            return self::SUCCESS;
        }

        $this->warn("Rolled back {$rolledBack->count()} expired firewall rule(s):");

        foreach ($rolledBack as $rule) {
            $this->line("  - {$rule->description} on {$rule->server->name} (port {$rule->port})");
        }

        return self::SUCCESS;
    }
}
