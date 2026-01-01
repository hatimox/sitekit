<?php

namespace App\Notifications;

use App\Models\FirewallRule;
use App\Models\Team;
use App\Notifications\Concerns\HasMultiChannelSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class FirewallRollbackWarning extends Notification implements ShouldQueue
{
    use Queueable;
    use HasMultiChannelSupport;

    public function __construct(
        public FirewallRule $rule,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Team) {
            return $this->getChannels($notifiable);
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $server = $this->rule->server;

        return (new MailMessage)
            ->warning()
            ->subject("Firewall Rule Rolled Back: {$server->name}")
            ->greeting('Firewall Safety Alert')
            ->line("A firewall rule on **{$server->name}** was automatically rolled back.")
            ->line("**Rule Details:**")
            ->line("- Action: {$this->rule->action}")
            ->line("- Port: {$this->rule->port}")
            ->line("- Protocol: {$this->rule->protocol}")
            ->when($this->reason, fn ($mail) => $mail->line("**Reason:** {$this->reason}"))
            ->line('This automatic rollback is a safety feature to prevent lockouts.')
            ->action('View Firewall Rules', url("/app/{$this->rule->team_id}/firewall-rules"))
            ->line('If this rule was intentional, you can re-apply it and confirm within the timeout period.');
    }

    public function toSlack(object $notifiable): array
    {
        $server = $this->rule->server;
        $reason = $this->reason ? "\nReason: {$this->reason}" : '';

        return $this->buildSlackMessage(
            title: "Firewall Rule Rolled Back: {$server->name}",
            text: "A firewall rule was automatically rolled back\nAction: {$this->rule->action}\nPort: {$this->rule->port}\nProtocol: {$this->rule->protocol}{$reason}",
            color: 'warning',
            url: url("/app/{$this->rule->team_id}/firewall-rules")
        );
    }

    public function toDiscord(object $notifiable): array
    {
        $server = $this->rule->server;
        $reason = $this->reason ? "\n**Reason:** {$this->reason}" : '';

        return $this->buildDiscordMessage(
            title: "Firewall Rule Rolled Back: {$server->name}",
            description: "A firewall rule was automatically rolled back\n**Action:** {$this->rule->action}\n**Port:** {$this->rule->port}\n**Protocol:** {$this->rule->protocol}{$reason}",
            color: 0xFFAA00,
            url: url("/app/{$this->rule->team_id}/firewall-rules")
        );
    }

    public function toDatabase(object $notifiable): array
    {
        $server = $this->rule->server;

        return FilamentNotification::make()
            ->title('Firewall Rule Rolled Back')
            ->icon('heroicon-o-shield-exclamation')
            ->warning()
            ->body("A firewall rule on {$server->name} was automatically rolled back for safety.")
            ->getDatabaseMessage();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'firewall_rollback',
            'rule_id' => $this->rule->id,
            'server_id' => $this->rule->server_id,
            'server_name' => $this->rule->server->name,
            'reason' => $this->reason,
        ];
    }
}
