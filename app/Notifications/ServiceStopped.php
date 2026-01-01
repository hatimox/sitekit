<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ServiceStopped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Service $service
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVICE_STOPPED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVICE_STOPPED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Service Stopped - ' . $this->service->display_name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A service has been stopped on your server.')
            ->line('**Service:** ' . $this->service->display_name)
            ->line('**Server:** ' . $this->service->server->name)
            ->line('**Status:** Stopped')
            ->action('View Service', url('/app/' . $this->service->server->team_id . '/services/' . $this->service->id))
            ->line('The service is no longer running. Start it again when needed.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Service Stopped')
            ->icon('heroicon-o-stop')
            ->warning()
            ->body("Service {$this->service->display_name} was stopped on {$this->service->server->name}.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'service_stopped',
            'service_id' => $this->service->id,
            'service_name' => $this->service->display_name,
            'server_name' => $this->service->server->name,
        ];
    }
}
