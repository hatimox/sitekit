<?php

namespace App\Filament\Resources\SshKeyResource\Pages;

use App\Filament\Resources\SshKeyResource;
use App\Models\SshKey;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSshKey extends ViewRecord
{
    protected static string $resource = SshKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Key Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('fingerprint')
                            ->fontFamily('mono')
                            ->copyable(),
                        TextEntry::make('user.name')
                            ->label('Added By'),
                    ])
                    ->columns(3),

                Section::make('Public Key')
                    ->schema([
                        TextEntry::make('public_key')
                            ->label('')
                            ->fontFamily('mono')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Deployed to Servers')
                    ->schema([
                        RepeatableEntry::make('servers')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Server'),
                                TextEntry::make('ip_address')
                                    ->label('IP Address'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'provisioning' => 'warning',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(3)
                            ->contained(false),
                    ])
                    ->visible(fn (SshKey $record) => $record->servers->isNotEmpty()),

                Section::make('No Servers')
                    ->schema([
                        TextEntry::make('no_servers')
                            ->label('')
                            ->default('This SSH key has not been deployed to any servers yet.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (SshKey $record) => $record->servers->isEmpty()),

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
