<?php

namespace App\Filament\Resources\WebAppResource\RelationManagers;

use App\Models\SslCertificate;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SslCertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'sslCertificates';

    protected static ?string $title = 'SSL Certificates';

    protected static ?string $icon = 'heroicon-o-lock-closed';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->headerActions([
                Tables\Actions\Action::make('addCustomSsl')
                    ->label('Add Custom SSL')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->form([
                        Forms\Components\Textarea::make('certificate')
                            ->label('SSL Certificate (PEM)')
                            ->required()
                            ->rows(6)
                            ->placeholder('-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----')
                            ->helperText('Paste your SSL certificate in PEM format'),
                        Forms\Components\Textarea::make('private_key')
                            ->label('Private Key (PEM)')
                            ->required()
                            ->rows(6)
                            ->placeholder('-----BEGIN PRIVATE KEY-----
...
-----END PRIVATE KEY-----')
                            ->helperText('Paste your private key in PEM format'),
                        Forms\Components\Textarea::make('chain')
                            ->label('Certificate Chain (Optional)')
                            ->rows(6)
                            ->placeholder('-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----')
                            ->helperText('Paste intermediate/chain certificates if required'),
                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Expiry Date')
                            ->required()
                            ->minDate(now())
                            ->helperText('When does this certificate expire?'),
                    ])
                    ->action(function (array $data) {
                        $webApp = $this->getOwnerRecord();

                        // Create the custom SSL certificate record
                        $certificate = SslCertificate::create([
                            'web_app_id' => $webApp->id,
                            'type' => SslCertificate::TYPE_CUSTOM,
                            'domain' => $webApp->domain,
                            'status' => SslCertificate::STATUS_PENDING,
                            'certificate' => $data['certificate'],
                            'private_key' => $data['private_key'],
                            'chain' => $data['chain'] ?? null,
                            'expires_at' => $data['expires_at'],
                            'issued_at' => now(),
                        ]);

                        // Dispatch job to install the certificate
                        $certificate->dispatchInstall();

                        // Update webapp SSL status
                        $webApp->update(['ssl_status' => \App\Models\WebApp::SSL_PENDING]);

                        Notification::make()
                            ->title('Custom SSL Certificate Added')
                            ->body('Installing certificate on server...')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SslCertificate::TYPE_LETSENCRYPT => 'success',
                        SslCertificate::TYPE_CUSTOM => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SslCertificate::TYPE_LETSENCRYPT => "Let's Encrypt",
                        SslCertificate::TYPE_CUSTOM => 'Custom',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => [SslCertificate::STATUS_PENDING, SslCertificate::STATUS_RENEWING],
                        'info' => SslCertificate::STATUS_ISSUING,
                        'success' => SslCertificate::STATUS_ACTIVE,
                        'danger' => [SslCertificate::STATUS_FAILED, SslCertificate::STATUS_EXPIRED],
                    ]),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (?SslCertificate $record): string =>
                        $record?->isExpired() ? 'danger' :
                        ($record?->isExpiringSoon() ? 'warning' : 'success')
                    ),
                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label('Days Left')
                    ->getStateUsing(fn (?SslCertificate $record) => $record?->getDaysUntilExpiry())
                    ->suffix(' days')
                    ->color(fn (?SslCertificate $record): string =>
                        $record?->isExpired() ? 'danger' :
                        ($record?->isExpiringSoon() ? 'warning' : 'success')
                    ),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->tooltip(fn (?SslCertificate $record) => $record?->error_message)
                    ->visible(fn (?SslCertificate $record) => $record?->status === SslCertificate::STATUS_FAILED)
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        SslCertificate::STATUS_ACTIVE => 'Active',
                        SslCertificate::STATUS_PENDING => 'Pending',
                        SslCertificate::STATUS_EXPIRED => 'Expired',
                        SslCertificate::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (SslCertificate $record) => 'SSL Certificate: ' . $record->domain)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Certificate Details')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('domain')
                                    ->label('Domain'),
                                \Filament\Infolists\Components\TextEntry::make('type')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        SslCertificate::TYPE_LETSENCRYPT => "Let's Encrypt",
                                        SslCertificate::TYPE_CUSTOM => 'Custom',
                                        default => $state,
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                                \Filament\Infolists\Components\TextEntry::make('issued_at')
                                    ->label('Issued')
                                    ->dateTime(),
                                \Filament\Infolists\Components\TextEntry::make('expires_at')
                                    ->label('Expires')
                                    ->dateTime(),
                            ])
                            ->columns(3),
                        \Filament\Infolists\Components\Section::make('Certificate (PEM)')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('certificate')
                                    ->label('')
                                    ->fontFamily('mono')
                                    ->copyable()
                                    ->copyMessage('Certificate copied!')
                                    ->placeholder('No certificate data stored'),
                            ])
                            ->collapsible()
                            ->visible(fn (SslCertificate $record) => !empty($record->certificate)),
                        \Filament\Infolists\Components\Section::make('Private Key (PEM)')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('private_key')
                                    ->label('')
                                    ->fontFamily('mono')
                                    ->copyable()
                                    ->copyMessage('Private key copied!')
                                    ->placeholder('No private key stored'),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (SslCertificate $record) => !empty($record->private_key)),
                        \Filament\Infolists\Components\Section::make('Certificate Chain (PEM)')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('chain')
                                    ->label('')
                                    ->fontFamily('mono')
                                    ->copyable()
                                    ->copyMessage('Chain copied!')
                                    ->placeholder('No chain data'),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (SslCertificate $record) => !empty($record->chain)),
                        \Filament\Infolists\Components\Section::make('Error')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('error_message')
                                    ->label('')
                                    ->color('danger'),
                            ])
                            ->visible(fn (SslCertificate $record) => !empty($record->error_message)),
                    ]),
                Tables\Actions\Action::make('renew')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (?SslCertificate $record) =>
                        $record?->type === SslCertificate::TYPE_LETSENCRYPT &&
                        $record?->status === SslCertificate::STATUS_ACTIVE
                    )
                    ->action(function (SslCertificate $record) {
                        $record->dispatchRenew();
                        Notification::make()
                            ->title('SSL Renewal Started')
                            ->body('Certificate renewal in progress...')
                            ->info()
                            ->send();
                    }),
                Tables\Actions\Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (?SslCertificate $record) => $record?->status === SslCertificate::STATUS_FAILED)
                    ->action(function (SslCertificate $record) {
                        $record->dispatchIssue();
                        Notification::make()
                            ->title('Retrying SSL Issue')
                            ->body('Attempting to issue certificate again...')
                            ->info()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No SSL certificates')
            ->emptyStateDescription('Issue an SSL certificate to secure your application.')
            ->emptyStateIcon('heroicon-o-lock-closed');
    }
}
