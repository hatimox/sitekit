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

class DeploymentFailed extends Notification implements ShouldQueue
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

            if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_DEPLOYMENT_FAILED)) {
                $channels[] = 'database';
            }

            if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_DEPLOYMENT_FAILED)) {
                $channels[] = 'mail';
            }

            return $channels;
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $webApp = $this->deployment->webApp;
        $commitMessage = $this->deployment->commit_message ?? 'Unknown';
        $error = $this->deployment->error ?? 'Unknown error';

        return (new MailMessage)
            ->error()
            ->subject("Deployment Failed: {$webApp->name}")
            ->greeting('Deployment Failed!')
            ->line("A deployment for **{$webApp->name}** ({$webApp->domain}) has failed.")
            ->line("Branch: {$this->deployment->branch}")
            ->line("Commit: {$this->deployment->commit_hash}")
            ->line("Message: {$commitMessage}")
            ->line("Error: {$error}")
            ->action('View Deployment', url("/app/{$webApp->team_id}/web-apps/{$webApp->id}"))
            ->line('Please check the deployment logs for more details.');
    }

    public function toSlack(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;
        $error = $this->deployment->error ?? 'Unknown error';

        return $this->buildSlackMessage(
            title: "Deployment Failed: {$webApp->name}",
            text: "Deployment for {$webApp->domain} has failed\nBranch: {$this->deployment->branch}\nCommit: {$this->deployment->commit_hash}\nError: {$error}",
            color: 'danger',
            url: url("/app/{$webApp->team_id}/web-apps/{$webApp->id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;
        $error = $this->deployment->error ?? 'Unknown error';

        return $this->buildDiscordMessage(
            title: "Deployment Failed: {$webApp->name}",
            description: "Deployment for **{$webApp->domain}** has failed\n**Branch:** {$this->deployment->branch}\n**Commit:** {$this->deployment->commit_hash}\n**Error:** {$error}",
            color: 0xFF0000,
            url: url("/app/{$webApp->team_id}/web-apps/{$webApp->id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return FilamentNotification::make()
            ->title('Deployment Failed')
            ->icon('heroicon-o-x-circle')
            ->danger()
            ->body("Deployment for {$webApp->name} ({$webApp->domain}) has failed.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return [
            'type' => 'deployment_failed',
            'deployment_id' => $this->deployment->id,
            'web_app_name' => $webApp->name,
            'branch' => $this->deployment->branch,
            'commit_hash' => $this->deployment->commit_hash,
        ];
    }
}
