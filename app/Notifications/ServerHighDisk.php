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

class ServerHighDisk extends Notification implements ShouldQueue
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

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_HIGH_DISK)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_HIGH_DISK)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalDisk = $this->server->disk_gb ? $this->server->disk_gb . ' GB' : 'unknown';

        return (new MailMessage)
            ->error()
            ->subject("High Disk Usage: {$this->server->name}")
            ->greeting('Disk Space Alert!')
            ->line("Your server **{$this->server->name}** ({$this->server->ip_address}) is running low on disk space.")
            ->line("**Current Usage:** {$this->currentUsage}%")
            ->line("**Threshold:** {$this->threshold}%")
            ->line("**Total Disk:** {$totalDisk}")
            ->action('View Server', url("/app/{$this->server->team_id}/servers/{$this->server->id}"))
            ->line('Consider cleaning up old logs, backups, or expanding your disk space.');
    }

    public function toSlack(object $notifiable): array
    {
        return $this->buildSlackMessage(
            title: "High Disk Usage: {$this->server->name}",
            text: "Server {$this->server->name} ({$this->server->ip_address}) has high disk usage\nCurrent: {$this->currentUsage}% | Threshold: {$this->threshold}%",
            color: 'danger',
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        return $this->buildDiscordMessage(
            title: "High Disk Usage: {$this->server->name}",
            description: "Server **{$this->server->name}** ({$this->server->ip_address}) has high disk usage\n**Current Usage:** {$this->currentUsage}%\n**Threshold:** {$this->threshold}%",
            color: 0xFF0000,
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('High Disk Usage')
            ->icon('heroicon-o-circle-stack')
            ->danger()
            ->body("Server {$this->server->name} disk at {$this->currentUsage}% (threshold: {$this->threshold}%)")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_high_disk',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'current_usage' => $this->currentUsage,
            'threshold' => $this->threshold,
        ];
    }
}
