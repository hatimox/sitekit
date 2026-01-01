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

class MonitorDown extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public HealthMonitor $monitor,
        public ?string $error = null
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        if ($notifiable instanceof User) {
            $channels = [];

            if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_HEALTH_MONITOR_DOWN)) {
                $channels[] = 'database';
            }

            if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_HEALTH_MONITOR_DOWN)) {
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

        return (new MailMessage)
            ->error()
            ->subject("Monitor Down: {$this->monitor->name}")
            ->greeting('Monitor Alert!')
            ->line("Your {$type} monitor **{$this->monitor->name}** is DOWN.")
            ->line("Target: {$target}")
            ->when($this->error, fn ($mail) => $mail->line("Error: {$this->error}"))
            ->line("Failed checks: {$this->monitor->consecutive_failures}")
            ->action('View Monitor', url("/app/{$this->monitor->team_id}/health-monitors/{$this->monitor->id}"))
            ->line('Please investigate the issue as soon as possible.');
    }

    public function toSlack(object $notifiable): array
    {
        $target = $this->monitor->check_target;
        $type = strtoupper($this->monitor->type);
        $error = $this->error ? "\nError: {$this->error}" : '';

        return $this->buildSlackMessage(
            title: "Monitor Down: {$this->monitor->name}",
            text: "{$type} monitor is DOWN\nTarget: {$target}{$error}\nFailed checks: {$this->monitor->consecutive_failures}",
            color: 'danger',
            url: url("/app/{$this->monitor->team_id}/health-monitors/{$this->monitor->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $target = $this->monitor->check_target;
        $type = strtoupper($this->monitor->type);
        $error = $this->error ? "\n**Error:** {$this->error}" : '';

        return $this->buildDiscordMessage(
            title: "Monitor Down: {$this->monitor->name}",
            description: "{$type} monitor is DOWN\n**Target:** {$target}{$error}\n**Failed checks:** {$this->monitor->consecutive_failures}",
            color: 0xFF0000,
            url: url("/app/{$this->monitor->team_id}/health-monitors/{$this->monitor->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        $type = strtoupper($this->monitor->type);
        $error = $this->error ? " Error: {$this->error}" : '';

        return FilamentNotification::make()
            ->title('Monitor Down')
            ->icon('heroicon-o-exclamation-triangle')
            ->danger()
            ->body("{$type} monitor {$this->monitor->name} is DOWN.{$error}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monitor_down',
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
            'monitor_type' => $this->monitor->type,
            'error' => $this->error,
        ];
    }
}
