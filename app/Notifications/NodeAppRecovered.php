<?php

namespace App\Notifications;

use App\Models\SupervisorProgram;
use App\Notifications\Concerns\HasNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NodeAppRecovered extends Notification implements ShouldQueue
{
    use Queueable, HasNotificationChannels;

    public function __construct(
        public SupervisorProgram $program
    ) {}

    public function via(object $notifiable): array
    {
        return $this->getChannels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = $this->program->webApp?->domain ?? $this->program->name;
        $serverName = $this->program->server?->name ?? 'Unknown Server';

        return (new MailMessage)
            ->subject("🟢 Node.js App Recovered: {$appName}")
            ->success()
            ->greeting('Node.js Application Recovered')
            ->line("Your Node.js application **{$appName}** on server **{$serverName}** is now responding to health checks.")
            ->line('The application has recovered and is back online.')
            ->action('View Application', url("/app/{$this->program->team_id}/web-apps/{$this->program->web_app_id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'node_app_recovered',
            'program_id' => $this->program->id,
            'program_name' => $this->program->name,
            'web_app_id' => $this->program->web_app_id,
            'web_app_domain' => $this->program->webApp?->domain,
            'server_id' => $this->program->server_id,
            'server_name' => $this->program->server?->name,
        ];
    }

    public function toSlack(object $notifiable): array
    {
        $appName = $this->program->webApp?->domain ?? $this->program->name;
        $serverName = $this->program->server?->name ?? 'Unknown';

        return [
            'text' => "🟢 Node.js App Recovered: {$appName}",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Node.js Application Recovered*\n\nYour app *{$appName}* on server *{$serverName}* is back online.",
                    ],
                ],
            ],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $appName = $this->program->webApp?->domain ?? $this->program->name;
        $serverName = $this->program->server?->name ?? 'Unknown';

        return [
            'content' => "🟢 **Node.js App Recovered**: {$appName}",
            'embeds' => [
                [
                    'title' => 'Application Health Check Passed',
                    'description' => "Your Node.js app **{$appName}** on server **{$serverName}** is now responding to health checks.",
                    'color' => 3066993, // Green
                ],
            ],
        ];
    }
}
