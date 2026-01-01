<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ServiceStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'cpu_percent',
        'memory_mb',
        'uptime_seconds',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'cpu_percent' => 'decimal:2',
            'memory_mb' => 'integer',
            'uptime_seconds' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get stats for a specific time period.
     */
    public static function forPeriod(string $serviceId, Carbon $start, Carbon $end)
    {
        return static::where('service_id', $serviceId)
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Get average stats for the last N hours.
     */
    public static function averageForLastHours(string $serviceId, int $hours = 24): array
    {
        $stats = static::where('service_id', $serviceId)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->get();

        if ($stats->isEmpty()) {
            return [
                'cpu_percent' => 0,
                'memory_mb' => 0,
            ];
        }

        return [
            'cpu_percent' => round($stats->avg('cpu_percent'), 2),
            'memory_mb' => round($stats->avg('memory_mb')),
        ];
    }

    /**
     * Get chart data for a service (for Filament charts).
     */
    public static function getChartData(string $serviceId, int $hours = 24): array
    {
        $stats = static::where('service_id', $serviceId)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at')
            ->get();

        return [
            'labels' => $stats->pluck('recorded_at')->map(fn ($dt) => $dt->format('H:i'))->toArray(),
            'cpu' => $stats->pluck('cpu_percent')->toArray(),
            'memory' => $stats->pluck('memory_mb')->toArray(),
        ];
    }

    /**
     * Get metrics for chart with flexible period options.
     *
     * @param string $serviceId
     * @param string $period One of: '1h', '6h', '24h', '7d', '30d'
     * @return array{labels: array, cpu: array, memory: array, uptime: array}
     */
    public static function getMetricsForChart(string $serviceId, string $period = '24h'): array
    {
        $start = match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $format = match ($period) {
            '1h', '6h' => 'H:i',
            '24h' => 'H:i',
            '7d' => 'D H:i',
            '30d' => 'M d',
            default => 'H:i',
        };

        $query = static::where('service_id', $serviceId)
            ->where('recorded_at', '>=', $start)
            ->orderBy('recorded_at');

        // For longer periods, aggregate data to reduce points
        if (in_array($period, ['7d', '30d'])) {
            $stats = $query->get();

            // Group by hour for 7d, by day for 30d
            $groupFormat = $period === '7d' ? 'Y-m-d H:00' : 'Y-m-d';
            $grouped = $stats->groupBy(fn ($stat) => $stat->recorded_at->format($groupFormat));

            $labels = [];
            $cpu = [];
            $memory = [];
            $uptime = [];

            foreach ($grouped as $key => $group) {
                $labels[] = Carbon::parse($key)->format($format);
                $cpu[] = round($group->avg('cpu_percent'), 2);
                $memory[] = round($group->avg('memory_mb'));
                $uptime[] = $group->last()?->uptime_seconds ?? 0;
            }

            return compact('labels', 'cpu', 'memory', 'uptime');
        }

        $stats = $query->get();

        return [
            'labels' => $stats->pluck('recorded_at')->map(fn ($dt) => $dt->format($format))->toArray(),
            'cpu' => $stats->pluck('cpu_percent')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'memory' => $stats->pluck('memory_mb')->toArray(),
            'uptime' => $stats->pluck('uptime_seconds')->toArray(),
        ];
    }

    /**
     * Prune old stats based on retention days.
     */
    public static function pruneOlderThan(int $days): int
    {
        return static::where('recorded_at', '<', now()->subDays($days))->delete();
    }
}
