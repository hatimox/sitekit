<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\WebApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class WebAppCreationFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WebApp $webApp,
        public string $error = ''
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_WEBAPP_FAILED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_WEBAPP_FAILED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Web App Creation Failed - ' . $this->webApp->domain)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Web application creation has failed.')
            ->line('**Domain:** ' . $this->webApp->domain)
            ->line('**Server:** ' . $this->webApp->server->name)
            ->when($this->error, fn ($mail) => $mail->line('**Error:** ' . $this->error))
            ->action('View Server', url('/app/' . $this->webApp->team_id . '/servers/' . $this->webApp->server_id))
            ->line('Please check the server logs for more details.');
    }

    public function toDatabase(object $notifiable): array
    {
        $error = $this->error ? " Error: {$this->error}" : '';

        return FilamentNotification::make()
            ->title('Web App Creation Failed')
            ->icon('heroicon-o-globe-alt')
            ->danger()
            ->body("Web app {$this->webApp->domain} failed to create on {$this->webApp->server->name}.{$error}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'webapp_creation_failed',
            'webapp_id' => $this->webApp->id,
            'domain' => $this->webApp->domain,
            'server_name' => $this->webApp->server->name,
            'error' => $this->error,
        ];
    }
}
