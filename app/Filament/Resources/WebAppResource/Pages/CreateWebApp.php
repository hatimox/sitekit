<?php

namespace App\Filament\Resources\WebAppResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\WebAppResource;
use App\Models\WebApp;
use App\Services\ConfigGenerator\ApacheConfigGenerator;
use App\Services\ConfigGenerator\NginxConfigGenerator;
use App\Services\ConfigGenerator\PhpFpmConfigGenerator;
use App\Services\DeployKeyGenerator;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWebApp extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = WebAppResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;

        // Generate deploy key pair for Git operations
        $keyGenerator = new DeployKeyGenerator();
        $keyPair = $keyGenerator->generate($data['domain'] ?? 'webapp');

        $data['deploy_private_key'] = $keyPair['private_key'];
        $data['deploy_public_key'] = $keyPair['public_key'];

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var WebApp $app */
        $app = $this->record;

        // Generate configs
        $nginxGen = new NginxConfigGenerator();
        $phpGen = new PhpFpmConfigGenerator();

        $nginxConfig = $nginxGen->generate($app);
        $phpPoolConfig = $phpGen->generate($app);

        // Dispatch job to create web app on server
        $app->dispatchJob('create_webapp', [
            'app_id' => $app->id,
            'domain' => $app->domain,
            'aliases' => $app->aliases ?? [],
            'username' => $app->system_user,
            'root_path' => $app->root_path,
            'public_path' => $app->public_path ?? 'public',
            'php_version' => $app->php_version,
            'app_type' => 'php', // Default to PHP, could be extended for Node, Python, etc.
            'nginx_config' => $nginxConfig,
            'fpm_config' => $phpPoolConfig,
            'deploy_public_key' => $app->deploy_public_key,
        ], priority: 1);

        // If using hybrid mode (nginx_apache), also create Apache vhost
        if ($app->web_server === WebApp::WEB_SERVER_NGINX_APACHE) {
            $apacheGen = new ApacheConfigGenerator();
            $app->dispatchJob('create_apache_vhost', [
                'app_id' => $app->id,
                'domain' => $app->domain,
                'config' => $apacheGen->generate($app),
            ], priority: 2);
        }

        // Update status to creating
        $app->update(['status' => WebApp::STATUS_CREATING]);

        Notification::make()
            ->title('Web App Creation Started')
            ->body("Setting up {$app->domain} on your server...")
            ->info()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
