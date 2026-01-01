<?php

namespace App\Filament\Concerns;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

trait RequiresActiveServer
{
    public function bootRequiresActiveServer(): void
    {
        $this->checkServerPrerequisite();
    }

    protected function checkServerPrerequisite(): void
    {
        $team = Filament::getTenant();

        if (!$team) {
            return;
        }

        $hasActiveServer = Server::where('team_id', $team->id)
            ->where('status', 'active')
            ->exists();

        if (!$hasActiveServer) {
            Notification::make()
                ->title('No Active Servers')
                ->body('You need to connect a server before you can create this resource. Please connect a server first.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(route('filament.app.resources.servers.index', ['tenant' => $team->id]));
        }
    }
}
