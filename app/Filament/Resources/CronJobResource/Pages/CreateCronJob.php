<?php

namespace App\Filament\Resources\CronJobResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\CronJobResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateCronJob extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = CronJobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncToServer();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
