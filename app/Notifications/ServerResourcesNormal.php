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

class ServerResourcesNormal extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public Server $server,
        public array $currentMetrics
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_RESOURCES_NORMAL)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_RESOURCES_NORMAL)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $load = round($this->currentMetrics['load'] ?? 0, 2);
        $memory = round($this->currentMetrics['memory'] ?? 0, 1);
        $disk = round($this->currentMetrics['disk'] ?? 0, 1);

        $loadThreshold = $this->server->getEffectiveLoadThreshold();
        $memoryThreshold = $this->server->alert_memory_threshold ?? 90;
        $diskThreshold = $this->server->alert_disk_threshold ?? 90;

        return (new MailMessage)
            ->success()
            ->subject("Server Resources Back to Normal: {$this->server->name}")
            ->greeting('Good News!')
            ->line("Your server **{$this->server->name}** ({$this->server->ip_address}) is now operating within normal resource parameters.")
            ->line('No further action is required at this time.')
            ->line('---')
            ->line('**Current Usage:**')
            ->line("- Load Average: {$load} (threshold: {$loadThreshold})")
            ->line("- Memory Usage: {$memory}% (threshold: {$memoryThreshold}%)")
            ->line("- Disk Usage: {$disk}% (threshold: {$diskThreshold}%)")
            ->line('---')
            ->line('**Optional: Prevent Future Issues**')
            ->line('To help avoid similar resource spikes in the future, consider:')
            ->line('- Reviewing your application performance')
            ->line('- Setting up proactive monitoring')
            ->line('- Cleaning up old logs and temporary files')
            ->action('View Server', url("/app/{$this->server->team_id}/servers/{$this->server->id}"));
    }

    public function toSlack(object $notifiable): array
    {
        $load = round($this->currentMetrics['load'] ?? 0, 2);
        $memory = round($this->currentMetrics['memory'] ?? 0, 1);
        $disk = round($this->currentMetrics['disk'] ?? 0, 1);

        return $this->buildSlackMessage(
            title: "Server Resources Normal: {$this->server->name}",
            text: "Server {$this->server->name} ({$this->server->ip_address}) is back to normal\nLoad: {$load} | Memory: {$memory}% | Disk: {$disk}%",
            color: 'good',
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $load = round($this->currentMetrics['load'] ?? 0, 2);
        $memory = round($this->currentMetrics['memory'] ?? 0, 1);
        $disk = round($this->currentMetrics['disk'] ?? 0, 1);

        return $this->buildDiscordMessage(
            title: "Server Resources Normal: {$this->server->name}",
            description: "Server **{$this->server->name}** ({$this->server->ip_address}) is back to normal\n**Load:** {$load}\n**Memory:** {$memory}%\n**Disk:** {$disk}%",
            color: 0x00FF00,
            url: url("/app/{$this->server->team_id}/servers/{$this->server->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Server Resources Normal')
            ->icon('heroicon-o-check-circle')
            ->success()
            ->body("Server {$this->server->name} resources are back to normal levels.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_resources_normal',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'metrics' => $this->currentMetrics,
        ];
    }
}
