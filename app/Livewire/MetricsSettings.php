<?php

namespace App\Livewire;

use Livewire\Component;

class MetricsSettings extends Component
{
    public $team;
    public $metrics_interval_seconds = 300;
    public $metrics_retention_days = 30;

    public function mount($team)
    {
        $this->team = $team;
        $this->metrics_interval_seconds = $team->metrics_interval_seconds ?? 300;
        $this->metrics_retention_days = $team->metrics_retention_days ?? 30;
    }

    public function updateMetricsSettings()
    {
        $this->validate([
            'metrics_interval_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'metrics_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [
            'metrics_interval_seconds.min' => 'Metrics interval must be at least 60 seconds (1 minute).',
            'metrics_interval_seconds.max' => 'Metrics interval cannot exceed 3600 seconds (1 hour).',
            'metrics_retention_days.min' => 'Retention period must be at least 1 day.',
            'metrics_retention_days.max' => 'Retention period cannot exceed 365 days.',
        ]);

        $this->team->update([
            'metrics_interval_seconds' => $this->metrics_interval_seconds,
            'metrics_retention_days' => $this->metrics_retention_days,
        ]);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.metrics-settings');
    }
}
