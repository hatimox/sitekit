<?php

namespace App\Filament\Resources\HealthMonitorResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\HealthMonitorResource;
use App\Models\HealthMonitor;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateHealthMonitor extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = HealthMonitorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;
        $data['status'] = HealthMonitor::STATUS_PENDING;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
