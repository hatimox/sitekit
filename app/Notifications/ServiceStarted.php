<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ServiceStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Service $service
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVICE_STARTED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVICE_STARTED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Service Started - ' . $this->service->display_name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A service has been started on your server.')
            ->line('**Service:** ' . $this->service->display_name)
            ->line('**Server:** ' . $this->service->server->name)
            ->line('**Status:** Active')
            ->action('View Service', url('/app/' . $this->service->server->team_id . '/services/' . $this->service->id))
            ->line('The service is now running.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Service Started')
            ->icon('heroicon-o-play')
            ->success()
            ->body("Service {$this->service->display_name} was started on {$this->service->server->name}.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'service_started',
            'service_id' => $this->service->id,
            'service_name' => $this->service->display_name,
            'server_name' => $this->service->server->name,
        ];
    }
}
