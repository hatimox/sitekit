<?php

namespace App\Filament\Resources\SupervisorProgramResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\SupervisorProgramResource;
use App\Models\SupervisorProgram;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSupervisorProgram extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = SupervisorProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var SupervisorProgram $program */
        $program = $this->record;

        $program->dispatchJob('supervisor_create', [
            'program_id' => $program->id,
            'name' => $program->name,
            'config' => $program->generateConfig(),
        ], priority: 1);

        Notification::make()
            ->title('Worker Creation Started')
            ->body("Setting up {$program->name} on your server...")
            ->info()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
