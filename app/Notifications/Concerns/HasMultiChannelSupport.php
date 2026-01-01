<?php

namespace App\Notifications\Concerns;

use App\Models\Team;

trait HasMultiChannelSupport
{
    protected function getChannels(Team $team): array
    {
        $channels = ['database'];

        if ($team->shouldNotifyVia('mail')) {
            $channels[] = 'mail';
        }

        if ($team->shouldNotifyVia('slack')) {
            $channels[] = 'slack';
        }

        if ($team->shouldNotifyVia('discord')) {
            $channels[] = 'discord';
        }

        return $channels;
    }

    protected function buildSlackMessage(string $title, string $text, string $color = 'danger', ?string $url = null): array
    {
        $attachment = [
            'color' => $color,
            'title' => $title,
            'text' => $text,
            'footer' => 'SiteKit',
            'ts' => now()->timestamp,
        ];

        if ($url) {
            $attachment['title_link'] = $url;
        }

        return [
            'attachments' => [$attachment],
        ];
    }

    protected function buildDiscordMessage(string $title, string $description, int $color = 0xFF0000, ?string $url = null): array
    {
        $embed = [
            'title' => $title,
            'description' => $description,
            'color' => $color,
            'timestamp' => now()->toIso8601String(),
            'footer' => [
                'text' => 'SiteKit',
            ],
        ];

        if ($url) {
            $embed['url'] = $url;
        }

        return [
            'embeds' => [$embed],
        ];
    }
}
