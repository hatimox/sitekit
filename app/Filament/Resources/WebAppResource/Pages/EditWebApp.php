<?php

namespace App\Filament\Resources\WebAppResource\Pages;

use App\Filament\Resources\WebAppResource;
use App\Services\Config\NginxConfigGenerator;
use App\Services\Config\PhpFpmConfigGenerator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWebApp extends EditRecord
{
    protected static string $resource = WebAppResource::class;

    protected ?string $oldPhpVersion = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Store the old PHP version before editing
        $this->oldPhpVersion = $this->record->php_version;

        return $data;
    }

    protected function afterSave(): void
    {
        // If PHP version changed, dispatch update job with old version
        if ($this->oldPhpVersion && $this->oldPhpVersion !== $this->record->php_version) {
            $nginxGen = new NginxConfigGenerator();
            $phpGen = new PhpFpmConfigGenerator();

            $this->record->dispatchJob('update_webapp_config', [
                'app_id' => $this->record->id,
                'domain' => $this->record->domain,
                'username' => $this->record->system_user,
                'nginx_config' => $this->record->hasSSL()
                    ? $nginxGen->generateSSL($this->record)
                    : $nginxGen->generate($this->record),
                'fpm_config' => $phpGen->generate($this->record),
                'php_version' => $this->record->php_version,
                'old_php_version' => $this->oldPhpVersion,
            ]);

            Notification::make()
                ->title('PHP Version Changed')
                ->body("Switching from PHP {$this->oldPhpVersion} to PHP {$this->record->php_version}. Configuration is being updated.")
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
