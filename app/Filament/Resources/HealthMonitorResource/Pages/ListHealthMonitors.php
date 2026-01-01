<?php

namespace App\Filament\Resources\HealthMonitorResource\Pages;

use App\Filament\Resources\HealthMonitorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHealthMonitors extends ListRecords
{
    protected static string $resource = HealthMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
