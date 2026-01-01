<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresServerForNavigation;
use App\Filament\Resources\CronJobResource\Pages;
use App\Models\CronJob;
use App\Models\WebApp;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CronJobResource extends Resource
{
    use RequiresServerForNavigation;

    protected static ?string $model = CronJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Applications';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Cron Jobs';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cron Job Details')
                    ->description('Schedule commands to run automatically at specific intervals. Perfect for Laravel schedulers, backups, and maintenance tasks.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Laravel Scheduler'),
                        Forms\Components\Select::make('server_id')
                            ->relationship('server', 'name', fn (Builder $query) =>
                                $query->where('team_id', Filament::getTenant()?->id)
                                    ->where('status', 'active'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive(),
                        Forms\Components\Select::make('web_app_id')
                            ->label('Web Application')
                            ->options(fn (Get $get) => WebApp::where('server_id', $get('server_id'))->pluck('name', 'id'))
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $app = WebApp::find($state);
                                    if ($app) {
                                        $set('user', $app->system_user);
                                        $set('command', "cd {$app->root_path}/current && php artisan schedule:run >> /dev/null 2>&1");
                                    }
                                }
                            }),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Schedule')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_cron_help')
                            ->label('Cron syntax help')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Explain cron job syntax. What do the 5 fields (minute, hour, day, month, weekday) mean? Give examples for: every 5 minutes, every hour, daily at 3am, weekly on Monday, first of every month. Also explain special characters like *, /, -, and comma.")',
                            ]),
                        Forms\Components\Actions\Action::make('ai_laravel_scheduler')
                            ->label('Laravel scheduler')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("How does Laravel\'s task scheduler work? What cron expression should I use to run \'php artisan schedule:run\' every minute? What are best practices for scheduled tasks in Laravel?")',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('command')
                            ->required()
                            ->placeholder('php /path/to/artisan schedule:run')
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('schedule')
                                    ->required()
                                    ->default('* * * * *')
                                    ->regex('/^(\*|(\d+|\*)(\/\d+)?|\d+(-\d+)?(,\d+(-\d+)?)*)\s+(\*|(\d+|\*)(\/\d+)?|\d+(-\d+)?(,\d+(-\d+)?)*)\s+(\*|(\d+|\*)(\/\d+)?|\d+(-\d+)?(,\d+(-\d+)?)*)\s+(\*|(\d+|\*)(\/\d+)?|\d+(-\d+)?(,\d+(-\d+)?)*)\s+(\*|(\d+|\*)(\/\d+)?|\d+(-\d+)?(,\d+(-\d+)?)*)$/')
                                    ->helperText(new HtmlString('minute hour day month weekday. <a href="/app/documentation?topic=cron-jobs" class="text-primary-600 hover:underline" wire:navigate>Learn more</a>')),
                                Forms\Components\Select::make('frequency')
                                    ->label('Or choose preset')
                                    ->options([
                                        '* * * * *' => 'Every minute',
                                        '*/5 * * * *' => 'Every 5 minutes',
                                        '*/15 * * * *' => 'Every 15 minutes',
                                        '0 * * * *' => 'Every hour',
                                        '0 */6 * * *' => 'Every 6 hours',
                                        '0 0 * * *' => 'Daily at midnight',
                                        '0 0 * * 0' => 'Weekly on Sunday',
                                    ])
                                    ->reactive()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(fn ($state, Set $set) => $set('schedule', $state)),
                            ]),
                        Forms\Components\TextInput::make('user')
                            ->required()
                            ->default('sitekit')
                            ->helperText('System user to run the command as'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable(),
                Tables\Columns\TextColumn::make('schedule')
                    ->badge()
                    ->formatStateUsing(fn (CronJob $record): string => $record->schedule_description),
                Tables\Columns\TextColumn::make('command')
                    ->limit(50)
                    ->tooltip(fn (CronJob $record): string => $record->command),
                Tables\Columns\TextColumn::make('user')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('last_run_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'success',
                        'danger' => 'failed',
                        'warning' => 'running',
                        'gray' => fn ($state) => empty($state),
                    ])
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : 'Never run')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Next Run')
                    ->dateTime('M j, g:i A')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('webApp.name')
                    ->label('Web App')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                // AI Troubleshoot for failed cron jobs
                Tables\Actions\Action::make('ai_troubleshoot')
                    ->label('AI Help')
                    ->icon('heroicon-o-sparkles')
                    ->color('danger')
                    ->visible(fn (CronJob $record) => $record->last_run_status === 'failed' && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (CronJob $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My cron job \'' . e($record->name) . '\' failed. Command: ' . e($record->command) . '. Schedule: ' . e($record->schedule) . '. Exit code: ' . e($record->last_run_exit_code ?? 'Unknown') . '. Output: ' . e(\Illuminate\Support\Str::limit($record->last_run_output ?? 'No output', 200)) . '. Help me debug this issue.")',
                    ]),

                // AI Explain schedule
                Tables\Actions\Action::make('ai_explain')
                    ->label('Explain')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn () => config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (CronJob $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Explain this cron schedule: ' . e($record->schedule) . '. When will it run? How often? Give me specific examples of the next few run times.")',
                    ]),

                Tables\Actions\Action::make('runNow')
                    ->label('Run Now')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Run Cron Job Now')
                    ->modalDescription('This will execute the cron job command immediately on the server.')
                    ->modalSubmitActionLabel('Run Now')
                    ->action(function (CronJob $record) {
                        $record->runNow();
                        Notification::make()
                            ->title('Cron job queued')
                            ->body("The command is being executed on the server.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('viewOutput')
                    ->label('Output')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (CronJob $record) => $record->last_run_at !== null)
                    ->modalHeading('Cron Job Output')
                    ->modalDescription(fn (CronJob $record) => "Last run: " . ($record->last_run_at?->diffForHumans() ?? 'Never'))
                    ->modalContent(fn (CronJob $record) => view('filament.components.cron-output', [
                        'output' => $record->last_run_output ?? 'No output recorded',
                        'status' => $record->last_run_status,
                        'exitCode' => $record->last_run_exit_code,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (CronJob $record) => $record->is_active ? 'Disable' : 'Enable')
                    ->icon(fn (CronJob $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (CronJob $record) => $record->is_active ? 'warning' : 'success')
                    ->action(function (CronJob $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        $record->syncToServer();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (CronJob $record) => $record->syncToServer()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('No cron jobs scheduled')
            ->emptyStateDescription('Schedule commands to run automatically. For Laravel apps, add "php artisan schedule:run" to execute your scheduled tasks every minute.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Cron Job')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCronJobs::route('/'),
            'create' => Pages\CreateCronJob::route('/create'),
            'view' => Pages\ViewCronJob::route('/{record}'),
            'edit' => Pages\EditCronJob::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
