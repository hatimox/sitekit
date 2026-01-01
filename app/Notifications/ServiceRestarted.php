<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ServiceRestarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Service $service,
        public string $action = 'restarted'
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVICE_RESTARTED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVICE_RESTARTED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Service ' . ucfirst($this->action) . ' - ' . $this->service->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line("A service has been {$this->action} on your server.")
            ->line('**Service:** ' . $this->service->name)
            ->line('**Server:** ' . $this->service->server->name)
            ->line('**Status:** ' . ucfirst($this->service->status))
            ->action('View Services', url('/app/' . $this->service->server->team_id . '/servers/' . $this->service->server_id))
            ->line('No further action is required.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Service ' . ucfirst($this->action))
            ->icon('heroicon-o-cog')
            ->info()
            ->body("Service {$this->service->name} was {$this->action} on {$this->service->server->name}.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'service_restarted',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'server_name' => $this->service->server->name,
            'action' => $this->action,
        ];
    }
}
