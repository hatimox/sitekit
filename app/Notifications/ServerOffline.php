<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Server;
use App\Models\Team;
use App\Notifications\Concerns\HasMultiChannelSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ServerOffline extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public Server $server,
        public string $reason = 'No heartbeat received'
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_OFFLINE)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_OFFLINE)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Server Offline: {$this->server->name}")
            ->greeting('Server Alert!')
            ->line("Your server **{$this->server->name}** ({$this->server->ip_address}) appears to be offline.")
            ->line("Reason: {$this->reason}")
            ->line("Last seen: " . ($this->server->last_heartbeat_at?->diffForHumans() ?? 'Never'))
            ->action('View Server', url("/app/{$this->server->team_id}/servers/{$this->server->id}"))
            ->line('Please check your server status and ensure the SiteKit agent is running.');
    }

    public function toSlack(object $notifiable): array
    {
        $lastSeen = $this->server->last_heartbeat_at?->diffForHumans() ?? 'Never';

        return $this->buildSlackMessage(
            title: "Server Offline: {$this->server->name}",
            text: "Server {$this->server->name} ({$this->server->ip_address}) is offline\nReason: {$this->reason}\nLast seen: {$lastSeen}",
            color: 'danger',
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $lastSeen = $this->server->last_heartbeat_at?->diffForHumans() ?? 'Never';

        return $this->buildDiscordMessage(
            title: "Server Offline: {$this->server->name}",
            description: "Server **{$this->server->name}** ({$this->server->ip_address}) is offline\n**Reason:** {$this->reason}\n**Last seen:** {$lastSeen}",
            color: 0xFF0000,
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Server Offline')
            ->icon('heroicon-o-server')
            ->danger()
            ->body("Server {$this->server->name} ({$this->server->ip_address}) is offline. {$this->reason}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_offline',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'reason' => $this->reason,
        ];
    }
}
