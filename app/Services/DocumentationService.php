<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class DocumentationService
{
    protected string $docsPath;
    protected GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->docsPath = resource_path('docs');

        // Configure CommonMark with GitHub Flavored Markdown
        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];

        $this->converter = new GithubFlavoredMarkdownConverter($config);
    }

    /**
     * Get all available documentation topics
     */
    public function getTopics(): array
    {
        return [
            'getting-started' => [
                'title' => 'Getting Started',
                'icon' => 'heroicon-o-rocket-launch',
                'file' => 'getting-started.md',
            ],
            'servers' => [
                'title' => 'Servers',
                'icon' => 'heroicon-o-server-stack',
                'file' => 'servers.md',
            ],
            'web-apps' => [
                'title' => 'Web Apps',
                'icon' => 'heroicon-o-globe-alt',
                'file' => 'web-apps.md',
            ],
            'databases' => [
                'title' => 'Databases',
                'icon' => 'heroicon-o-circle-stack',
                'file' => 'databases.md',
            ],
            'ssl' => [
                'title' => 'SSL Certificates',
                'icon' => 'heroicon-o-lock-closed',
                'file' => 'ssl.md',
            ],
            'cron-jobs' => [
                'title' => 'Cron Jobs',
                'icon' => 'heroicon-o-clock',
                'file' => 'cron-jobs.md',
            ],
            'firewall' => [
                'title' => 'Firewall',
                'icon' => 'heroicon-o-shield-check',
                'file' => 'firewall.md',
            ],
            'workers' => [
                'title' => 'Background Workers',
                'icon' => 'heroicon-o-cog-6-tooth',
                'file' => 'workers.md',
            ],
            'ssh-keys' => [
                'title' => 'SSH Keys',
                'icon' => 'heroicon-o-key',
                'file' => 'ssh-keys.md',
            ],
            'health-monitors' => [
                'title' => 'Health Monitors',
                'icon' => 'heroicon-o-heart',
                'file' => 'health-monitors.md',
            ],
        ];
    }

    /**
     * Get topic by slug
     */
    public function getTopic(string $slug): ?array
    {
        $topics = $this->getTopics();
        return $topics[$slug] ?? null;
    }

    /**
     * Get raw markdown content for a topic
     */
    public function getMarkdown(string $slug): ?string
    {
        $topic = $this->getTopic($slug);
        if (!$topic) {
            return null;
        }

        $filePath = $this->docsPath . '/' . $topic['file'];

        if (!File::exists($filePath)) {
            return null;
        }

        return File::get($filePath);
    }

    /**
     * Get HTML content for a topic
     */
    public function getHtml(string $slug): ?string
    {
        $markdown = $this->getMarkdown($slug);
        if (!$markdown) {
            return null;
        }

        return $this->converter->convert($markdown)->getContent();
    }

    /**
     * Get content parsed into sections based on H2 headers
     */
    public function getSections(string $slug): array
    {
        $markdown = $this->getMarkdown($slug);
        if (!$markdown) {
            return [];
        }

        // Split by H2 headers (## )
        $parts = preg_split('/^## /m', $markdown);

        $sections = [];
        $firstPart = true;

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if ($firstPart) {
                // First part is the H1 title and intro
                $firstPart = false;

                // Extract title from H1
                if (preg_match('/^# (.+)$/m', $part, $matches)) {
                    $sections['_title'] = trim($matches[1]);
                    $part = preg_replace('/^# .+$/m', '', $part);
                }

                $intro = trim($part);
                if (!empty($intro)) {
                    $sections['_intro'] = [
                        'title' => 'Overview',
                        'content' => $this->converter->convert($intro)->getContent(),
                    ];
                }
                continue;
            }

            // Extract section title from first line
            $lines = explode("\n", $part, 2);
            $title = trim($lines[0]);
            $content = isset($lines[1]) ? trim($lines[1]) : '';

            // Create a slug from the title
            $sectionSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
            $sectionSlug = trim($sectionSlug, '-');

            $sections[$sectionSlug] = [
                'title' => $title,
                'content' => $this->converter->convert($content)->getContent(),
            ];
        }

        return $sections;
    }

    /**
     * Get content for the Filament docs page (keyed by section slug)
     */
    public function getFilamentContent(string $topicSlug): array
    {
        $sections = $this->getSections($topicSlug);

        $content = [];
        foreach ($sections as $key => $section) {
            if (str_starts_with($key, '_')) {
                continue; // Skip meta sections like _title, _intro
            }

            $content[$key] = [
                'title' => $section['title'],
                'content' => $section['content'],
            ];
        }

        return $content;
    }

    /**
     * Get sidebar sections for Filament page
     */
    public function getFilamentSections(string $topicSlug): array
    {
        $sections = $this->getSections($topicSlug);

        $result = [];
        foreach ($sections as $key => $section) {
            if (str_starts_with($key, '_')) {
                continue;
            }
            $result[$key] = $section['title'];
        }

        return $result;
    }

    /**
     * Check if a topic exists
     */
    public function exists(string $slug): bool
    {
        $topic = $this->getTopic($slug);
        if (!$topic) {
            return false;
        }

        $filePath = $this->docsPath . '/' . $topic['file'];
        return File::exists($filePath);
    }
}
