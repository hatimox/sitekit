<?php

namespace App\Notifications;

use App\Models\Database;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class DatabaseCreationFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Database $database,
        public string $error = ''
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_DATABASE_FAILED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_DATABASE_FAILED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Database Creation Failed - ' . $this->database->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Database creation has failed.')
            ->line('**Database:** ' . $this->database->name)
            ->line('**Server:** ' . $this->database->server->name)
            ->when($this->error, fn ($mail) => $mail->line('**Error:** ' . $this->error))
            ->action('View Server', url('/app/' . $this->database->team_id . '/servers/' . $this->database->server_id))
            ->line('Please check the server logs for more details.');
    }

    public function toDatabase(object $notifiable): array
    {
        $error = $this->error ? " Error: {$this->error}" : '';

        return FilamentNotification::make()
            ->title('Database Creation Failed')
            ->icon('heroicon-o-circle-stack')
            ->danger()
            ->body("Database {$this->database->name} failed to create on {$this->database->server->name}.{$error}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'database_creation_failed',
            'database_id' => $this->database->id,
            'database_name' => $this->database->name,
            'server_name' => $this->database->server->name,
            'error' => $this->error,
        ];
    }
}
