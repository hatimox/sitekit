<x-filament-panels::page>
    @livewire(Laravel\Jetstream\Http\Livewire\UpdateTeamNameForm::class, compact('team'))

    <x-section-border/>

    @if(config('ai.enabled'))
        @livewire(\App\Livewire\AiSettings::class, compact('team'))

        <x-section-border/>
    @endif

    @livewire(\App\Livewire\NotificationSettings::class, compact('team'))

    <x-section-border/>

    @livewire(\App\Livewire\MetricsSettings::class, compact('team'))

    <x-section-border/>

    @livewire(\App\Livewire\BackupStorageSettings::class, compact('team'))

    @livewire(Laravel\Jetstream\Http\Livewire\TeamMemberManager::class, compact('team'))

    @if (Gate::check('delete', $team) && ! $team->personal_team)
        <x-section-border/>

        @livewire(Laravel\Jetstream\Http\Livewire\DeleteTeamForm::class, compact('team'))
    @endif
</x-filament-panels::page>
