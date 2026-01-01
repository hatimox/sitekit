<?php

namespace App\Notifications;

use App\Models\DatabaseBackup;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class BackupCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DatabaseBackup $backup
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_BACKUP_COMPLETED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_BACKUP_COMPLETED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $size = $this->backup->size_bytes
            ? round($this->backup->size_bytes / 1024 / 1024, 2) . ' MB'
            : 'Unknown';

        return (new MailMessage)
            ->success()
            ->subject('Backup Completed - ' . $this->backup->database->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your database backup has completed successfully.')
            ->line('**Database:** ' . $this->backup->database->name)
            ->line('**Size:** ' . $size)
            ->line('**Created:** ' . $this->backup->created_at->format('M d, Y H:i'))
            ->action('View Backups', url('/app/' . $this->backup->database->team_id . '/databases/' . $this->backup->database_id))
            ->line('Your data is safely backed up.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Backup Completed')
            ->icon('heroicon-o-circle-stack')
            ->success()
            ->body("Database backup for {$this->backup->database->name} completed successfully.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'backup_completed',
            'backup_id' => $this->backup->id,
            'database_name' => $this->backup->database->name,
        ];
    }
}
