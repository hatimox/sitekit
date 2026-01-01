<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\WebApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class WebAppCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WebApp $webApp
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_WEBAPP_CREATED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_WEBAPP_CREATED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->success()
            ->subject('Web App Created - ' . $this->webApp->domain)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your web application has been successfully created.')
            ->line('**Domain:** ' . $this->webApp->domain)
            ->line('**PHP Version:** ' . $this->webApp->php_version)
            ->line('**Server:** ' . $this->webApp->server->name)
            ->action('View Web App', url('/app/' . $this->webApp->team_id . '/web-apps/' . $this->webApp->id))
            ->line('You can now deploy your code to this application.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Web App Created')
            ->icon('heroicon-o-globe-alt')
            ->success()
            ->body("Web app {$this->webApp->domain} has been created on {$this->webApp->server->name}.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'webapp_created',
            'webapp_id' => $this->webApp->id,
            'domain' => $this->webApp->domain,
            'server_name' => $this->webApp->server->name,
        ];
    }
}
