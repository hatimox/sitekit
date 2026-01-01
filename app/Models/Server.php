<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Server extends Model
{
    use HasFactory;
    use HasUuids;
    use LogsActivity;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_FAILED = 'failed';

    public const PHASE_PENDING = 'pending';
    public const PHASE_BOOTSTRAP = 'bootstrap';
    public const PHASE_INSTALLING = 'installing';
    public const PHASE_COMPLETED = 'completed';
    public const PHASE_FAILED = 'failed';

    public const PROVIDER_CUSTOM = 'custom';
    public const PROVIDER_DIGITALOCEAN = 'digitalocean';
    public const PROVIDER_LINODE = 'linode';
    public const PROVIDER_VULTR = 'vultr';
    public const PROVIDER_HETZNER = 'hetzner';
    public const PROVIDER_AWS = 'aws';

    protected $fillable = [
        'team_id',
        'name',
        'ip_address',
        'ssh_port',
        'status',
        'provisioning_phase',
        'provider',
        'agent_token',
        'agent_token_expires_at',
        'agent_public_key',
        'last_heartbeat_at',
        'services_status',
        'os_name',
        'os_version',
        'cpu_count',
        'memory_mb',
        'disk_gb',
        // Resource alert settings
        'alert_load_threshold',
        'alert_memory_threshold',
        'alert_disk_threshold',
        'resource_alerts_enabled',
        'is_load_alert_active',
        'is_memory_alert_active',
        'is_disk_alert_active',
        'last_resource_alert_at',
        'database_health',
    ];

    protected function casts(): array
    {
        return [
            'agent_token_expires_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'services_status' => 'array',
            'alert_load_threshold' => 'float',
            'alert_memory_threshold' => 'float',
            'alert_disk_threshold' => 'float',
            'resource_alerts_enabled' => 'boolean',
            'is_load_alert_active' => 'boolean',
            'is_memory_alert_active' => 'boolean',
            'is_disk_alert_active' => 'boolean',
            'last_resource_alert_at' => 'datetime',
            'database_health' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Server $server) {
            if (empty($server->agent_token)) {
                $server->agent_token = Str::random(64);
                $server->agent_token_expires_at = now()->addHours(24);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function agentJobs(): HasMany
    {
        return $this->hasMany(AgentJob::class);
    }

    public function sshKeys(): BelongsToMany
    {
        return $this->belongsToMany(SshKey::class, 'server_ssh_key')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function webApps(): HasMany
    {
        return $this->hasMany(WebApp::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }

    public function firewallRules(): HasMany
    {
        return $this->hasMany(FirewallRule::class)->orderBy('order');
    }

    public function healthMonitors(): HasMany
    {
        return $this->hasMany(HealthMonitor::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(ServerStat::class)->orderByDesc('recorded_at');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    public function provisioningSteps(): HasMany
    {
        return $this->hasMany(ServerProvisioningStep::class)->orderBy('order');
    }

    public function latestStats(): ?ServerStat
    {
        return $this->stats()->first();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProvisioning(): bool
    {
        return $this->status === self::STATUS_PROVISIONING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOffline(): bool
    {
        return $this->status === self::STATUS_OFFLINE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isConnected(): bool
    {
        return $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->gt(now()->subMinutes(5));
    }

    public function regenerateAgentToken(): void
    {
        $this->update([
            'agent_token' => Str::random(64),
            'agent_token_expires_at' => now()->addHours(24),
        ]);
    }

    public function getProvisioningCommand(): string
    {
        // Use request URL if available (handles ngrok/tunnels), otherwise fall back to config
        $baseUrl = request()?->getSchemeAndHttpHost() ?: config('app.url');
        $token = $this->agent_token;

        return "curl -sSL {$baseUrl}/provision/{$token} | sudo bash";
    }

    public function dispatchJob(string $type, array $payload = [], int $priority = 5): AgentJob
    {
        return $this->agentJobs()->create([
            'team_id' => $this->team_id,
            'type' => $type,
            'payload' => $payload,
            'priority' => $priority,
        ]);
    }

    /**
     * Dispatch a server restore job to clean up all SiteKit components.
     * This will remove all installed packages, configurations, and data.
     */
    public function dispatchRestore(bool $removePackages = true, bool $removeData = true): AgentJob
    {
        // Mark server as restoring
        $this->update(['status' => self::STATUS_PROVISIONING]);

        return $this->dispatchJob('server_restore', [
            'server_id' => $this->id,
            'keep_ssh_access' => true,
            'remove_packages' => $removePackages,
            'remove_data' => $removeData,
        ], priority: 1); // High priority
    }

    /**
     * Check if server is in installing phase.
     */
    public function isInstalling(): bool
    {
        return $this->provisioning_phase === self::PHASE_INSTALLING;
    }

    /**
     * Check if provisioning is complete.
     */
    public function isProvisioningComplete(): bool
    {
        return $this->provisioning_phase === self::PHASE_COMPLETED;
    }

    /**
     * Create default provisioning steps for this server.
     */
    public function createProvisioningSteps(?array $enabledSteps = null): void
    {
        // Delete any existing steps
        $this->provisioningSteps()->delete();

        $defaultSteps = ServerProvisioningStep::getDefaultSteps();

        foreach ($defaultSteps as $stepData) {
            // If enabledSteps is provided, only create those steps
            // Otherwise, create all default steps
            if ($enabledSteps !== null && !in_array($stepData['step_type'], $enabledSteps)) {
                continue;
            }

            // Skip non-default steps if no explicit list provided
            if ($enabledSteps === null && !$stepData['is_default']) {
                continue;
            }

            $this->provisioningSteps()->create($stepData);
        }
    }

    /**
     * Dispatch all pending provisioning steps as agent jobs.
     */
    public function dispatchAllProvisioningSteps(): void
    {
        $this->update([
            'status' => self::STATUS_PROVISIONING,
            'provisioning_phase' => self::PHASE_INSTALLING,
        ]);

        $this->provisioningSteps()
            ->where('status', ServerProvisioningStep::STATUS_PENDING)
            ->each(function (ServerProvisioningStep $step) {
                $step->dispatchJob();
            });
    }

    /**
     * Get provisioning progress as percentage.
     */
    public function getProvisioningProgress(): int
    {
        $total = $this->provisioningSteps()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->provisioningSteps()
            ->whereIn('status', [
                ServerProvisioningStep::STATUS_COMPLETED,
                ServerProvisioningStep::STATUS_SKIPPED,
            ])
            ->count();

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Check if all provisioning steps are complete.
     */
    public function areAllStepsComplete(): bool
    {
        $total = $this->provisioningSteps()->count();
        if ($total === 0) {
            return true;
        }

        $doneStatuses = [
            ServerProvisioningStep::STATUS_COMPLETED,
            ServerProvisioningStep::STATUS_SKIPPED,
        ];

        return $this->provisioningSteps()
            ->whereNotIn('status', $doneStatuses)
            ->doesntExist();
    }

    /**
     * Check if any required steps have failed.
     */
    public function hasFailedRequiredSteps(): bool
    {
        return $this->provisioningSteps()
            ->where('is_required', true)
            ->where('status', ServerProvisioningStep::STATUS_FAILED)
            ->exists();
    }

    /**
     * Complete provisioning if all steps are done.
     */
    public function checkAndCompleteProvisioning(): void
    {
        if (!$this->areAllStepsComplete()) {
            return;
        }

        if ($this->hasFailedRequiredSteps()) {
            $this->update([
                'status' => self::STATUS_FAILED,
                'provisioning_phase' => self::PHASE_FAILED,
            ]);
            return;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'provisioning_phase' => self::PHASE_COMPLETED,
        ]);
    }

    protected function getLoggableAttributes(): array
    {
        return ['name', 'ip_address', 'status', 'provider'];
    }

    /**
     * Sync Service records from heartbeat services_status data
     */
    public function syncServicesFromHeartbeat(): void
    {
        if (empty($this->services_status)) {
            return;
        }

        $existingServices = $this->services()->get()->keyBy(function ($service) {
            return $service->type . '-' . $service->version;
        });

        $seenKeys = [];

        foreach ($this->services_status as $serviceData) {
            $detectedVersion = $serviceData['version'] ?? null;
            $parsed = $this->parseServiceName($serviceData['name'] ?? '', $detectedVersion);
            if (!$parsed) {
                continue;
            }

            $key = $parsed['type'] . '-' . $parsed['version'];
            $seenKeys[] = $key;

            $status = ($serviceData['status'] ?? '') === 'running'
                ? Service::STATUS_ACTIVE
                : Service::STATUS_STOPPED;

            if ($existingServices->has($key)) {
                // Update existing service status
                $existingServices->get($key)->update(['status' => $status]);
            } else {
                // Create new service
                $this->services()->create([
                    'type' => $parsed['type'],
                    'version' => $parsed['version'],
                    'status' => $status,
                    'installed_at' => now(),
                ]);
            }
        }

        // Remove services that are no longer reported
        $this->services()
            ->get()
            ->each(function ($service) use ($seenKeys) {
                $key = $service->type . '-' . $service->version;
                if (!in_array($key, $seenKeys)) {
                    $service->delete();
                }
            });
    }

    /**
     * Update existing Service status from heartbeat data (lightweight, for every heartbeat)
     * Also adds new services and removes stale ones.
     */
    public function updateServicesStatusFromHeartbeat(): void
    {
        if (empty($this->services_status)) {
            return;
        }

        // Build a map of service_name => status from heartbeat
        $statusMap = [];
        foreach ($this->services_status as $serviceData) {
            $detectedVersion = $serviceData['version'] ?? null;
            $parsed = $this->parseServiceName($serviceData['name'] ?? '', $detectedVersion);
            if (!$parsed) {
                continue;
            }
            $key = $parsed['type'] . '-' . $parsed['version'];
            $rawStatus = $serviceData['status'] ?? '';
            $statusMap[$key] = [
                'type' => $parsed['type'],
                'version' => $parsed['version'],
                'status' => $rawStatus === 'running' ? Service::STATUS_ACTIVE : Service::STATUS_STOPPED,
                'raw' => $rawStatus,
            ];
        }

        $existingKeys = [];

        // Update status for existing services
        $this->services()->each(function ($service) use ($statusMap, &$existingKeys) {
            $key = $service->type . '-' . $service->version;
            $existingKeys[] = $key;

            if (!isset($statusMap[$key])) {
                // Service no longer reported by agent - remove it
                $service->delete();
                return;
            }

            $newStatus = $statusMap[$key]['status'];
            $rawStatus = $statusMap[$key]['raw'];
            $oldStatus = $service->status;

            if ($oldStatus !== $newStatus) {
                $service->update(['status' => $newStatus]);

                // Detect crash: was active, now failed (not just stopped)
                if ($oldStatus === Service::STATUS_ACTIVE && $rawStatus === 'failed') {
                    $service->update(['status' => Service::STATUS_FAILED]);
                    $owner = $this->team?->owner;
                    if ($owner) {
                        $owner->notify(new \App\Notifications\ServiceCrashed($service));
                    }
                }
            }
        });

        // Add new services that weren't in the database
        foreach ($statusMap as $key => $data) {
            if (!in_array($key, $existingKeys)) {
                $this->services()->create([
                    'type' => $data['type'],
                    'version' => $data['version'],
                    'status' => $data['status'],
                    'installed_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get active database engines on this server.
     * Returns array of database types that are currently active.
     */
    public function getActiveDatabaseEngines(): array
    {
        $databaseTypes = [
            Service::TYPE_MYSQL,
            Service::TYPE_MARIADB,
            Service::TYPE_POSTGRESQL,
        ];

        return $this->services()
            ->whereIn('type', $databaseTypes)
            ->where('status', Service::STATUS_ACTIVE)
            ->pluck('type')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get database health for a specific service type.
     * Returns health data with status, response_ms, and error.
     */
    public function getDatabaseHealth(string $serviceType): ?array
    {
        if (empty($this->database_health)) {
            return null;
        }

        // Map service type to health key
        $healthKey = match ($serviceType) {
            Service::TYPE_MYSQL, Service::TYPE_MARIADB => 'mysql',
            Service::TYPE_POSTGRESQL => 'postgresql',
            default => null,
        };

        if (!$healthKey || !isset($this->database_health[$healthKey])) {
            return null;
        }

        return $this->database_health[$healthKey];
    }

    /**
     * Check if a database service is healthy.
     */
    public function isDatabaseHealthy(string $serviceType): ?bool
    {
        $health = $this->getDatabaseHealth($serviceType);
        if ($health === null) {
            return null; // Unknown
        }
        return ($health['status'] ?? '') === 'ok';
    }

    /**
     * Parse systemd service name into type and version
     *
     * @param string $name The systemd service name (e.g., "nginx", "php8.4-fpm")
     * @param string|null $detectedVersion Actual version detected by agent (e.g., "1.24.0", "8.4.1")
     */
    protected function parseServiceName(string $name, ?string $detectedVersion = null): ?array
    {
        // PHP-FPM: php8.3-fpm, php8.2-fpm, etc.
        if (preg_match('/^php(\d+\.\d+)-fpm$/', $name, $matches)) {
            // Use detected version (e.g., "8.4.1") or fall back to version from service name (e.g., "8.4")
            $version = $detectedVersion ?: $matches[1];
            return ['type' => Service::TYPE_PHP, 'version' => $version];
        }

        // Service type mappings (fallback versions used only if agent doesn't report version)
        $typeMap = [
            'nginx' => Service::TYPE_NGINX,
            'apache2' => Service::TYPE_APACHE,
            'mysql' => Service::TYPE_MYSQL,
            'mariadb' => Service::TYPE_MARIADB,
            'postgresql' => Service::TYPE_POSTGRESQL,
            'redis-server' => Service::TYPE_REDIS,
            'memcached' => Service::TYPE_MEMCACHED,
            'supervisor' => Service::TYPE_SUPERVISOR,
            'beanstalkd' => Service::TYPE_BEANSTALKD,
        ];

        // Fallback versions (only used when agent doesn't detect version)
        $fallbackVersions = [
            'nginx' => 'latest',
            'apache2' => '2.4',
            'mysql' => '8.4',
            'mariadb' => '11.4',
            'postgresql' => '17',
            'redis-server' => '7.4',
            'memcached' => '1.6',
            'supervisor' => 'latest',
            'beanstalkd' => 'latest',
        ];

        if (isset($typeMap[$name])) {
            // Use detected version from agent, or fall back to default
            $version = $detectedVersion ?: ($fallbackVersions[$name] ?? 'latest');
            return ['type' => $typeMap[$name], 'version' => $version];
        }

        return null;
    }

    /**
     * Sync Tool records from heartbeat tools_status data
     */
    public function syncToolsFromHeartbeat(array $toolsStatus): void
    {
        if (empty($toolsStatus)) {
            return;
        }

        $existingTools = $this->tools()->get()->keyBy('name');
        $seenNames = [];

        foreach ($toolsStatus as $toolData) {
            $name = $toolData['name'] ?? '';
            if (empty($name)) {
                continue;
            }

            $seenNames[] = $name;

            $data = [
                'version' => $toolData['version'] ?? 'unknown',
                'path' => $toolData['path'] ?? null,
            ];

            if ($existingTools->has($name)) {
                // Update existing tool
                $existingTools->get($name)->update($data);
            } else {
                // Create new tool
                $this->tools()->create(array_merge(['name' => $name], $data));
            }
        }

        // Remove tools that are no longer reported
        $this->tools()
            ->whereNotIn('name', $seenNames)
            ->delete();
    }

    /**
     * Check if any resource alert is currently active.
     */
    public function hasActiveResourceAlert(): bool
    {
        return $this->is_load_alert_active
            || $this->is_memory_alert_active
            || $this->is_disk_alert_active;
    }

    /**
     * Get the effective load threshold (uses cpu_count if not set).
     */
    public function getEffectiveLoadThreshold(): float
    {
        if ($this->alert_load_threshold > 0) {
            return $this->alert_load_threshold;
        }

        // Default: cpu_count * 2 (or 5 if cpu_count unknown)
        return ($this->cpu_count ?? 2) * 2;
    }

    /**
     * Check if we should send a resource alert (respects cooldown).
     */
    public function canSendResourceAlert(int $cooldownMinutes = 15): bool
    {
        if (!$this->resource_alerts_enabled) {
            return false;
        }

        if ($this->last_resource_alert_at === null) {
            return true;
        }

        return $this->last_resource_alert_at->lt(now()->subMinutes($cooldownMinutes));
    }

    /**
     * Record that a resource alert was sent.
     */
    public function recordResourceAlert(): void
    {
        $this->update(['last_resource_alert_at' => now()]);
    }
}
