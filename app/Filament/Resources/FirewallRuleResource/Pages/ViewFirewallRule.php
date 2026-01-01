<?php

namespace App\Filament\Resources\FirewallRuleResource\Pages;

use App\Filament\Resources\FirewallRuleResource;
use App\Models\FirewallRule;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFirewallRule extends ViewRecord
{
    protected static string $resource = FirewallRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (FirewallRule $record) => !$record->is_system),

            Actions\Action::make('toggle')
                ->label(fn (FirewallRule $record) => $record->is_active ? 'Disable' : 'Enable')
                ->icon(fn (FirewallRule $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn (FirewallRule $record) => $record->is_active ? 'warning' : 'success')
                ->visible(fn (FirewallRule $record) => !$record->is_system)
                ->requiresConfirmation()
                ->action(function (FirewallRule $record) {
                    $record->update(['is_active' => !$record->is_active]);

                    if ($record->is_active) {
                        $record->dispatchApply();
                        Notification::make()
                            ->title('Firewall Rule Enabled')
                            ->success()
                            ->send();
                    } else {
                        $record->revert();
                        Notification::make()
                            ->title('Firewall Rule Disabled')
                            ->warning()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn (FirewallRule $record) => !$record->is_system)
                ->before(function (FirewallRule $record) {
                    if ($record->is_active) {
                        $record->revert();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Rule Configuration')
                    ->schema([
                        TextEntry::make('server.name')
                            ->label('Server'),
                        TextEntry::make('action')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                FirewallRule::ACTION_ALLOW => 'success',
                                FirewallRule::ACTION_DENY => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('direction')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => match ($state) {
                                FirewallRule::DIRECTION_IN => 'Inbound',
                                FirewallRule::DIRECTION_OUT => 'Outbound',
                                default => $state,
                            }),
                        TextEntry::make('protocol')
                            ->badge()
                            ->color('info'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        IconEntry::make('is_system')
                            ->label('System Rule')
                            ->boolean(),
                    ])
                    ->columns(3),

                Section::make('Target')
                    ->schema([
                        TextEntry::make('port')
                            ->label('Port(s)')
                            ->copyable(),
                        TextEntry::make('from_ip')
                            ->label('From IP')
                            ->copyable(),
                        TextEntry::make('description'),
                        TextEntry::make('order')
                            ->label('Priority'),
                    ])
                    ->columns(2),

                Section::make('Safety Confirmation')
                    ->schema([
                        IconEntry::make('is_pending_confirmation')
                            ->label('Awaiting Confirmation')
                            ->boolean(),
                        TextEntry::make('confirmation_expires_at')
                            ->label('Confirmation Expires')
                            ->dateTime()
                            ->visible(fn (FirewallRule $record) => $record->is_pending_confirmation),
                        TextEntry::make('rollback_reason')
                            ->visible(fn (FirewallRule $record) => $record->rollback_reason !== null),
                        TextEntry::make('rolled_back_at')
                            ->label('Rolled Back At')
                            ->dateTime()
                            ->visible(fn (FirewallRule $record) => $record->rolled_back_at !== null),
                    ])
                    ->columns(2)
                    ->visible(fn (FirewallRule $record) =>
                        $record->is_pending_confirmation ||
                        $record->rollback_reason !== null ||
                        $record->rolled_back_at !== null
                    ),

                Section::make('UFW Command')
                    ->schema([
                        TextEntry::make('ufw_command')
                            ->label('')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
