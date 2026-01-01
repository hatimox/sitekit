<?php

namespace App\Filament\Resources\SshKeyResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\SshKeyResource;
use App\Models\AgentJob;
use App\Models\Server;
use App\Models\SshKey;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSshKey extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = SshKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var SshKey $sshKey */
        $sshKey = $this->record;
        $team = Filament::getTenant();

        // Get selected servers from form (optional deployment)
        $deployServerIds = $this->data['deploy_servers'] ?? [];
        $targetUser = $this->data['target_user'] ?? 'sitekit';

        // If no servers selected, skip deployment
        if (empty($deployServerIds)) {
            Notification::make()
                ->title('SSH Key Created')
                ->body('Key saved. Use "Deploy to Server" action to add it to servers.')
                ->success()
                ->send();
            return;
        }

        // Get selected active servers for this team
        $servers = Server::where('team_id', $team->id)
            ->where('status', Server::STATUS_ACTIVE)
            ->whereIn('id', $deployServerIds)
            ->get();

        $jobCount = 0;
        foreach ($servers as $server) {
            // Attach SSH key to server with pending status
            $sshKey->servers()->attach($server->id, ['status' => 'pending']);

            // Dispatch job to add the key
            AgentJob::create([
                'server_id' => $server->id,
                'team_id' => $team->id,
                'type' => 'ssh_key_add',
                'payload' => [
                    'key_id' => $sshKey->id,
                    'public_key' => $sshKey->public_key,
                    'username' => $targetUser,
                ],
            ]);
            $jobCount++;
        }

        if ($jobCount > 0) {
            Notification::make()
                ->title('SSH Key Deployment Started')
                ->body("Deploying key to {$jobCount} server(s) for user '{$targetUser}'...")
                ->info()
                ->send();
        }
    }
}
