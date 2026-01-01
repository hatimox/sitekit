<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresServerForNavigation;
use App\Filament\Resources\FirewallRuleResource\Pages;
use App\Models\FirewallRule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class FirewallRuleResource extends Resource
{
    use RequiresServerForNavigation;

    protected static ?string $model = FirewallRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Firewall Rules';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Firewall Rule')
                    ->description('Control incoming and outgoing network traffic. Rules are managed via UFW and take effect immediately.')
                    ->icon('heroicon-o-shield-check')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_ports_needed')
                            ->label('What ports do I need?')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat(' . json_encode("What firewall ports do I need to open for common web services? List ports for: Laravel/PHP apps, MySQL, PostgreSQL, Redis, Node.js, SSL/HTTPS, SSH, and any other commonly needed ports. Explain what each port is used for.") . ')',
                            ]),
                        Forms\Components\Actions\Action::make('ai_best_practices')
                            ->label('Security tips')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat(' . json_encode("What are the best practices for server firewall configuration? Should I use allow or deny by default? How should I handle SSH access? What about rate limiting?") . ')',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\Select::make('server_id')
                            ->relationship('server', 'name', fn (Builder $query) =>
                                $query->where('team_id', Filament::getTenant()?->id)
                                    ->where('status', 'active'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('action')
                            ->options([
                                FirewallRule::ACTION_ALLOW => 'Allow',
                                FirewallRule::ACTION_DENY => 'Deny',
                            ])
                            ->default(FirewallRule::ACTION_ALLOW)
                            ->required(),
                        Forms\Components\Select::make('direction')
                            ->options([
                                FirewallRule::DIRECTION_IN => 'Inbound',
                                FirewallRule::DIRECTION_OUT => 'Outbound',
                            ])
                            ->default(FirewallRule::DIRECTION_IN)
                            ->required(),
                        Forms\Components\Select::make('protocol')
                            ->options([
                                FirewallRule::PROTOCOL_TCP => 'TCP',
                                FirewallRule::PROTOCOL_UDP => 'UDP',
                                FirewallRule::PROTOCOL_ANY => 'Any',
                            ])
                            ->default(FirewallRule::PROTOCOL_TCP)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Target')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_ip_check')
                            ->label('Is this IP safe?')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat(' . json_encode("How do I verify if an IP address is safe to whitelist in my firewall? What tools can I use to check if an IP is malicious? What are the risks of allowing specific IPs vs 'any'?") . ')',
                            ]),
                        Forms\Components\Actions\Action::make('ai_cidr_help')
                            ->label('CIDR notation')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat(' . json_encode("Explain CIDR notation for firewall rules. How do I specify a range of IPs? Give examples like /24, /16, /8 and explain how many IPs each covers.") . ')',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('port')
                            ->required()
                            ->placeholder('22, 80, 3000:3100')
                            ->helperText('Single port, comma-separated, or range'),
                        Forms\Components\TextInput::make('from_ip')
                            ->default('any')
                            ->placeholder('192.168.1.0/24 or specific IP')
                            ->helperText(new HtmlString('Use "any" to allow all IPs. <a href="/app/documentation?topic=firewall" class="text-primary-600 hover:underline" wire:navigate>CIDR notation guide</a>')),
                        Forms\Components\TextInput::make('description')
                            ->placeholder('SSH Access'),
                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(50)
                            ->helperText('Lower numbers are processed first'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('action')
                    ->colors([
                        'success' => FirewallRule::ACTION_ALLOW,
                        'danger' => FirewallRule::ACTION_DENY,
                    ]),
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('protocol')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('port')
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_ip')
                    ->label('From IP'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),
            ])
            ->defaultSort('order')
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        FirewallRule::ACTION_ALLOW => 'Allow',
                        FirewallRule::ACTION_DENY => 'Deny',
                    ]),
            ])
            ->actions([
                // AI Explain Rule
                Tables\Actions\Action::make('ai_explain')
                    ->label('Explain')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn () => config('ai.enabled'))
                    ->extraAttributes(fn (FirewallRule $record) => [
                        'x-data' => '',
                        'x-on:click.prevent' => 'openAiChat(' . json_encode(
                            "Explain this firewall rule: Action: {$record->action}, Direction: {$record->direction}, Protocol: {$record->protocol}, Port: {$record->port}, From IP: {$record->from_ip}. What does this rule do? Is it secure? Are there any concerns I should be aware of?"
                        ) . ')',
                    ]),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (FirewallRule $record) => !$record->is_system),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (FirewallRule $record) => $record->is_active ? 'Disable' : 'Enable')
                    ->icon(fn (FirewallRule $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (FirewallRule $record) => $record->is_active ? 'warning' : 'success')
                    ->visible(fn (FirewallRule $record) => !$record->is_system)
                    ->requiresConfirmation()
                    ->action(function (FirewallRule $record) {
                        $record->update(['is_active' => !$record->is_active]);

                        if ($record->is_active) {
                            $record->dispatchApply();
                        } else {
                            $record->revert();
                        }
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (FirewallRule $record) => !$record->is_system)
                    ->form([
                        Forms\Components\TextInput::make('description')
                            ->default(fn (FirewallRule $record) => $record->description . ' (Copy)')
                            ->placeholder('Description for the new rule'),
                    ])
                    ->action(function (FirewallRule $record, array $data) {
                        $clone = $record->replicate(['is_system', 'created_at', 'updated_at']);
                        $clone->description = $data['description'];
                        $clone->is_active = false;
                        $clone->save();

                        Notification::make()
                            ->title('Rule Duplicated')
                            ->body('Enable the new rule when ready.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (FirewallRule $record) => !$record->is_system)
                    ->before(function (FirewallRule $record) {
                        if ($record->is_active) {
                            $record->revert();
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('templates')
                    ->label('Add from Template')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('server_id')
                            ->label('Server')
                            ->options(fn () => \App\Models\Server::where('team_id', Filament::getTenant()?->id)
                                ->where('status', 'active')
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\CheckboxList::make('templates')
                            ->label('Select Templates')
                            ->options([
                                'http' => 'HTTP (80) - Web Traffic',
                                'https' => 'HTTPS (443) - Secure Web Traffic',
                                'ssh' => 'SSH (22) - Remote Access',
                                'mysql' => 'MySQL (3306) - Database',
                                'postgresql' => 'PostgreSQL (5432) - Database',
                                'redis' => 'Redis (6379) - Cache',
                                'ftp' => 'FTP (21) - File Transfer',
                            ])
                            ->columns(2)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $templates = [
                            'http' => ['port' => '80', 'description' => 'HTTP Traffic'],
                            'https' => ['port' => '443', 'description' => 'HTTPS Traffic'],
                            'ssh' => ['port' => '22', 'description' => 'SSH Access'],
                            'mysql' => ['port' => '3306', 'description' => 'MySQL Database'],
                            'postgresql' => ['port' => '5432', 'description' => 'PostgreSQL Database'],
                            'redis' => ['port' => '6379', 'description' => 'Redis Cache'],
                            'ftp' => ['port' => '21', 'description' => 'FTP Access'],
                        ];

                        $count = 0;
                        foreach ($data['templates'] as $template) {
                            if (isset($templates[$template])) {
                                FirewallRule::create([
                                    'team_id' => Filament::getTenant()->id,
                                    'server_id' => $data['server_id'],
                                    'action' => FirewallRule::ACTION_ALLOW,
                                    'direction' => FirewallRule::DIRECTION_IN,
                                    'protocol' => FirewallRule::PROTOCOL_TCP,
                                    'port' => $templates[$template]['port'],
                                    'from_ip' => 'any',
                                    'description' => $templates[$template]['description'],
                                    'is_active' => false,
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} Rules Created")
                            ->body('Enable the rules when ready to apply.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('ai_security_review')
                        ->label('AI Security Review')
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->visible(fn () => config('ai.enabled'))
                        ->requiresConfirmation()
                        ->modalHeading('AI Security Review')
                        ->modalDescription(fn ($records) => 'Review ' . $records->count() . ' firewall rule(s) for security issues?')
                        ->modalSubmitActionLabel('Review with AI')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, $livewire) {
                            $rulesList = $records->map(fn ($r) => "- {$r->action} {$r->direction} {$r->protocol}/{$r->port} from {$r->from_ip}" . ($r->description ? " ({$r->description})" : ""))->join("\n");
                            $message = "Review these firewall rules for security:\n\n{$rulesList}\n\nCheck for:\n- Overly permissive rules\n- Missing security measures\n- Potential vulnerabilities\n- Best practice violations\n\nProvide specific recommendations for improvement.";
                            $livewire->dispatch('open-ai-chat', message: $message);
                        }),

                    Tables\Actions\BulkAction::make('enable')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->is_system && !$record->is_active) {
                                    $record->update(['is_active' => true]);
                                    $record->dispatchApply();
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} rules enabled")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('disable')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->is_system && $record->is_active) {
                                    $record->update(['is_active' => false]);
                                    $record->revert();
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} rules disabled")
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateHeading('Firewall configured')
            ->emptyStateDescription('Default firewall rules (SSH, HTTP, HTTPS) are automatically created during provisioning. Add custom rules to allow or deny specific ports.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Firewall Rule')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFirewallRules::route('/'),
            'create' => Pages\CreateFirewallRule::route('/create'),
            'view' => Pages\ViewFirewallRule::route('/{record}'),
            'edit' => Pages\EditFirewallRule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
