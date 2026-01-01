<?php

namespace App\Models\Concerns;

use App\Services\ErrorMessageService;
use Illuminate\Support\Carbon;

/**
 * Provides error tracking functionality for models.
 *
 * Required columns: last_error, last_error_at, suggested_action
 */
trait HasErrorTracking
{
    /**
     * Record an error on this model.
     */
    public function recordError(string $error): void
    {
        $errorService = app(ErrorMessageService::class);
        $parsed = $errorService->parse($error);

        $this->update([
            'last_error' => $parsed['title'] . ': ' . $parsed['message'],
            'last_error_at' => now(),
            'suggested_action' => $parsed['action_type'],
            'status' => 'error',
        ]);
    }

    /**
     * Clear the error state.
     */
    public function clearError(): void
    {
        $this->update([
            'last_error' => null,
            'last_error_at' => null,
            'suggested_action' => null,
        ]);
    }

    /**
     * Check if this model has an error.
     */
    public function hasError(): bool
    {
        return !empty($this->last_error);
    }

    /**
     * Get the suggested action for the current error.
     */
    public function getSuggestedActionLabel(): ?string
    {
        return match ($this->suggested_action) {
            'repair_service' => 'Repair Service',
            'restart_service' => 'Restart Service',
            'renew_ssl' => 'Renew Certificate',
            'retry' => 'Try Again',
            default => null,
        };
    }

    /**
     * Get time since error occurred.
     */
    public function getErrorAgeAttribute(): ?string
    {
        if (!$this->last_error_at) {
            return null;
        }

        return Carbon::parse($this->last_error_at)->diffForHumans();
    }

    /**
     * Scope to get models with errors.
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('last_error');
    }

    /**
     * Scope to get models without errors.
     */
    public function scopeWithoutErrors($query)
    {
        return $query->whereNull('last_error');
    }
}
