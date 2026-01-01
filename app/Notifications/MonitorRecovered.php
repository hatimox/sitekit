<?php

namespace App\Notifications;

use App\Models\HealthMonitor;
use App\Models\NotificationPreference;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\HasMultiChannelSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class MonitorRecovered extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public HealthMonitor $monitor,
        public ?string $downtime = null
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        if ($notifiable instanceof User) {
            $channels = [];

            if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_HEALTH_MONITOR_RECOVERED)) {
                $channels[] = 'database';
            }

            if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_HEALTH_MONITOR_RECOVERED)) {
                $channels[] = 'mail';
            }

            return $channels;
        }

        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $target = $this->monitor->check_target;
        $type = strtoupper($this->monitor->type);
        $downtime = $this->downtime ?? $this->monitor->last_down_at?->diffForHumans(now(), ['parts' => 2]) ?? 'unknown';

        return (new MailMessage)
            ->success()
            ->subject("Monitor Recovered: {$this->monitor->name}")
            ->greeting('Good News!')
            ->line("Your {$type} monitor **{$this->monitor->name}** has RECOVERED.")
            ->line("Target: {$target}")
            ->line("Downtime: {$downtime}")
            ->action('View Monitor', url("/app/{$this->monitor->team_id}/health-monitors/{$this->monitor->id}"))
            ->line('Your service is back online.');
    }

    public function toSlack(object $notifiable): array
    {
        $target = $this->monitor->check_target;
        $type = strtoupper($this->monitor->type);
        $downtime = $this->downtime ?? $this->monitor->last_down_at?->diffForHumans(now(), ['parts' => 2]) ?? 'unknown';

        return $this->buildSlackMessage(
            title: "Monitor Recovered: {$this->monitor->name}",
            text: "{$type} monitor has RECOVERED\nTarget: {$target}\nDowntime: {$downtime}",
            color: 'good',
            url: url("/app/{$this->monitor->team_id}/health-monitors/{$this->monitor->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $target = $this->monitor->check_target;
        $type = strtoupper($this->monitor->type);
        $downtime = $this->downtime ?? $this->monitor->last_down_at?->diffForHumans(now(), ['parts' => 2]) ?? 'unknown';

        return $this->buildDiscordMessage(
            title: "Monitor Recovered: {$this->monitor->name}",
            description: "{$type} monitor has RECOVERED\n**Target:** {$target}\n**Downtime:** {$downtime}",
            color: 0x00FF00,
            url: url("/app/{$this->monitor->team_id}/health-monitors/{$this->monitor->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        $type = strtoupper($this->monitor->type);
        $downtime = $this->downtime ?? $this->monitor->last_down_at?->diffForHumans(now(), ['parts' => 2]) ?? 'unknown';

        return FilamentNotification::make()
            ->title('Monitor Recovered')
            ->icon('heroicon-o-check-circle')
            ->success()
            ->body("{$type} monitor {$this->monitor->name} has RECOVERED. Downtime: {$downtime}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monitor_recovered',
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
        ];
    }
}
