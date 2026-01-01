<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\SslCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class SslCertificateIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SslCertificate $certificate
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SSL_ISSUED)) {
            $channels[] = 'database';
        }

        if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SSL_ISSUED)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SSL Certificate Issued - ' . $this->certificate->domain)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your SSL certificate has been successfully issued.')
            ->line('**Domain:** ' . $this->certificate->domain)
            ->line('**Expires:** ' . $this->certificate->expires_at?->format('M d, Y'))
            ->action('View Certificate', url('/app/' . $this->certificate->team_id . '/web-apps/' . $this->certificate->web_app_id))
            ->line('Your website is now secure with HTTPS.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('SSL Certificate Issued')
            ->icon('heroicon-o-lock-closed')
            ->success()
            ->body("SSL certificate for {$this->certificate->domain} has been issued successfully.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ssl_certificate_issued',
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'expires_at' => $this->certificate->expires_at?->toISOString(),
        ];
    }
}
