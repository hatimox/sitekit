<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ServiceCrashed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Service $service,
        public ?string $errorMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVICE_CRASHED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVICE_CRASHED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Service Crashed - ' . $this->service->display_name)
            ->error()
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A service has crashed on your server and requires attention.')
            ->line('**Service:** ' . $this->service->display_name)
            ->line('**Server:** ' . $this->service->server->name)
            ->line('**Status:** Failed');

        if ($this->errorMessage) {
            $message->line('**Error:** ' . $this->errorMessage);
        }

        return $message
            ->action('View Service', url('/app/' . $this->service->server->team_id . '/services/' . $this->service->id))
            ->line('Please investigate and restart the service if needed.');
    }

    public function toDatabase(object $notifiable): array
    {
        $body = "Service {$this->service->display_name} crashed on {$this->service->server->name}.";
        if ($this->errorMessage) {
            $body .= " Error: " . \Illuminate\Support\Str::limit($this->errorMessage, 100);
        }

        return FilamentNotification::make()
            ->title('Service Crashed')
            ->icon('heroicon-o-exclamation-triangle')
            ->danger()
            ->body($body)
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'service_crashed',
            'service_id' => $this->service->id,
            'service_name' => $this->service->display_name,
            'server_name' => $this->service->server->name,
            'error_message' => $this->errorMessage,
        ];
    }
}
