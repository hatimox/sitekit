<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\SslCertificate;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\HasMultiChannelSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class SslExpiringWarning extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public SslCertificate $certificate
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        if ($notifiable instanceof User) {
            $channels = [];

            if (NotificationPreference::shouldSendInApp($notifiable, NotificationPreference::EVENT_SSL_EXPIRING)) {
                $channels[] = 'database';
            }

            if (NotificationPreference::shouldSendEmail($notifiable, NotificationPreference::EVENT_SSL_EXPIRING)) {
                $channels[] = 'mail';
            }

            return $channels;
        }

        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->certificate->getDaysUntilExpiry();
        $domain = $this->certificate->domain;

        return (new MailMessage)
            ->warning()
            ->subject("SSL Certificate Expiring: {$domain}")
            ->greeting('SSL Certificate Warning!')
            ->line("The SSL certificate for **{$domain}** will expire in **{$days} days**.")
            ->line("Expiry date: " . $this->certificate->expires_at->format('F j, Y'))
            ->when(
                $this->certificate->type === SslCertificate::TYPE_LETSENCRYPT,
                fn ($mail) => $mail->line("Auto-renewal is enabled for Let's Encrypt certificates."),
                fn ($mail) => $mail->line("Please upload a new certificate before expiry.")
            )
            ->action('Manage SSL', url("/app/{$this->certificate->webApp->team_id}/web-apps/{$this->certificate->web_app_id}"))
            ->line('Expired certificates will cause browser security warnings for your visitors.');
    }

    public function toSlack(object $notifiable): array
    {
        $days = $this->certificate->getDaysUntilExpiry();
        $domain = $this->certificate->domain;
        $expiryDate = $this->certificate->expires_at->format('F j, Y');

        return $this->buildSlackMessage(
            title: "SSL Certificate Expiring: {$domain}",
            text: "Certificate will expire in {$days} days\nExpiry date: {$expiryDate}",
            color: 'warning',
            url: url("/app/{$this->certificate->webApp->team_id}/web-apps/{$this->certificate->web_app_id}")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $days = $this->certificate->getDaysUntilExpiry();
        $domain = $this->certificate->domain;
        $expiryDate = $this->certificate->expires_at->format('F j, Y');

        return $this->buildDiscordMessage(
            title: "SSL Certificate Expiring: {$domain}",
            description: "Certificate will expire in **{$days} days**\n**Expiry date:** {$expiryDate}",
            color: 0xFFAA00,
            url: url("/app/{$this->certificate->webApp->team_id}/web-apps/{$this->certificate->web_app_id}")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        $days = $this->certificate->getDaysUntilExpiry();
        $domain = $this->certificate->domain;

        return FilamentNotification::make()
            ->title('SSL Certificate Expiring')
            ->icon('heroicon-o-shield-exclamation')
            ->warning()
            ->body("SSL certificate for {$domain} will expire in {$days} days.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ssl_expiring',
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'expires_at' => $this->certificate->expires_at,
            'days_remaining' => $this->certificate->getDaysUntilExpiry(),
        ];
    }
}
