<?php

namespace App\Http\Controllers;

use App\Events\AgentJobUpdated;
use App\Events\ServerConnected;
use App\Events\ServerStatsUpdated;
use App\Events\ServerStatusChanged;
use App\Models\AgentJob;
use App\Models\FirewallRule;
use App\Models\Server;
use App\Models\ServerProvisioningStep;
use App\Models\ServerStat;
use App\Models\Service;
use App\Models\ServiceStat;
use App\Models\SupervisorProgram;
use App\Models\WebApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    /**
     * Report agent heartbeat and update server stats
     */
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->get('server');

        $validated = $request->validate([
            'os_name' => 'nullable|string|max:100',
            'os_version' => 'nullable|string|max:50',
            'cpu_count' => 'nullable|integer|min:1',
            'memory_mb' => 'nullable|integer|min:1',
            'disk_gb' => 'nullable|integer|min:1',
            'cpu_percent' => 'nullable|numeric|min:0|max:100',
            'memory_percent' => 'nullable|numeric|min:0|max:100',
            'disk_percent' => 'nullable|numeric|min:0|max:100',
            'load_1m' => 'nullable|numeric|min:0',
            'load_5m' => 'nullable|numeric|min:0',
            'load_15m' => 'nullable|numeric|min:0',
            'services_status' => 'nullable|array',
            'services_status.*.name' => 'required_with:services_status|string',
            'services_status.*.status' => 'required_with:services_status|string',
            'services_status.*.enabled' => 'nullable|boolean',
            'services_status.*.version' => 'nullable|string',
            'services_status.*.cpu_percent' => 'nullable|numeric|min:0|max:100',
            'services_status.*.memory_mb' => 'nullable|integer|min:0',
            'services_status.*.uptime_seconds' => 'nullable|integer|min:0',
            'daemons_status' => 'nullable|array',
            'daemons_status.*.name' => 'required_with:daemons_status|string',
            'daemons_status.*.status' => 'required_with:daemons_status|string',
            'daemons_status.*.pid' => 'nullable|integer|min:0',
            'daemons_status.*.cpu_percent' => 'nullable|numeric|min:0',
            'daemons_status.*.memory_mb' => 'nullable|integer|min:0',
            'daemons_status.*.uptime_seconds' => 'nullable|integer|min:0',
            'tools_status' => 'nullable|array',
            'tools_status.*.name' => 'required_with:tools_status|string',
            'tools_status.*.version' => 'required_with:tools_status|string',
            'tools_status.*.path' => 'nullable|string',
            'database_health' => 'nullable|array',
        ]);

        // Track previous status for event dispatching
        $previousStatus = $server->status;
        $wasConnecting = $server->status === Server::STATUS_PROVISIONING;

        // Update server info
        $server->update([
            'last_heartbeat_at' => now(),
            'status' => Server::STATUS_ACTIVE,
            'os_name' => $validated['os_name'] ?? $server->os_name,
            'os_version' => $validated['os_version'] ?? $server->os_version,
            'cpu_count' => $validated['cpu_count'] ?? $server->cpu_count,
            'memory_mb' => $validated['memory_mb'] ?? $server->memory_mb,
            'disk_gb' => $validated['disk_gb'] ?? $server->disk_gb,
            'services_status' => $validated['services_status'] ?? $server->services_status,
        ]);

        // Dispatch ServerConnected event on first successful heartbeat
        if ($wasConnecting) {
            event(new ServerConnected($server));

            // Check if this is a new server in bootstrap phase (needs software installation)
            if ($server->provisioning_phase === Server::PHASE_BOOTSTRAP) {
                // Create provisioning steps and dispatch jobs
                $server->createProvisioningSteps();
                $server->dispatchAllProvisioningSteps();
            } elseif ($server->provisioning_phase === Server::PHASE_COMPLETED || $server->provisioning_phase === Server::PHASE_PENDING) {
                // Legacy server or completed provisioning - sync services from heartbeat
                $server->syncServicesFromHeartbeat();

                // Notify user of successful server provisioning
                $owner = $server->team?->owner;
                if ($owner) {
                    $owner->notify(new \App\Notifications\ServerProvisioned($server));
                }
            }
        }

        // Check if server is installing and all steps are complete
        if ($server->isInstalling()) {
            $server->checkAndCompleteProvisioning();
        }

        // Dispatch status change event if status changed
        if ($previousStatus !== Server::STATUS_ACTIVE) {
            event(new ServerStatusChanged($server, $previousStatus));
        }

        // Update service status from heartbeat data (lightweight sync on every heartbeat)
        if (!empty($validated['services_status'])) {
            $server->updateServicesStatusFromHeartbeat();

            // Store per-service metrics if provided
            $this->storeServiceMetrics($server, $validated['services_status']);
        }

        // Update daemon (supervisor program) metrics from heartbeat data
        if (!empty($validated['daemons_status'])) {
            $this->updateDaemonMetrics($server, $validated['daemons_status']);
        }

        // Sync tools from heartbeat data
        if (!empty($validated['tools_status'])) {
            $server->syncToolsFromHeartbeat($validated['tools_status']);
        }

        // Store database health status
        if (!empty($validated['database_health'])) {
            $server->update(['database_health' => $validated['database_health']]);
        }

        // Record stats if provided
        if (isset($validated['cpu_percent']) || isset($validated['memory_percent'])) {
            ServerStat::create([
                'server_id' => $server->id,
                'cpu_percent' => $validated['cpu_percent'] ?? 0,
                'memory_percent' => $validated['memory_percent'] ?? 0,
                'disk_percent' => $validated['disk_percent'] ?? 0,
                'load_1m' => $validated['load_1m'] ?? 0,
                'load_5m' => $validated['load_5m'] ?? 0,
                'load_15m' => $validated['load_15m'] ?? 0,
                'recorded_at' => now(),
            ]);

            // Broadcast real-time stats
            event(new ServerStatsUpdated($server, $validated));
        }

        return response()->json([
            'status' => 'ok',
            'server_id' => $server->id,
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get pending jobs for the agent
     */
    public function jobs(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->get('server');

        $jobs = AgentJob::where('server_id', $server->id)
            ->where('status', AgentJob::STATUS_PENDING)
            ->orderBy('priority')
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AgentJob $job) => [
                'id' => $job->id,
                'type' => $job->type,
                'payload' => $job->payload,
                'priority' => $job->priority,
                'created_at' => $job->created_at->toIso8601String(),
            ]);

        // Mark fetched jobs as running
        AgentJob::where('server_id', $server->id)
            ->where('status', AgentJob::STATUS_PENDING)
            ->whereIn('id', $jobs->pluck('id'))
            ->update([
                'status' => AgentJob::STATUS_RUNNING,
                'started_at' => now(),
            ]);

        return response()->json([
            'jobs' => $jobs,
            'count' => $jobs->count(),
        ]);
    }

    /**
     * Report job completion
     */
    public function jobComplete(Request $request, string $jobId): JsonResponse
    {
        /** @var Server $server */
        $server = $request->get('server');

        $validated = $request->validate([
            'status' => 'required|in:completed,failed',
            'output' => 'nullable|string|max:65535',
            'error' => 'nullable|string|max:65535',
            'exit_code' => 'nullable|integer',
        ]);

        $job = AgentJob::where('id', $jobId)
            ->where('server_id', $server->id)
            ->first();

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $job->update([
            'status' => $validated['status'],
            'output' => $validated['output'] ?? null,
            'error' => $validated['error'] ?? null,
            'exit_code' => $validated['exit_code'] ?? null,
            'completed_at' => now(),
        ]);

        // Broadcast job update
        event(new AgentJobUpdated($job));

        // Handle job-specific callbacks
        $this->handleJobCallback($job, $validated);

        return response()->json([
            'status' => 'ok',
            'job_id' => $job->id,
        ]);
    }

    /**
     * Confirm firewall rule is working (user can still access server)
     */
    public function confirmFirewallRule(Request $request, string $token): JsonResponse
    {
        $rule = FirewallRule::where('confirmation_token', $token)
            ->where('is_pending_confirmation', true)
            ->first();

        if (!$rule) {
            return response()->json(['error' => 'Rule not found or already confirmed'], 404);
        }

        $rule->update([
            'is_pending_confirmation' => false,
            'confirmation_token' => null,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Firewall rule confirmed and activated',
        ]);
    }

    /**
     * Handle job-specific callbacks after completion
     */
    protected function handleJobCallback(AgentJob $job, array $result): void
    {
        $type = $job->type;
        $success = $result['status'] === 'completed';
        $payload = $job->payload;
        $error = $result['error'] ?? null;

        match ($type) {
            // Service management
            'service_install' => $this->handleServiceInstallCallback($payload, $success, $result),
            'service_uninstall' => $this->handleServiceUninstallCallback($payload, $success),
            'service_restart', 'service_start', 'service_stop', 'service_reload' => $this->handleServiceActionCallback($job, $type, $payload, $success),

            // Firewall
            'firewall_apply', 'apply_firewall_rule' => $this->handleFirewallCallback($payload, $success, $result),

            // Deployment
            'deploy' => $this->handleDeployCallback($payload, $success, $error, $result['output'] ?? null),

            // Web Apps
            'create_webapp' => $this->handleWebAppCreateCallback($payload, $success, $error),
            'delete_webapp' => $this->handleWebAppDeleteCallback($payload, $success),

            // SSL
            'ssl_issue', 'ssl_renew' => $this->handleSslCallback($payload, $success, $error),

            // Database
            'create_database' => $this->handleDatabaseCreateCallback($payload, $success, $error),
            'delete_database' => $this->handleDatabaseDeleteCallback($payload, $success),
            'database_backup' => $this->handleDatabaseBackupCallback($payload, $success, $error, $result),

            // Supervisor
            'supervisor_create', 'supervisor_update' => $this->handleSupervisorCallback($payload, $success, $error),
            'supervisor_delete' => $this->handleSupervisorDeleteCallback($payload, $success),
            'supervisor_start' => $this->handleSupervisorStartCallback($payload, $success, $error),
            'supervisor_stop' => $this->handleSupervisorStopCallback($payload, $success),

            // SSH Keys
            'ssh_key_add' => $this->handleSshKeyAddCallback($job->server_id, $payload, $success),
            'ssh_key_remove' => $this->handleSshKeyRemoveCallback($job->server_id, $payload, $success),
            'ssh_key_sync' => $this->handleSshKeySyncCallback($job->server_id, $payload, $success),

            // Server management
            'server_restore' => $this->handleServerRestoreCallback($job, $payload, $success),

            // Provisioning steps
            'provision_system', 'provision_nginx', 'provision_apache', 'provision_php',
            'provision_mariadb', 'provision_mysql', 'provision_postgresql', 'provision_redis',
            'provision_memcached', 'provision_composer', 'provision_node',
            'provision_supervisor' => $this->handleProvisioningStepCallback($job, $success, $result),

            default => null,
        };
    }

    protected function handleServiceInstallCallback(array $payload, bool $success, array $result): void
    {
        $service = Service::find($payload['service_id'] ?? null);
        if (!$service) {
            return;
        }

        if ($success) {
            $service->markInstalled();
            $service->clearError();
        } else {
            $error = $result['error'] ?? 'Installation failed';
            $service->markFailed($error);
            $service->recordError($error);
        }
    }

    protected function handleServiceUninstallCallback(array $payload, bool $success): void
    {
        $service = Service::find($payload['service_id'] ?? null);
        if (!$service) {
            return;
        }

        if ($success) {
            $service->delete();
        } else {
            $service->update(['status' => Service::STATUS_ACTIVE]);
        }
    }

    protected function handleServiceActionCallback(AgentJob $job, string $type, array $payload, bool $success): void
    {
        $service = Service::find($payload['service_id'] ?? null);
        if (!$service || !$success) {
            return;
        }

        // Update service status based on action type
        if ($type === 'service_stop') {
            $service->update(['status' => Service::STATUS_STOPPED]);
        } elseif ($type === 'service_start') {
            $service->update(['status' => Service::STATUS_ACTIVE]);
        }

        // Notify user with specific notification type
        $owner = $service->server->team?->owner;
        if ($owner) {
            match ($type) {
                'service_start' => $owner->notify(new \App\Notifications\ServiceStarted($service)),
                'service_stop' => $owner->notify(new \App\Notifications\ServiceStopped($service)),
                'service_restart', 'service_reload' => $owner->notify(new \App\Notifications\ServiceRestarted($service, $type === 'service_restart' ? 'restarted' : 'reloaded')),
                default => null,
            };
        }
    }

    protected function handleFirewallCallback(array $payload, bool $success, array $result): void
    {
        $rule = FirewallRule::find($payload['rule_id'] ?? null);
        if (!$rule) {
            return;
        }

        if (!$success) {
            // Revert the rule if it failed
            $rule->update([
                'is_active' => false,
                'is_pending_confirmation' => false,
            ]);
        }
    }

    protected function handleDeployCallback(array $payload, bool $success, ?string $error, ?string $output): void
    {
        $deployment = \App\Models\Deployment::find($payload['deployment_id'] ?? null);
        if (!$deployment) {
            return;
        }

        if ($output) {
            $deployment->appendLog($output);
        }

        if ($success) {
            $deployment->markAs(\App\Models\Deployment::STATUS_ACTIVE);

            // Restart Node.js supervisor program if this is a Node.js app
            $webApp = $deployment->webApp;
            if ($webApp && $webApp->isNodeJs()) {
                $this->restartNodeSupervisor($webApp);
            }

            // Notify user of successful deployment
            $owner = $deployment->team->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\DeploymentSuccessful($deployment));
            }
        } else {
            $deployment->markAs(\App\Models\Deployment::STATUS_FAILED, $error);

            // Notify user of failed deployment
            $owner = $deployment->team->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\DeploymentFailed($deployment, $error));
            }
        }
    }

    /**
     * Restart the supervisor program for a Node.js web app after deployment.
     */
    protected function restartNodeSupervisor(WebApp $webApp): void
    {
        // Check for supervisor program linked to this web app
        if ($webApp->supervisor_program_id) {
            $program = SupervisorProgram::find($webApp->supervisor_program_id);
            if ($program) {
                $program->dispatchJob('supervisor_restart', [
                    'name' => $program->name,
                ], 3); // Higher priority for restart

                Log::info("Dispatched supervisor restart after deploy", [
                    'web_app_id' => $webApp->id,
                    'program_id' => $program->id,
                ]);
            }
        }

        // Also check for any supervisor programs linked to this web app (monorepo)
        $programs = SupervisorProgram::where('web_app_id', $webApp->id)->get();
        foreach ($programs as $program) {
            // Skip the main one if already restarted
            if ($program->id === $webApp->supervisor_program_id) {
                continue;
            }

            $program->dispatchJob('supervisor_restart', [
                'name' => $program->name,
            ], 3);
        }
    }

    protected function handleWebAppCreateCallback(array $payload, bool $success, ?string $error): void
    {
        $webApp = WebApp::find($payload['app_id'] ?? $payload['web_app_id'] ?? null);
        if (!$webApp) {
            return;
        }

        if ($success) {
            $webApp->update(['status' => WebApp::STATUS_ACTIVE]);
            $webApp->clearError();

            // Create supervisor program for Node.js apps
            if ($webApp->isNodeJs()) {
                $this->createNodeSupervisorProgram($webApp);
            }

            // Notify user of successful web app creation
            $owner = $webApp->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\WebAppCreated($webApp));
            }
        } else {
            // Record user-friendly error first (this sets status to 'error')
            if ($error) {
                $webApp->recordError($error);
            }

            // Then override with proper failed status
            $webApp->update([
                'status' => WebApp::STATUS_FAILED,
                'error_message' => $error,
            ]);

            // Notify team owner of web app creation failure
            $owner = $webApp->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\WebAppCreationFailed($webApp, $error ?? 'Web app creation failed'));
            }
        }
    }

    /**
     * Create a supervisor program for a Node.js web app.
     */
    protected function createNodeSupervisorProgram(WebApp $webApp): ?SupervisorProgram
    {
        if (!$webApp->isNodeJs() || !$webApp->node_port) {
            return null;
        }

        // Build the command string
        $command = $this->buildNodeCommand($webApp);

        // Create the supervisor program
        $program = SupervisorProgram::create([
            'server_id' => $webApp->server_id,
            'team_id' => $webApp->team_id,
            'web_app_id' => $webApp->id,
            'name' => "nodejs-{$webApp->id}",
            'command' => $command,
            'directory' => "{$webApp->root_path}/current",
            'user' => $webApp->system_user ?? 'sitekit',
            'numprocs' => 1,
            'autostart' => true,
            'autorestart' => true,
            'startsecs' => 10,
            'stopwaitsecs' => 30,
            'stdout_logfile' => "{$webApp->root_path}/logs/node.log",
            'stderr_logfile' => "{$webApp->root_path}/logs/node-error.log",
            'environment' => [
                'NODE_ENV' => 'production',
                'PORT' => (string) $webApp->node_port,
                'HOME' => "/home/{$webApp->system_user}",
            ],
            'status' => SupervisorProgram::STATUS_PENDING,
        ]);

        // Link the program to the web app
        $webApp->update(['supervisor_program_id' => $program->id]);

        // Dispatch job to create the supervisor config on the server
        $program->dispatchJob('supervisor_create', [
            'config' => $program->generateConfig(),
            'name' => $program->name,
        ]);

        Log::info("Created supervisor program for Node.js app", [
            'web_app_id' => $webApp->id,
            'program_id' => $program->id,
            'port' => $webApp->node_port,
        ]);

        return $program;
    }

    /**
     * Create multiple supervisor programs for monorepo Node.js apps.
     */
    protected function createNodeSupervisorPrograms(WebApp $webApp): array
    {
        if (!$webApp->isNodeJs() || empty($webApp->node_processes)) {
            return [];
        }

        $programs = [];

        foreach ($webApp->node_processes as $index => $process) {
            $name = $process['name'] ?? "process-{$index}";
            $command = $process['command'] ?? $this->buildNodeCommand($webApp);
            $port = $process['port'] ?? ($webApp->node_port + $index);
            $directory = $process['directory'] ?? "{$webApp->root_path}/current";

            $program = SupervisorProgram::create([
                'server_id' => $webApp->server_id,
                'team_id' => $webApp->team_id,
                'web_app_id' => $webApp->id,
                'name' => "nodejs-{$webApp->id}-{$name}",
                'command' => $command,
                'directory' => $directory,
                'user' => $webApp->system_user ?? 'sitekit',
                'numprocs' => 1,
                'autostart' => true,
                'autorestart' => true,
                'startsecs' => 10,
                'stopwaitsecs' => 30,
                'stdout_logfile' => "{$webApp->root_path}/logs/{$name}.log",
                'stderr_logfile' => "{$webApp->root_path}/logs/{$name}-error.log",
                'environment' => [
                    'NODE_ENV' => 'production',
                    'PORT' => (string) $port,
                    'HOME' => "/home/{$webApp->system_user}",
                ],
                'status' => SupervisorProgram::STATUS_PENDING,
            ]);

            // Dispatch job to create the supervisor config
            $program->dispatchJob('supervisor_create', [
                'config' => $program->generateConfig(),
                'name' => $program->name,
            ]);

            $programs[] = $program;
        }

        // Link the first program to the web app (main process)
        if (!empty($programs)) {
            $webApp->update(['supervisor_program_id' => $programs[0]->id]);
        }

        Log::info("Created supervisor programs for monorepo Node.js app", [
            'web_app_id' => $webApp->id,
            'program_count' => count($programs),
        ]);

        return $programs;
    }

    /**
     * Build the command string for a Node.js app.
     */
    protected function buildNodeCommand(WebApp $webApp): string
    {
        // Use the start_command if set, otherwise use default
        $command = $webApp->start_command ?: $webApp->getDefaultStartCommand();

        // For npm/yarn/pnpm commands, use the full path
        $packageManager = $webApp->package_manager ?? WebApp::PACKAGE_MANAGER_NPM;

        // If the command starts with a package manager, ensure proper environment
        if (preg_match('/^(npm|yarn|pnpm)\s/', $command)) {
            // The command already uses a package manager, use as-is
            return $command;
        }

        // If it's a direct node command (e.g., "node dist/main.js"), use as-is
        if (str_starts_with($command, 'node ')) {
            return $command;
        }

        // Otherwise, assume it's a script name and prefix with package manager
        return "{$packageManager} run {$command}";
    }

    protected function handleWebAppDeleteCallback(array $payload, bool $success): void
    {
        if ($success) {
            $webApp = \App\Models\WebApp::find($payload['web_app_id'] ?? null);
            $webApp?->delete();
        }
    }

    protected function handleSslCallback(array $payload, bool $success, ?string $error): void
    {
        $certificate = \App\Models\SslCertificate::find($payload['certificate_id'] ?? null);
        if (!$certificate) {
            return;
        }

        $webApp = $certificate->webApp;

        if ($success) {
            $certificate->update([
                'status' => \App\Models\SslCertificate::STATUS_ACTIVE,
                'issued_at' => now(),
                'expires_at' => now()->addMonths(3), // Let's Encrypt default
                'error_message' => null,
            ]);
            $certificate->clearError();

            // Update WebApp SSL status
            $webApp?->update([
                'ssl_status' => \App\Models\WebApp::SSL_ACTIVE,
                'ssl_expires_at' => now()->addMonths(3),
            ]);

            // Notify team owner of successful SSL issuance
            $owner = $webApp?->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\SslCertificateIssued($certificate));
            }

            // Dispatch nginx config update with SSL enabled
            if ($webApp) {
                $nginxGen = new \App\Services\ConfigGenerator\NginxConfigGenerator();
                $phpGen = new \App\Services\ConfigGenerator\PhpFpmConfigGenerator();

                AgentJob::create([
                    'server_id' => $webApp->server_id,
                    'team_id' => $webApp->team_id,
                    'type' => 'update_webapp_config',
                    'payload' => [
                        'app_id' => $webApp->id,
                        'domain' => $webApp->domain,
                        'php_version' => $webApp->php_version,
                        'nginx_config' => $nginxGen->generateSSL($webApp),
                        'fpm_config' => $phpGen->generate($webApp),
                        'username' => $webApp->system_user,
                    ],
                ]);
            }
        } else {
            $certificate->update([
                'status' => \App\Models\SslCertificate::STATUS_FAILED,
                'error_message' => $error,
            ]);

            // Record user-friendly error
            if ($error) {
                $certificate->recordError($error);
            }

            // Update WebApp SSL status to failed
            $webApp?->update([
                'ssl_status' => \App\Models\WebApp::SSL_FAILED,
                'error_message' => "SSL: " . ($error ?? 'Certificate issuance failed'),
            ]);

            // Notify team owner of SSL failure
            $owner = $webApp?->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\SslCertificateFailed($certificate, $error ?? 'Certificate issuance failed'));
            }
        }
    }

    protected function handleDatabaseCreateCallback(array $payload, bool $success, ?string $error): void
    {
        $database = \App\Models\Database::find($payload['database_id'] ?? null);
        if (!$database) {
            return;
        }

        if ($success) {
            $database->update(['status' => \App\Models\Database::STATUS_ACTIVE]);
            $database->clearError();

            // Notify team owner of successful database creation
            $owner = $database->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\DatabaseCreated($database));
            }
        } else {
            $database->update([
                'status' => \App\Models\Database::STATUS_FAILED,
                'error_message' => $error,
            ]);

            // Record user-friendly error
            if ($error) {
                $database->recordError($error);
            }

            // Notify team owner of database creation failure
            $owner = $database->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\DatabaseCreationFailed($database, $error ?? 'Database creation failed'));
            }
        }
    }

    protected function handleDatabaseDeleteCallback(array $payload, bool $success): void
    {
        if ($success) {
            $database = \App\Models\Database::find($payload['database_id'] ?? null);
            $database?->delete();
        }
    }

    protected function handleDatabaseBackupCallback(array $payload, bool $success, ?string $error, array $result): void
    {
        $backup = \App\Models\DatabaseBackup::find($payload['backup_id'] ?? null);
        if (!$backup) {
            return;
        }

        if ($success) {
            $backup->update([
                'status' => \App\Models\DatabaseBackup::STATUS_COMPLETED,
                'path' => $result['output'] ?? null, // Agent returns the backup file path
                'size_bytes' => $payload['size_bytes'] ?? null,
                'completed_at' => now(),
            ]);

            // Update database's last_backup_at
            $backup->database->update(['last_backup_at' => now()]);

            // Notify user of successful backup
            $owner = $backup->database->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\BackupCompleted($backup));
            }

            // Upload to cloud storage if configured
            if ($backup->team->hasCloudBackupStorage()) {
                dispatch(function () use ($backup) {
                    $backup->uploadToCloud();
                })->afterResponse();
            }
        } else {
            $backup->update([
                'status' => \App\Models\DatabaseBackup::STATUS_FAILED,
                'error_message' => $error,
                'completed_at' => now(),
            ]);

            // Notify user of failed backup
            $owner = $backup->database->team?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\BackupFailed($backup->database, $error ?? 'Backup failed'));
            }
        }
    }

    protected function handleSupervisorCallback(array $payload, bool $success, ?string $error): void
    {
        $program = \App\Models\SupervisorProgram::find($payload['program_id'] ?? null);
        if (!$program) {
            return;
        }

        if ($success) {
            $program->update(['status' => \App\Models\SupervisorProgram::STATUS_ACTIVE]);
            $program->clearError();
        } else {
            $program->update([
                'status' => \App\Models\SupervisorProgram::STATUS_FAILED,
                'error_message' => $error,
            ]);

            // Record user-friendly error
            if ($error) {
                $program->recordError($error);
            }
        }
    }

    protected function handleSupervisorDeleteCallback(array $payload, bool $success): void
    {
        if ($success) {
            $program = \App\Models\SupervisorProgram::find($payload['program_id'] ?? null);
            $program?->delete();
        }
    }

    protected function handleSupervisorStartCallback(array $payload, bool $success, ?string $error): void
    {
        $program = \App\Models\SupervisorProgram::find($payload['program_id'] ?? null);
        if (!$program) {
            return;
        }

        if ($success) {
            $program->update(['status' => \App\Models\SupervisorProgram::STATUS_ACTIVE]);
            $program->clearError();
        } else {
            $program->update([
                'status' => \App\Models\SupervisorProgram::STATUS_FAILED,
                'error_message' => $error,
            ]);

            // Record user-friendly error
            if ($error) {
                $program->recordError($error);
            }
        }
    }

    protected function handleSupervisorStopCallback(array $payload, bool $success): void
    {
        $program = \App\Models\SupervisorProgram::find($payload['program_id'] ?? null);
        if (!$program) {
            return;
        }

        $program->update(['status' => \App\Models\SupervisorProgram::STATUS_STOPPED]);
    }

    /**
     * Get server configuration for the agent
     */
    public function config(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->get('server');

        return response()->json([
            'server_id' => $server->id,
            'name' => $server->name,
            'heartbeat_interval' => 60,
            'job_poll_interval' => 5,
            'stats_interval' => 60,
        ]);
    }

    protected function handleSshKeyAddCallback(string $serverId, array $payload, bool $success): void
    {
        $keyId = $payload['key_id'] ?? null;
        if (!$keyId) {
            return;
        }

        $sshKey = \App\Models\SshKey::find($keyId);
        if (!$sshKey) {
            return;
        }

        // Update pivot table status
        $sshKey->servers()->updateExistingPivot($serverId, [
            'status' => $success ? 'active' : 'failed',
        ]);
    }

    protected function handleSshKeyRemoveCallback(string $serverId, array $payload, bool $success): void
    {
        $keyId = $payload['key_id'] ?? null;
        if (!$keyId) {
            return;
        }

        $sshKey = \App\Models\SshKey::find($keyId);
        if (!$sshKey) {
            return;
        }

        if ($success) {
            // Remove from pivot table
            $sshKey->servers()->detach($serverId);
        } else {
            // Mark as failed
            $sshKey->servers()->updateExistingPivot($serverId, [
                'status' => 'failed',
            ]);
        }
    }

    protected function handleSshKeySyncCallback(string $serverId, array $payload, bool $success): void
    {
        $keyIds = $payload['key_ids'] ?? [];
        if (empty($keyIds)) {
            return;
        }

        if ($success) {
            // Update all keys as active on this server
            foreach ($keyIds as $keyId) {
                $sshKey = \App\Models\SshKey::find($keyId);
                if ($sshKey) {
                    $sshKey->servers()->syncWithoutDetaching([
                        $serverId => ['status' => 'active'],
                    ]);
                }
            }
        }
    }

    /**
     * Store per-service metrics from heartbeat data.
     */
    protected function storeServiceMetrics(Server $server, array $servicesStatus): void
    {
        // Build a map of service_name => service_id
        $services = $server->services()->get()->keyBy(function ($service) {
            return $service->getServiceNameForSystemd();
        });

        foreach ($servicesStatus as $serviceData) {
            $serviceName = $serviceData['name'] ?? null;
            if (!$serviceName) {
                continue;
            }

            // Find the matching service
            $service = $services->get($serviceName);
            if (!$service) {
                continue;
            }

            // Only store metrics if at least one metric field is provided
            $hasCpu = isset($serviceData['cpu_percent']);
            $hasMemory = isset($serviceData['memory_mb']);
            $hasUptime = isset($serviceData['uptime_seconds']);

            if (!$hasCpu && !$hasMemory && !$hasUptime) {
                continue;
            }

            ServiceStat::create([
                'service_id' => $service->id,
                'cpu_percent' => $serviceData['cpu_percent'] ?? 0,
                'memory_mb' => $serviceData['memory_mb'] ?? 0,
                'uptime_seconds' => $serviceData['uptime_seconds'] ?? 0,
                'recorded_at' => now(),
            ]);
        }
    }

    /**
     * Update supervisor program metrics from heartbeat data.
     */
    protected function updateDaemonMetrics(Server $server, array $daemonsStatus): void
    {
        // Build a map of program_name => program
        $programs = \App\Models\SupervisorProgram::where('server_id', $server->id)
            ->get()
            ->keyBy('name');

        foreach ($daemonsStatus as $daemonData) {
            $name = $daemonData['name'] ?? null;
            if (!$name) {
                continue;
            }

            // Supervisor names may be in format "program:program_00"
            // Try to match by the base name (before the colon)
            $baseName = explode(':', $name)[0];
            $program = $programs->get($name) ?? $programs->get($baseName);

            if (!$program) {
                continue;
            }

            // Map supervisor status to our status
            $status = match (strtoupper($daemonData['status'] ?? '')) {
                'RUNNING' => \App\Models\SupervisorProgram::STATUS_ACTIVE,
                'STOPPED', 'EXITED' => \App\Models\SupervisorProgram::STATUS_STOPPED,
                'STARTING', 'BACKOFF', 'STOPPING' => \App\Models\SupervisorProgram::STATUS_PENDING,
                'FATAL' => \App\Models\SupervisorProgram::STATUS_FAILED,
                default => $program->status,
            };

            // Update program with metrics
            $program->update([
                'status' => $status,
                'cpu_percent' => $daemonData['cpu_percent'] ?? $program->cpu_percent,
                'memory_mb' => $daemonData['memory_mb'] ?? $program->memory_mb,
                'uptime_seconds' => $daemonData['uptime_seconds'] ?? $program->uptime_seconds,
                'metrics_updated_at' => now(),
            ]);
        }
    }

    /**
     * Handle server restore callback.
     * Cleans up all related database records and resets server to pending state.
     */
    protected function handleServerRestoreCallback(\App\Models\AgentJob $job, array $payload, bool $success): void
    {
        $server = $job->server;
        if (!$server) {
            return;
        }

        if ($success) {
            // Delete all related records
            $server->webApps()->delete();
            $server->services()->delete();
            $server->databases()->delete();
            $server->firewallRules()->delete();
            $server->cronJobs()->delete();
            $server->healthMonitors()->delete();
            \App\Models\SupervisorProgram::where('server_id', $server->id)->delete();

            // Detach SSH keys (don't delete the keys themselves)
            $server->sshKeys()->detach();

            // Reset server to pending state with new agent token
            $server->update([
                'status' => Server::STATUS_PENDING,
                'agent_token' => \Illuminate\Support\Str::random(64),
                'last_heartbeat_at' => null,
                'services_status' => null,
                'system_stats' => null,
            ]);

            // Notify the owner
            $owner = $server->team?->owner;
            if ($owner) {
                \Filament\Notifications\Notification::make()
                    ->title('Server Restored')
                    ->body("Server '{$server->name}' has been restored to its original state. You can re-provision it when ready.")
                    ->success()
                    ->sendToDatabase($owner);
            }
        } else {
            // Restore failed - mark server as failed
            $server->update(['status' => Server::STATUS_FAILED]);

            $owner = $server->team?->owner;
            if ($owner) {
                \Filament\Notifications\Notification::make()
                    ->title('Server Restore Failed')
                    ->body("Failed to restore server '{$server->name}'. Please check the server logs.")
                    ->danger()
                    ->sendToDatabase($owner);
            }
        }
    }

    /**
     * Handle provisioning step callback.
     * Updates the step status and checks if all steps are complete.
     */
    protected function handleProvisioningStepCallback(AgentJob $job, bool $success, array $result): void
    {
        $stepId = $job->payload['step_id'] ?? null;
        if (!$stepId) {
            return;
        }

        $step = ServerProvisioningStep::find($stepId);
        if (!$step) {
            return;
        }

        if ($success) {
            $step->markCompleted($result['output'] ?? null, $result['exit_code'] ?? 0);
        } else {
            $step->markFailed(
                $result['error'] ?? 'Step failed',
                $result['output'] ?? null,
                $result['exit_code'] ?? 1
            );
        }

        // Check if all steps are complete and update server status
        $server = $step->server;
        if ($server) {
            $server->refresh();
            $server->checkAndCompleteProvisioning();

            // If provisioning just completed, sync services and notify
            if ($server->isProvisioningComplete()) {
                $server->syncServicesFromHeartbeat();

                $owner = $server->team?->owner;
                if ($owner) {
                    $owner->notify(new \App\Notifications\ServerProvisioned($server));
                }
            }
        }
    }
}
