<?php

namespace App\Filament\Resources\WebAppResource\Pages;

use App\Filament\Resources\WebAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWebApps extends ListRecords
{
    protected static string $resource = WebAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
