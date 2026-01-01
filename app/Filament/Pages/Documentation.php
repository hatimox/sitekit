<?php

namespace App\Filament\Pages;

use App\Services\DocumentationService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class Documentation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Documentation';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.documentation';

    public string $topic = 'getting-started';

    protected DocumentationService $docs;

    public function boot(DocumentationService $docs): void
    {
        $this->docs = $docs;
    }

    public function mount(): void
    {
        $this->topic = request()->query('topic', 'getting-started');
    }

    public function getTitle(): string
    {
        return 'Documentation';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_public')
                ->label('View Public Docs')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(route('docs.show', $this->topic))
                ->openUrlInNewTab(),
            Action::make('ai_ask')
                ->label('Ask AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn () => config('ai.enabled'))
                ->extraAttributes([
                    'x-data' => '',
                    'x-on:click.prevent' => 'openAiChat("I have a question about SiteKit. Help me understand how to use this platform effectively.")',
                ]),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public function getDocTopics(): array
    {
        return app(DocumentationService::class)->getTopics();
    }

    public function getDocContent(): array
    {
        return app(DocumentationService::class)->getFilamentContent($this->topic);
    }

    public function getDocSections(): array
    {
        return app(DocumentationService::class)->getFilamentSections($this->topic);
    }
}
