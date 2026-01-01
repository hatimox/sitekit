<?php

namespace App\Observers;

use App\Models\Service;
use App\Models\WebApp;

class WebAppObserver
{
    /**
     * Handle the WebApp "created" event.
     * Auto-start the PHP-FPM and Apache services if needed.
     */
    public function created(WebApp $webApp): void
    {
        $this->ensurePhpFpmRunning($webApp);
        $this->ensureApacheRunning($webApp);
    }

    /**
     * Handle the WebApp "updated" event.
     * Auto-start the PHP-FPM service if the PHP version changed.
     * Auto-start Apache if web_server changed to nginx_apache.
     */
    public function updated(WebApp $webApp): void
    {
        if ($webApp->wasChanged('php_version')) {
            $this->ensurePhpFpmRunning($webApp);
        }

        if ($webApp->wasChanged('web_server')) {
            $this->ensureApacheRunning($webApp);
        }
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
}
