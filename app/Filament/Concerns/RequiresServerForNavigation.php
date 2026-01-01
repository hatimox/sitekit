<?php

namespace App\Filament\Concerns;

use App\Models\Server;
use Filament\Facades\Filament;

trait RequiresServerForNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        $team = Filament::getTenant();

        if (!$team) {
            return false;
        }

        return Server::where('team_id', $team->id)
            ->where('status', 'active')
            ->exists();
    }
}
