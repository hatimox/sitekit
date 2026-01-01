<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AiDemo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'AI Demo';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?int $navigationSort = 100;
    protected static ?string $title = 'AI Assistant Demo';

    protected static string $view = 'filament.pages.ai-demo';

    public function mount(): void
    {
        // Demo data
    }
}
