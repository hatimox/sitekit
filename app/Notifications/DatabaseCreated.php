<?php

namespace App\Notifications;

use App\Models\Database;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class DatabaseCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Database $database
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_DATABASE_CREATED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_DATABASE_CREATED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->success()
            ->subject('Database Created - ' . $this->database->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your database has been successfully created.')
            ->line('**Database:** ' . $this->database->name)
            ->line('**Type:** ' . strtoupper($this->database->type))
            ->line('**Server:** ' . $this->database->server->name)
            ->action('View Database', url('/app/' . $this->database->team_id . '/databases/' . $this->database->id))
            ->line('You can now connect to your database using the credentials provided.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Database Created')
            ->icon('heroicon-o-circle-stack')
            ->success()
            ->body("Database {$this->database->name} has been created on {$this->database->server->name}.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'database_created',
            'database_id' => $this->database->id,
            'name' => $this->database->name,
            'server_name' => $this->database->server->name,
        ];
    }
}
