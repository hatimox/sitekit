<?php

namespace App\Notifications;

use App\Models\Deployment;
use App\Models\NotificationPreference;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\HasMultiChannelSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class DeploymentSuccessful extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        if ($notifiable instanceof User) {
            $channels = [];

            if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_DEPLOYMENT_COMPLETED)) {
                $channels[] = 'database';
            }

            if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_DEPLOYMENT_COMPLETED)) {
                $channels[] = 'mail';
            }

            return $channels;
        }

        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $webApp = $this->deployment->webApp;
        $duration = $this->deployment->started_at && $this->deployment->finished_at
            ? $this->deployment->started_at->diffForHumans($this->deployment->finished_at, ['parts' => 2])
            : 'Unknown';

        return (new MailMessage)
            ->success()
            ->subject("Deployment Successful: {$webApp->name}")
            ->greeting('Deployment Complete!')
            ->line("A deployment for **{$webApp->name}** has completed successfully.")
            ->line("Branch: {$this->deployment->branch}")
            ->line("Commit: {$this->deployment->commit_hash}")
            ->line("Duration: {$duration}")
            ->action('View Site', "https://{$webApp->domain}")
            ->line('Your changes are now live!');
    }

    public function toSlack(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return $this->buildSlackMessage(
            title: "Deployment Successful: {$webApp->name}",
            text: "Deployment for {$webApp->domain} completed successfully\nBranch: {$this->deployment->branch}\nCommit: {$this->deployment->commit_hash}",
            color: 'good',
            url: "https://{$webApp->domain}"
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return $this->buildDiscordMessage(
            title: "Deployment Successful: {$webApp->name}",
            description: "Deployment for **{$webApp->domain}** completed successfully\n**Branch:** {$this->deployment->branch}\n**Commit:** {$this->deployment->commit_hash}",
            color: 0x00FF00,
            url: "https://{$webApp->domain}"
        );
    }

    public function toDatabase(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return FilamentNotification::make()
            ->title('Deployment Successful')
            ->icon('heroicon-o-check-circle')
            ->success()
            ->body("Deployment for {$webApp->name} ({$webApp->domain}) completed successfully.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return [
            'type' => 'deployment_successful',
            'deployment_id' => $this->deployment->id,
            'web_app_name' => $webApp->name,
            'domain' => $webApp->domain,
        ];
    }
}
