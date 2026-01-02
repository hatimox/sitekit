<?php

namespace App\Observers;

use App\Models\Service;
use App\Models\SupervisorProgram;
use App\Models\WebApp;
use App\Services\PortAllocationService;
use Illuminate\Support\Facades\Log;

class WebAppObserver
{
    /**
     * Handle the WebApp "created" event.
     * Auto-start required services based on app type.
     */
    public function created(WebApp $webApp): void
    {
        match ($webApp->app_type) {
            WebApp::APP_TYPE_NODEJS => $this->ensureNodeJsServicesRunning($webApp),
            WebApp::APP_TYPE_PHP => $this->ensurePhpServicesRunning($webApp),
            default => null, // Static apps don't need additional services
        };
    }

    /**
     * Handle the WebApp "updated" event.
     * Auto-start services if configuration changed.
     */
    public function updated(WebApp $webApp): void
    {
        // Handle PHP version changes
        if ($webApp->wasChanged('php_version') && $webApp->isPhp()) {
            $this->ensurePhpFpmRunning($webApp);
        }

        // Handle web_server changes
        if ($webApp->wasChanged('web_server')) {
            $this->ensureApacheRunning($webApp);
        }

        // Handle Node.js version changes
        if ($webApp->wasChanged('node_version') && $webApp->isNodeJs()) {
            $this->ensureNodeJsRunning($webApp);
        }
    }

    /**
     * Handle the WebApp "deleting" event.
     * Cleanup supervisor programs and release ports.
     */
    public function deleting(WebApp $webApp): void
    {
        // Cleanup Node.js resources
        if ($webApp->isNodeJs()) {
            $this->cleanupNodeJsResources($webApp);
        }
    }

    /**
     * Ensure PHP services are running.
     */
    protected function ensurePhpServicesRunning(WebApp $webApp): void
    {
        $this->ensurePhpFpmRunning($webApp);
        $this->ensureApacheRunning($webApp);
    }

    /**
     * Ensure Node.js services are running.
     */
    protected function ensureNodeJsServicesRunning(WebApp $webApp): void
    {
        $this->ensureNodeJsRunning($webApp);
        $this->ensureSupervisorRunning($webApp);
    }

    /**
     * Ensure the PHP-FPM service for this web app is running.
     */
    protected function ensurePhpFpmRunning(WebApp $webApp): void
    {
        if (!$webApp->php_version || !$webApp->server_id) {
            return;
        }

        $phpService = Service::where('server_id', $webApp->server_id)
            ->where('type', Service::TYPE_PHP)
            ->where('version', $webApp->php_version)
            ->first();

        // If the PHP service exists and is stopped, start it
        if ($phpService && $phpService->status === Service::STATUS_STOPPED) {
            $phpService->dispatchStart();
        }
    }

    /**
     * Ensure Apache is running if the web app uses nginx-apache hybrid mode.
     */
    protected function ensureApacheRunning(WebApp $webApp): void
    {
        if ($webApp->web_server !== WebApp::WEB_SERVER_NGINX_APACHE || !$webApp->server_id) {
            return;
        }

        $apacheService = Service::where('server_id', $webApp->server_id)
            ->where('type', Service::TYPE_APACHE)
            ->first();

        // If the Apache service exists and is stopped, start it
        if ($apacheService && $apacheService->status === Service::STATUS_STOPPED) {
            $apacheService->dispatchStart();
        }
    }

    /**
     * Ensure Node.js runtime is available on the server.
     */
    protected function ensureNodeJsRunning(WebApp $webApp): void
    {
        if (!$webApp->node_version || !$webApp->server_id) {
            return;
        }

        $nodeService = Service::where('server_id', $webApp->server_id)
            ->where('type', Service::TYPE_NODEJS)
            ->where('version', $webApp->node_version)
            ->first();

        // If the Node.js service exists and is stopped, start it
        if ($nodeService && $nodeService->status === Service::STATUS_STOPPED) {
            $nodeService->dispatchStart();
        }
    }

    /**
     * Ensure Supervisor is running for Node.js process management.
     */
    protected function ensureSupervisorRunning(WebApp $webApp): void
    {
        if (!$webApp->server_id) {
            return;
        }

        $supervisorService = Service::where('server_id', $webApp->server_id)
            ->where('type', Service::TYPE_SUPERVISOR)
            ->first();

        // If the Supervisor service exists and is stopped, start it
        if ($supervisorService && $supervisorService->status === Service::STATUS_STOPPED) {
            $supervisorService->dispatchStart();
        }
    }

    /**
     * Cleanup Node.js resources when the web app is deleted.
     */
    protected function cleanupNodeJsResources(WebApp $webApp): void
    {
        // Release the allocated port
        if ($webApp->node_port) {
            $portService = app(PortAllocationService::class);
            $portService->release($webApp);

            Log::info("Released port {$webApp->node_port} for deleted web app {$webApp->id}");
        }

        // Delete associated supervisor program
        if ($webApp->supervisor_program_id) {
            $program = SupervisorProgram::find($webApp->supervisor_program_id);
            if ($program) {
                // Dispatch job to remove the supervisor program from the server
                $program->dispatchRemove();

                Log::info("Scheduled removal of supervisor program for deleted web app {$webApp->id}");
            }
        }

        // For monorepo apps, cleanup multiple ports
        if (!empty($webApp->node_processes)) {
            $ports = collect($webApp->node_processes)->pluck('port')->filter();
            if ($ports->isNotEmpty()) {
                Log::info("Released monorepo ports for deleted web app {$webApp->id}", [
                    'ports' => $ports->toArray(),
                ]);
            }
        }
    }
}
