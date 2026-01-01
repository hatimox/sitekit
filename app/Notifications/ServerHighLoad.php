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

class ServerHighLoad extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public Server $server,
        public float $currentLoad,
        public float $threshold
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_HIGH_LOAD)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_HIGH_LOAD)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cpuCount = $this->server->cpu_count ?? 'unknown';

        return (new MailMessage)
            ->error()
            ->subject("High Server Load: {$this->server->name}")
            ->greeting('Server Load Alert!')
            ->line("Your server **{$this->server->name}** ({$this->server->ip_address}) is experiencing high load.")
            ->line("**Current Load:** {$this->currentLoad}")
            ->line("**Threshold:** {$this->threshold}")
            ->line("**CPU Cores:** {$cpuCount}")
            ->action('View Server', url("/app/{$this->server->team_id}/servers/{$this->server->id}"))
            ->line('Consider checking running processes and optimizing your applications.');
    }

    public function toSlack(object $notifiable): array
    {
        return $this->buildSlackMessage(
            title: "High Server Load: {$this->server->name}",
            text: "Server {$this->server->name} ({$this->server->ip_address}) has high load\nCurrent: {$this->currentLoad} | Threshold: {$this->threshold}",
            color: 'danger',
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        return $this->buildDiscordMessage(
            title: "High Server Load: {$this->server->name}",
            description: "Server **{$this->server->name}** ({$this->server->ip_address}) has high load\n**Current Load:** {$this->currentLoad}\n**Threshold:** {$this->threshold}",
            color: 0xFF0000,
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('High Server Load')
            ->icon('heroicon-o-cpu-chip')
            ->danger()
            ->body("Server {$this->server->name} load is {$this->currentLoad} (threshold: {$this->threshold})")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_high_load',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'current_load' => $this->currentLoad,
            'threshold' => $this->threshold,
        ];
    }
}
