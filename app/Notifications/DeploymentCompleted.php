<?php

namespace App\Notifications;

use App\Models\Deployment;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class DeploymentCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_DEPLOYMENT_COMPLETED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_DEPLOYMENT_COMPLETED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $webApp = $this->deployment->webApp;

        return (new MailMessage)
            ->subject('Deployment Successful - ' . $webApp->domain)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your deployment has completed successfully.')
            ->line('**App:** ' . $webApp->name)
            ->line('**Domain:** ' . $webApp->domain)
            ->line('**Commit:** ' . substr($this->deployment->commit_hash ?? 'N/A', 0, 8))
            ->action('View Deployment', url('/app/' . $webApp->team_id . '/web-apps/' . $webApp->id))
            ->line('Your changes are now live!');
    }

    public function toDatabase(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return FilamentNotification::make()
            ->title('Deployment Completed')
            ->icon('heroicon-o-check-circle')
            ->success()
            ->body("Deployment to {$webApp->domain} completed successfully.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        $webApp = $this->deployment->webApp;

        return [
            'type' => 'deployment_completed',
            'deployment_id' => $this->deployment->id,
            'web_app_id' => $webApp->id,
            'domain' => $webApp->domain,
        ];
    }
}
