<x-filament-panels::page>
    @if($this->record->isActive())
        @include('filament.components.ftp-cache-tip')
    @endif

    {{ $this->infolist }}

    @if (count($relationManagers = $this->getRelationManagers()))
        <x-filament-panels::resources.relation-managers
            :active-manager="$this->activeRelationManager"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        />
    @endif
</x-filament-panels::page>
