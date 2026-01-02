<?php

namespace App\Services;

use App\Models\Server;
use App\Models\WebApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortAllocationService
{
    /**
     * Default port range for Node.js applications.
     */
    protected int $minPort;
    protected int $maxPort;

    public function __construct()
    {
        $this->minPort = config('sitekit.nodejs_port_min', 3000);
        $this->maxPort = config('sitekit.nodejs_port_max', 3999);
    }

    /**
     * Allocate a single port for a Node.js application on a server.
     */
    public function allocate(Server $server): int
    {
        return DB::transaction(function () use ($server) {
            $usedPorts = $this->getUsedPorts($server);

            for ($port = $this->minPort; $port <= $this->maxPort; $port++) {
                if (!in_array($port, $usedPorts)) {
                    Log::info("Port {$port} allocated for server {$server->id}");
                    return $port;
                }
            }

            throw new \RuntimeException(
                "No available ports in range {$this->minPort}-{$this->maxPort} for server {$server->id}"
            );
        });
    }

    /**
     * Allocate multiple consecutive ports for a monorepo application.
     */
    public function allocateMultiple(Server $server, int $count): array
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('Port count must be greater than 0');
        }

        if ($count > 100) {
            throw new \InvalidArgumentException('Cannot allocate more than 100 ports at once');
        }

        return DB::transaction(function () use ($server, $count) {
            $usedPorts = $this->getUsedPorts($server);
            $allocatedPorts = [];
            $consecutiveStart = null;
            $consecutiveCount = 0;

            // Try to find consecutive ports first
            for ($port = $this->minPort; $port <= $this->maxPort; $port++) {
                if (!in_array($port, $usedPorts)) {
                    if ($consecutiveStart === null) {
                        $consecutiveStart = $port;
                        $consecutiveCount = 1;
                    } else {
                        $consecutiveCount++;
                    }

                    if ($consecutiveCount === $count) {
                        $allocatedPorts = range($consecutiveStart, $port);
                        break;
                    }
                } else {
                    $consecutiveStart = null;
                    $consecutiveCount = 0;
                }
            }

            // If consecutive ports not found, allocate non-consecutive
            if (empty($allocatedPorts)) {
                for ($port = $this->minPort; $port <= $this->maxPort && count($allocatedPorts) < $count; $port++) {
                    if (!in_array($port, $usedPorts)) {
                        $allocatedPorts[] = $port;
                    }
                }
            }

            if (count($allocatedPorts) < $count) {
                throw new \RuntimeException(
                    "Could not allocate {$count} ports for server {$server->id}. Only " . count($allocatedPorts) . " available."
                );
            }

            Log::info("Allocated {$count} ports for server {$server->id}", ['ports' => $allocatedPorts]);

            return $allocatedPorts;
        });
    }

    /**
     * Release a port when a web app is deleted or changed to non-Node.js.
     */
    public function release(WebApp $webApp): void
    {
        if ($webApp->node_port) {
            Log::info("Port {$webApp->node_port} released for server {$webApp->server_id}");
            // The port is automatically released when node_port is set to null
            // This method exists for explicit logging and future extensions
        }

        // Also release any monorepo ports
        if (!empty($webApp->node_processes)) {
            $ports = collect($webApp->node_processes)->pluck('port')->filter()->values();
            if ($ports->isNotEmpty()) {
                Log::info("Monorepo ports released for server {$webApp->server_id}", [
                    'ports' => $ports->toArray(),
                ]);
            }
        }
    }

    /**
     * Check if a specific port is available on a server.
     */
    public function isAvailable(Server $server, int $port): bool
    {
        if ($port < $this->minPort || $port > $this->maxPort) {
            return false;
        }

        return !in_array($port, $this->getUsedPorts($server));
    }

    /**
     * Get the next available port on a server (for preview purposes).
     */
    public function getNextAvailable(Server $server): ?int
    {
        $usedPorts = $this->getUsedPorts($server);

        for ($port = $this->minPort; $port <= $this->maxPort; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        return null;
    }

    /**
     * Get count of available ports on a server.
     */
    public function getAvailableCount(Server $server): int
    {
        $usedPorts = $this->getUsedPorts($server);
        $totalPorts = $this->maxPort - $this->minPort + 1;

        return $totalPorts - count($usedPorts);
    }

    /**
     * Get all used ports on a server.
     */
    public function getUsedPorts(Server $server): array
    {
        // Get ports from node_port column
        $directPorts = WebApp::where('server_id', $server->id)
            ->whereNotNull('node_port')
            ->pluck('node_port')
            ->toArray();

        // Get ports from node_processes JSON (for monorepos)
        $monorepoPorts = WebApp::where('server_id', $server->id)
            ->whereNotNull('node_processes')
            ->get()
            ->flatMap(function ($app) {
                if (empty($app->node_processes)) {
                    return [];
                }
                return collect($app->node_processes)
                    ->pluck('port')
                    ->filter()
                    ->values();
            })
            ->toArray();

        return array_unique(array_merge($directPorts, $monorepoPorts));
    }

    /**
     * Get port usage statistics for a server.
     */
    public function getUsageStats(Server $server): array
    {
        $usedPorts = $this->getUsedPorts($server);
        $totalPorts = $this->maxPort - $this->minPort + 1;

        return [
            'total' => $totalPorts,
            'used' => count($usedPorts),
            'available' => $totalPorts - count($usedPorts),
            'usage_percent' => round((count($usedPorts) / $totalPorts) * 100, 1),
            'port_range' => "{$this->minPort}-{$this->maxPort}",
        ];
    }

    /**
     * Validate that a port is in the allowed range.
     */
    public function isValidPort(int $port): bool
    {
        return $port >= $this->minPort && $port <= $this->maxPort;
    }

    /**
     * Get the configured port range.
     */
    public function getPortRange(): array
    {
        return [
            'min' => $this->minPort,
            'max' => $this->maxPort,
        ];
    }
}
