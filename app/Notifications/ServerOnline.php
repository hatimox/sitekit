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

class ServerOnline extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public Server $server,
        public string $downtime = 'unknown'
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_ONLINE)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_ONLINE)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->success()
            ->subject("Server Back Online: {$this->server->name}")
            ->greeting('Good News!')
            ->line("Your server **{$this->server->name}** ({$this->server->ip_address}) is back online and responding normally.")
            ->line("Downtime duration: {$this->downtime}")
            ->action('View Server', url("/app/{$this->server->team_id}/servers/{$this->server->id}"))
            ->line('Your server is now connected and receiving heartbeats.');
    }

    public function toSlack(object $notifiable): array
    {
        return $this->buildSlackMessage(
            title: "Server Back Online: {$this->server->name}",
            text: "Server {$this->server->name} ({$this->server->ip_address}) is back online\nDowntime: {$this->downtime}",
            color: 'good',
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        return $this->buildDiscordMessage(
            title: "Server Back Online: {$this->server->name}",
            description: "Server **{$this->server->name}** ({$this->server->ip_address}) is back online\n**Downtime:** {$this->downtime}",
            color: 0x00FF00,
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Server Back Online')
            ->icon('heroicon-o-server')
            ->success()
            ->body("Server {$this->server->name} ({$this->server->ip_address}) is back online. Downtime: {$this->downtime}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_online',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'downtime' => $this->downtime,
        ];
    }
}
