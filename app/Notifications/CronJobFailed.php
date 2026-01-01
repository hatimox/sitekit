<?php

namespace App\Notifications;

use App\Models\CronJob;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class CronJobFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CronJob $cronJob,
        public string $error = '',
        public ?string $output = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_CRON_JOB_FAILED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_CRON_JOB_FAILED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Cron Job Failed - ' . $this->cronJob->command)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A scheduled cron job has failed.')
            ->line('**Command:** `' . $this->cronJob->command . '`')
            ->line('**Schedule:** ' . $this->cronJob->schedule)
            ->when($this->error, fn ($mail) => $mail->line('**Error:** ' . $this->error))
            ->when($this->output, fn ($mail) => $mail->line('**Output:** ' . substr($this->output, 0, 500)))
            ->action('View Cron Jobs', url('/app/' . $this->cronJob->team_id . '/cron-jobs/' . $this->cronJob->id))
            ->line('Please review and fix the cron job configuration.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Cron Job Failed')
            ->icon('heroicon-o-clock')
            ->danger()
            ->body("Cron job '{$this->cronJob->command}' failed. {$this->error}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'cron_job_failed',
            'cron_job_id' => $this->cronJob->id,
            'command' => $this->cronJob->command,
            'error' => $this->error,
        ];
    }
}
