<?php

namespace App\Filament\Resources\SupervisorProgramResource\Pages;

use App\Filament\Resources\SupervisorProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupervisorPrograms extends ListRecords
{
    protected static string $resource = SupervisorProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
