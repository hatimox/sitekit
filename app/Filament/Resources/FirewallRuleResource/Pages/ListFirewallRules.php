<?php

namespace App\Filament\Resources\FirewallRuleResource\Pages;

use App\Filament\Resources\FirewallRuleResource;
use App\Models\FirewallRule;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListFirewallRules extends ListRecords
{
    protected static string $resource = FirewallRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // AI Audit Rules
            Actions\Action::make('ai_audit')
                ->label('Audit Rules')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn () => config('ai.enabled'))
                ->action(function () {
                    $rules = FirewallRule::where('team_id', Filament::getTenant()?->id)
                        ->with('server')
                        ->get();

                    if ($rules->isEmpty()) {
                        $prompt = "I don't have any firewall rules yet. What are the essential firewall rules I should configure for a web server running PHP applications with MySQL/PostgreSQL?";
                    } else {
                        $rulesSummary = $rules->map(fn ($r) => "- {$r->server?->name}: {$r->action} {$r->direction} {$r->protocol}/{$r->port} from {$r->from_ip}")->implode("\n");
                        $prompt = "Audit my firewall rules for security issues:\n\n{$rulesSummary}\n\nAre there any security concerns? Missing rules? Overly permissive rules? Suggest improvements.";
                    }

                    $this->dispatch('open-ai-chat', message: $prompt);
                }),

            // AI Generate Rules
            Actions\Action::make('ai_generate')
                ->label('Generate Rules')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn () => config('ai.enabled'))
                ->action(function () {
                    $this->dispatch('open-ai-chat', message: "Generate firewall rules for my server. I need to allow: SSH (port 22), HTTP (80), HTTPS (443). What other ports should I consider for a typical web application server? Show me the recommended UFW commands.");
                }),

            Actions\CreateAction::make(),
        ];
    }
}
