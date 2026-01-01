<?php

namespace App\Filament\Resources\SupervisorProgramResource\Pages;

use App\Filament\Resources\SupervisorProgramResource;
use App\Models\SupervisorProgram;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupervisorProgram extends EditRecord
{
    protected static string $resource = SupervisorProgramResource::class;

    protected function afterSave(): void
    {
        /** @var SupervisorProgram $program */
        $program = $this->record;

        $program->dispatchJob('supervisor_update', [
            'program_id' => $program->id,
            'name' => $program->name,
            'config' => $program->generateConfig(),
        ]);

        Notification::make()
            ->title('Worker Updated')
            ->body("Updating configuration for {$program->name}...")
            ->info()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (SupervisorProgram $record) {
                    $record->dispatchJob('supervisor_delete', [
                        'program_id' => $record->id,
                        'name' => $record->name,
                    ]);
                }),
        ];
    }
}
