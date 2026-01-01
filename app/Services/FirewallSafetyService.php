<?php

namespace App\Services;

use App\Models\AgentJob;
use App\Models\FirewallRule;
use App\Models\Server;
use App\Notifications\FirewallRollbackWarning;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirewallSafetyService
{
    /**
     * Default confirmation timeout in seconds.
     */
    protected int $confirmationTimeout;

    /**
     * Critical ports that need extra confirmation.
     */
    protected array $criticalPorts = [22, 2222]; // SSH ports

    public function __construct()
    {
        $this->confirmationTimeout = config('sitekit.firewall_confirmation_timeout', 300);
    }

    /**
     * Determine if a rule requires confirmation (could lock user out).
     */
    public function requiresConfirmation(FirewallRule $rule): bool
    {
        // Deny rules on SSH ports always require confirmation
        if ($rule->action === FirewallRule::ACTION_DENY) {
            $ports = $this->parsePorts($rule->port);
            foreach ($ports as $port) {
                if (in_array($port, $this->criticalPorts)) {
                    return true;
                }
            }
        }

        // Blocking all inbound traffic requires confirmation
        if ($rule->action === FirewallRule::ACTION_DENY &&
            $rule->direction === FirewallRule::DIRECTION_IN &&
            $rule->from_ip === 'any' &&
            $rule->port === 'any') {
            return true;
        }

        // Deny rules that could block management access
        if ($rule->action === FirewallRule::ACTION_DENY && $rule->from_ip === 'any') {
            return true;
        }

        return false;
    }

    /**
     * Check if a rule would block the current user's access.
     */
    public function wouldBlockCurrentUser(FirewallRule $rule, string $userIp): bool
    {
        if ($rule->action !== FirewallRule::ACTION_DENY) {
            return false;
        }

        // Check if deny rule applies to user's IP
        if ($rule->from_ip === 'any') {
            $ports = $this->parsePorts($rule->port);
            return count(array_intersect($ports, $this->criticalPorts)) > 0;
        }

        // Check CIDR ranges
        if (Str::contains($rule->from_ip, '/')) {
            return $this->ipInCidr($userIp, $rule->from_ip);
        }

        return $rule->from_ip === $userIp;
    }

    /**
     * Apply a rule with safety confirmation.
     */
    public function applyWithSafety(FirewallRule $rule): array
    {
        $requiresConfirmation = $this->requiresConfirmation($rule);

        if ($requiresConfirmation) {
            $rule->update([
                'is_pending_confirmation' => true,
                'confirmation_token' => Str::random(32),
                'confirmation_expires_at' => now()->addSeconds($this->confirmationTimeout),
                'is_active' => true, // Temporarily active
            ]);

            Log::info("Firewall rule {$rule->id} applied with confirmation required", [
                'rule_id' => $rule->id,
                'server_id' => $rule->server_id,
                'timeout' => $this->confirmationTimeout,
            ]);
        } else {
            $rule->update([
                'is_pending_confirmation' => false,
                'confirmation_token' => null,
                'is_active' => true,
            ]);
        }

        // Dispatch job to apply rule on server
        $job = $rule->dispatchApply();

        return [
            'requires_confirmation' => $requiresConfirmation,
            'confirmation_token' => $rule->confirmation_token,
            'confirmation_url' => $requiresConfirmation
                ? route('api.firewall.confirm', ['token' => $rule->confirmation_token])
                : null,
            'timeout_seconds' => $requiresConfirmation ? $this->confirmationTimeout : null,
            'job_id' => $job->id,
        ];
    }

    /**
     * Confirm a firewall rule is working (user can still access).
     */
    public function confirm(string $token): ?FirewallRule
    {
        $rule = FirewallRule::where('confirmation_token', $token)
            ->where('is_pending_confirmation', true)
            ->first();

        if (!$rule) {
            return null;
        }

        $rule->update([
            'is_pending_confirmation' => false,
            'confirmation_token' => null,
            'confirmation_expires_at' => null,
        ]);

        Log::info("Firewall rule {$rule->id} confirmed", ['rule_id' => $rule->id]);

        return $rule;
    }

    /**
     * Rollback unconfirmed rules that have expired.
     */
    public function rollbackExpiredRules(): Collection
    {
        $expiredRules = FirewallRule::where('is_pending_confirmation', true)
            ->where('confirmation_expires_at', '<', now())
            ->get();

        $rolledBack = collect();

        foreach ($expiredRules as $rule) {
            $this->rollback($rule, 'Confirmation timeout expired');
            $rolledBack->push($rule);
        }

        if ($rolledBack->isNotEmpty()) {
            Log::warning("Rolled back {$rolledBack->count()} expired firewall rules");
        }

        return $rolledBack;
    }

    /**
     * Rollback a specific rule.
     */
    public function rollback(FirewallRule $rule, ?string $reason = null): AgentJob
    {
        Log::info("Rolling back firewall rule {$rule->id}", [
            'rule_id' => $rule->id,
            'reason' => $reason,
        ]);

        $rule->update([
            'is_active' => false,
            'is_pending_confirmation' => false,
            'confirmation_token' => null,
            'confirmation_expires_at' => null,
            'rollback_reason' => $reason,
            'rolled_back_at' => now(),
        ]);

        // Notify the team owner
        try {
            $rule->team->owner->notify(new FirewallRollbackWarning($rule, $reason));
        } catch (\Exception $e) {
            Log::error("Failed to send rollback notification", ['error' => $e->getMessage()]);
        }

        return AgentJob::create([
            'server_id' => $rule->server_id,
            'team_id' => $rule->team_id,
            'type' => 'revert_firewall_rule',
            'priority' => 1, // High priority
            'payload' => [
                'rule_id' => $rule->id,
                'command' => $rule->toUfwDeleteCommand(),
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Create a safe set of default rules for a new server.
     */
    public function createDefaultRules(Server $server): void
    {
        FirewallRule::createDefaultRules($server);

        // Enable UFW on the server
        AgentJob::create([
            'server_id' => $server->id,
            'team_id' => $server->team_id,
            'type' => 'enable_firewall',
            'payload' => [
                'default_incoming' => 'deny',
                'default_outgoing' => 'allow',
            ],
        ]);
    }

    /**
     * Check if a server's SSH access is properly configured.
     */
    public function validateSshAccess(Server $server): array
    {
        $sshRules = FirewallRule::where('server_id', $server->id)
            ->where('is_active', true)
            ->where('action', FirewallRule::ACTION_ALLOW)
            ->where('direction', FirewallRule::DIRECTION_IN)
            ->whereIn('port', ['22', '2222'])
            ->get();

        $issues = [];

        if ($sshRules->isEmpty()) {
            $issues[] = 'No active SSH allow rules found';
        }

        // Check for conflicting deny rules
        $denyRules = FirewallRule::where('server_id', $server->id)
            ->where('is_active', true)
            ->where('action', FirewallRule::ACTION_DENY)
            ->where('direction', FirewallRule::DIRECTION_IN)
            ->get();

        foreach ($denyRules as $denyRule) {
            $denyPorts = $this->parsePorts($denyRule->port);
            if (in_array(22, $denyPorts) || in_array(2222, $denyPorts)) {
                if ($denyRule->order < ($sshRules->min('order') ?? PHP_INT_MAX)) {
                    $issues[] = "Deny rule on SSH port has higher priority than allow rule";
                }
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'ssh_rules_count' => $sshRules->count(),
        ];
    }

    /**
     * Parse port specification into array of port numbers.
     */
    protected function parsePorts(string $portSpec): array
    {
        if ($portSpec === 'any') {
            return $this->criticalPorts; // Return critical ports for "any"
        }

        $ports = [];
        $parts = explode(',', $portSpec);

        foreach ($parts as $part) {
            $part = trim($part);
            if (Str::contains($part, ':')) {
                // Range like 3000:3100
                [$start, $end] = explode(':', $part);
                for ($p = (int) $start; $p <= (int) $end; $p++) {
                    $ports[] = $p;
                }
            } else {
                $ports[] = (int) $part;
            }
        }

        return $ports;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = ~((1 << (32 - (int) $mask)) - 1);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Get rules pending confirmation for a server.
     */
    public function getPendingConfirmation(Server $server): Collection
    {
        return FirewallRule::where('server_id', $server->id)
            ->where('is_pending_confirmation', true)
            ->get()
            ->map(function ($rule) {
                return [
                    'rule' => $rule,
                    'remaining_seconds' => $rule->confirmation_expires_at
                        ? max(0, now()->diffInSeconds($rule->confirmation_expires_at, false))
                        : 0,
                ];
            });
    }
}
