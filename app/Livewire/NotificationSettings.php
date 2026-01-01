<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationSettings extends Component
{
    public $team;
    public $slack_webhook_url = '';
    public $discord_webhook_url = '';
    public $slack_enabled = true;
    public $discord_enabled = true;

    public function mount($team)
    {
        $this->team = $team;
        $this->slack_webhook_url = $team->slack_webhook_url ?? '';
        $this->discord_webhook_url = $team->discord_webhook_url ?? '';

        $settings = $team->notification_settings ?? [];
        $this->slack_enabled = $settings['slack_enabled'] ?? true;
        $this->discord_enabled = $settings['discord_enabled'] ?? true;
    }

    public function updateNotificationSettings()
    {
        $this->validate([
            'slack_webhook_url' => ['nullable', 'url', 'regex:/^https:\/\/hooks\.slack\.com\//'],
            'discord_webhook_url' => ['nullable', 'url', 'regex:/^https:\/\/discord\.com\/api\/webhooks\//'],
        ], [
            'slack_webhook_url.regex' => 'The Slack webhook URL must be a valid Slack incoming webhook URL.',
            'discord_webhook_url.regex' => 'The Discord webhook URL must be a valid Discord webhook URL.',
        ]);

        $this->team->update([
            'slack_webhook_url' => $this->slack_webhook_url ?: null,
            'discord_webhook_url' => $this->discord_webhook_url ?: null,
            'notification_settings' => [
                'slack_enabled' => $this->slack_enabled,
                'discord_enabled' => $this->discord_enabled,
            ],
        ]);

        $this->dispatch('saved');
    }

    public function testSlackWebhook()
    {
        if (empty($this->slack_webhook_url)) {
            $this->addError('slack_webhook_url', 'Please enter a Slack webhook URL first.');
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($this->slack_webhook_url, [
                'text' => 'Test notification from SiteKit',
                'attachments' => [
                    [
                        'color' => 'good',
                        'title' => 'Webhook Test Successful',
                        'text' => 'Your Slack integration is working correctly.',
                        'footer' => 'SiteKit',
                        'ts' => now()->timestamp,
                    ],
                ],
            ]);

            if ($response->successful()) {
                session()->flash('slack_test_success', 'Test message sent successfully!');
            } else {
                $this->addError('slack_webhook_url', 'Failed to send test message. Please check your webhook URL.');
            }
        } catch (\Exception $e) {
            $this->addError('slack_webhook_url', 'Failed to send test message: ' . $e->getMessage());
        }
    }

    public function testDiscordWebhook()
    {
        if (empty($this->discord_webhook_url)) {
            $this->addError('discord_webhook_url', 'Please enter a Discord webhook URL first.');
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($this->discord_webhook_url, [
                'embeds' => [
                    [
                        'title' => 'Webhook Test Successful',
                        'description' => 'Your Discord integration is working correctly.',
                        'color' => 0x00FF00,
                        'timestamp' => now()->toIso8601String(),
                        'footer' => [
                            'text' => 'SiteKit',
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                session()->flash('discord_test_success', 'Test message sent successfully!');
            } else {
                $this->addError('discord_webhook_url', 'Failed to send test message. Please check your webhook URL.');
            }
        } catch (\Exception $e) {
            $this->addError('discord_webhook_url', 'Failed to send test message: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.notification-settings');
    }
}
