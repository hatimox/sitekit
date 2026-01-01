<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ServerProvisioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SERVER_PROVISIONED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SERVER_PROVISIONED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->success()
            ->subject('Server Provisioned - ' . $this->server->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your server has been successfully provisioned and is ready to use.')
            ->line('**Server:** ' . $this->server->name)
            ->line('**IP Address:** ' . $this->server->ip_address)
            ->line('**OS:** ' . ($this->server->os_name ?? 'Unknown') . ' ' . ($this->server->os_version ?? ''))
            ->action('View Server', url('/app/' . $this->server->team_id . '/servers/' . $this->server->id))
            ->line('You can now deploy applications to this server.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Server Provisioned')
            ->icon('heroicon-o-server')
            ->success()
            ->body("Server {$this->server->name} ({$this->server->ip_address}) is now ready.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_provisioned',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'ip_address' => $this->server->ip_address,
        ];
    }
}
