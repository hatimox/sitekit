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

class ServerHighMemory extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public Server $server,
        public float $currentUsage,
        public float $threshold
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_HIGH_MEMORY)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_HIGH_MEMORY)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalMemory = $this->server->memory_mb ? round($this->server->memory_mb / 1024, 1) . ' GB' : 'unknown';

        return (new MailMessage)
            ->error()
            ->subject("High Memory Usage: {$this->server->name}")
            ->greeting('Memory Alert!')
            ->line("Your server **{$this->server->name}** ({$this->server->ip_address}) is experiencing high memory usage.")
            ->line("**Current Usage:** {$this->currentUsage}%")
            ->line("**Threshold:** {$this->threshold}%")
            ->line("**Total Memory:** {$totalMemory}")
            ->action('View Server', url("/app/{$this->server->team_id}/servers/{$this->server->id}"))
            ->line('Consider checking memory-intensive processes or upgrading your server.');
    }

    public function toSlack(object $notifiable): array
    {
        return $this->buildSlackMessage(
            title: "High Memory Usage: {$this->server->name}",
            text: "Server {$this->server->name} ({$this->server->ip_address}) has high memory usage\nCurrent: {$this->currentUsage}% | Threshold: {$this->threshold}%",
            color: 'danger',
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        return $this->buildDiscordMessage(
            title: "High Memory Usage: {$this->server->name}",
            description: "Server **{$this->server->name}** ({$this->server->ip_address}) has high memory usage\n**Current Usage:** {$this->currentUsage}%\n**Threshold:** {$this->threshold}%",
            color: 0xFF0000,
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('High Memory Usage')
            ->icon('heroicon-o-chart-bar')
            ->danger()
            ->body("Server {$this->server->name} memory at {$this->currentUsage}% (threshold: {$this->threshold}%)")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_high_memory',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'current_usage' => $this->currentUsage,
            'threshold' => $this->threshold,
        ];
    }
}
