<?php

namespace App\Filament\Resources\AgentJobResource\Pages;

use App\Filament\Resources\AgentJobResource;
use App\Models\AgentJob;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAgentJob extends ViewRecord
{
    protected static string $resource = AgentJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Cancel Job')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, [
                    AgentJob::STATUS_PENDING,
                    AgentJob::STATUS_QUEUED,
                    AgentJob::STATUS_RUNNING,
                ]))
                ->requiresConfirmation()
                ->action(fn () => $this->record->update(['status' => AgentJob::STATUS_CANCELLED])),
            Actions\Action::make('retry')
                ->label('Retry Job')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->isFailed() && $this->record->canRetry())
                ->action(function () {
                    $this->record->update([
                        'status' => AgentJob::STATUS_PENDING,
                        'retry_count' => $this->record->retry_count + 1,
                        'error' => null,
                        'output' => null,
                        'exit_code' => null,
                        'started_at' => null,
                        'completed_at' => null,
                    ]);
                }),
        ];
    }
}
