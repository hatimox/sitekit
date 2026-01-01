<?php

namespace App\Notifications;

use App\Models\Database;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class BackupFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Database $database,
        public string $error = ''
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_BACKUP_FAILED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_BACKUP_FAILED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Backup Failed - ' . $this->database->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A database backup has failed.')
            ->line('**Database:** ' . $this->database->name)
            ->line('**Server:** ' . $this->database->server->name)
            ->when($this->error, fn ($mail) => $mail->line('**Error:** ' . $this->error))
            ->action('View Database', url('/app/' . $this->database->team_id . '/databases/' . $this->database->id))
            ->line('Please check your database configuration and try again.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Backup Failed')
            ->icon('heroicon-o-circle-stack')
            ->danger()
            ->body("Database backup for {$this->database->name} failed. {$this->error}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'backup_failed',
            'database_id' => $this->database->id,
            'database_name' => $this->database->name,
            'error' => $this->error,
        ];
    }
}
