<x-filament-panels::page>
    @if($this->record->isActive())
        @include('filament.components.ftp-cache-tip')
    @endif

    {{ $this->infolist }}

    {{-- File Manager Section --}}
    @if($this->record->isActive() && $this->record->server->isActive())
        <div class="mt-6">
            <livewire:file-manager :web-app="$this->record" />
        </div>
    @endif

    @if (count($relationManagers = $this->getRelationManagers()))
        <x-filament-panels::resources.relation-managers
            :active-manager="$this->activeRelationManager"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        />
    @endif
</x-filament-panels::page>
