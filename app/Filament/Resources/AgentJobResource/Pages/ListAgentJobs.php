<?php

namespace App\Filament\Resources\AgentJobResource\Pages;

use App\Filament\Resources\AgentJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgentJobs extends ListRecords
{
    protected static string $resource = AgentJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - jobs are created programmatically
        ];
    }
}
