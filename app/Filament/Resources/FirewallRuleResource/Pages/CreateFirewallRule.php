<?php

namespace App\Filament\Resources\FirewallRuleResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\FirewallRuleResource;
use App\Services\FirewallSafetyService;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFirewallRule extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = FirewallRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;
        $data['is_active'] = false; // Will be activated by safety service

        return $data;
    }

    protected function afterCreate(): void
    {
        $rule = $this->record;
        $safetyService = app(FirewallSafetyService::class);

        // Apply rule with safety checks
        $result = $safetyService->applyWithSafety($rule);

        if ($result['requires_confirmation']) {
            $timeout = $result['timeout_seconds'];
            $confirmUrl = $result['confirmation_url'];

            Notification::make()
                ->title('Firewall Rule Requires Confirmation')
                ->body("This rule affects critical ports. Confirm within {$timeout} seconds or it will be automatically rolled back.")
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('confirm')
                        ->label('Confirm Rule Works')
                        ->url($confirmUrl)
                        ->openUrlInNewTab(false)
                        ->button()
                        ->color('success'),
                ])
                ->send();
        } else {
            Notification::make()
                ->title('Firewall Rule Applied')
                ->body('The rule has been applied to your server.')
                ->success()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
