<?php

namespace App\Notifications;

use App\Models\SupervisorProgram;
use App\Notifications\Concerns\HasNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NodeAppDown extends Notification implements ShouldQueue
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
            ->subject("🔴 Node.js App Down: {$appName}")
            ->error()
            ->greeting('Node.js Application Down')
            ->line("Your Node.js application **{$appName}** on server **{$serverName}** is not responding to health checks.")
            ->line("The health check endpoint has failed {$this->program->consecutive_failures} consecutive times.")
            ->action('View Application', url("/app/{$this->program->team_id}/web-apps/{$this->program->web_app_id}"))
            ->line('Please check your application logs and restart if necessary.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'node_app_down',
            'program_id' => $this->program->id,
            'program_name' => $this->program->name,
            'web_app_id' => $this->program->web_app_id,
            'web_app_domain' => $this->program->webApp?->domain,
            'server_id' => $this->program->server_id,
            'server_name' => $this->program->server?->name,
            'consecutive_failures' => $this->program->consecutive_failures,
        ];
    }

    public function toSlack(object $notifiable): array
    {
        $appName = $this->program->webApp?->domain ?? $this->program->name;
        $serverName = $this->program->server?->name ?? 'Unknown';

        return [
            'text' => "🔴 Node.js App Down: {$appName}",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Node.js Application Down*\n\nYour app *{$appName}* on server *{$serverName}* is not responding.",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Failed Checks:*\n{$this->program->consecutive_failures}"],
                        ['type' => 'mrkdwn', 'text' => "*Health URL:*\n{$this->program->health_check_url}"],
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
            'content' => "🔴 **Node.js App Down**: {$appName}",
            'embeds' => [
                [
                    'title' => 'Application Health Check Failed',
                    'description' => "Your Node.js app **{$appName}** on server **{$serverName}** is not responding to health checks.",
                    'color' => 15158332, // Red
                    'fields' => [
                        ['name' => 'Failed Checks', 'value' => (string) $this->program->consecutive_failures, 'inline' => true],
                        ['name' => 'Health URL', 'value' => $this->program->health_check_url ?? 'N/A', 'inline' => true],
                    ],
                ],
            ],
        ];
    }
}
