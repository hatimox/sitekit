<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\SslCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class SslCertificateFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SslCertificate $certificate,
        public string $error = ''
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SSL_FAILED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SSL_FAILED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('SSL Certificate Failed - ' . $this->certificate->domain)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('SSL certificate issuance has failed for your domain.')
            ->line('**Domain:** ' . $this->certificate->domain)
            ->when($this->error, fn ($mail) => $mail->line('**Error:** ' . $this->error))
            ->action('View Web App', url('/app/' . $this->certificate->team_id . '/web-apps/' . $this->certificate->web_app_id))
            ->line('Please check your domain DNS settings and try again.');
    }

    public function toDatabase(object $notifiable): array
    {
        $error = $this->error ? " Error: {$this->error}" : '';

        return FilamentNotification::make()
            ->title('SSL Certificate Failed')
            ->icon('heroicon-o-lock-open')
            ->danger()
            ->body("SSL certificate for {$this->certificate->domain} failed to issue.{$error}")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ssl_certificate_failed',
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'error' => $this->error,
        ];
    }
}
