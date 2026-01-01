<?php

namespace App\Services;

use App\Models\HealthMonitor;
use App\Notifications\MonitorDown;
use App\Notifications\MonitorRecovered;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UptimeMonitor
{
    protected array $userAgents = [
        'SiteKit Monitor/1.0',
        'Mozilla/5.0 (compatible; SiteKitBot/1.0)',
    ];

    public function check(HealthMonitor $monitor): HealthCheckResult
    {
        $result = match ($monitor->type) {
            HealthMonitor::TYPE_HTTP => $this->checkHttp($monitor),
            HealthMonitor::TYPE_HTTPS => $this->checkHttps($monitor),
            HealthMonitor::TYPE_TCP => $this->checkTcp($monitor),
            HealthMonitor::TYPE_PING => $this->checkPing($monitor),
            HealthMonitor::TYPE_HEARTBEAT => $this->checkHeartbeat($monitor),
            HealthMonitor::TYPE_SSL_EXPIRY => $this->checkSslExpiry($monitor),
            default => new HealthCheckResult(false, error: 'Unknown monitor type'),
        };

        // Update monitor state
        $this->updateMonitorState($monitor, $result);

        return $result;
    }

    protected function checkHttp(HealthMonitor $monitor): HealthCheckResult
    {
        return $this->performHttpCheck($monitor, false);
    }

    protected function checkHttps(HealthMonitor $monitor): HealthCheckResult
    {
        return $this->performHttpCheck($monitor, true);
    }

    protected function performHttpCheck(HealthMonitor $monitor, bool $verifySsl): HealthCheckResult
    {
        $start = microtime(true);
        $settings = $monitor->settings ?? [];

        try {
            $request = Http::timeout($monitor->timeout_seconds)
                ->withOptions(['verify' => $verifySsl])
                ->withUserAgent($this->userAgents[array_rand($this->userAgents)]);

            // Add custom headers if configured
            if (!empty($settings['headers'])) {
                foreach ($settings['headers'] as $key => $value) {
                    $request->withHeaders([$key => $value]);
                }
            }

            // Add basic auth if configured
            if (!empty($settings['auth_username']) && !empty($settings['auth_password'])) {
                $request->withBasicAuth($settings['auth_username'], $settings['auth_password']);
            }

            // Perform request with configured method
            $method = strtolower($settings['method'] ?? 'get');
            $body = $settings['body'] ?? null;

            $response = match ($method) {
                'post' => $request->post($monitor->url, $body),
                'put' => $request->put($monitor->url, $body),
                'head' => $request->head($monitor->url),
                default => $request->get($monitor->url),
            };

            $responseTime = (microtime(true) - $start) * 1000;
            $success = true;
            $error = null;

            // Check expected status code
            $expectedStatus = $settings['expected_status'] ?? [200, 201, 202, 204, 301, 302];
            if (!is_array($expectedStatus)) {
                $expectedStatus = [$expectedStatus];
            }

            if (!in_array($response->status(), $expectedStatus)) {
                $success = false;
                $error = "Unexpected status code: {$response->status()} (expected: " . implode(',', $expectedStatus) . ")";
            }

            // Check for keyword in response if configured
            if ($success && !empty($settings['keyword'])) {
                if (stripos($response->body(), $settings['keyword']) === false) {
                    $success = false;
                    $error = "Keyword '{$settings['keyword']}' not found in response";
                }
            }

            // Check for keyword that should NOT be present
            if ($success && !empty($settings['keyword_absent'])) {
                if (stripos($response->body(), $settings['keyword_absent']) !== false) {
                    $success = false;
                    $error = "Keyword '{$settings['keyword_absent']}' found in response (should be absent)";
                }
            }

            // Check response time threshold
            if ($success && !empty($settings['max_response_time'])) {
                if ($responseTime > $settings['max_response_time']) {
                    $success = false;
                    $error = "Response time {$responseTime}ms exceeds threshold {$settings['max_response_time']}ms";
                }
            }

            return new HealthCheckResult(
                success: $success,
                responseTime: $responseTime,
                statusCode: $response->status(),
                error: $error,
                metadata: [
                    'content_length' => strlen($response->body()),
                    'headers' => $response->headers(),
                ],
            );
        } catch (ConnectionException $e) {
            return new HealthCheckResult(
                success: false,
                responseTime: (microtime(true) - $start) * 1000,
                error: 'Connection failed: ' . $e->getMessage(),
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                success: false,
                responseTime: (microtime(true) - $start) * 1000,
                error: $e->getMessage(),
            );
        }
    }

    protected function checkTcp(HealthMonitor $monitor): HealthCheckResult
    {
        $start = microtime(true);
        $settings = $monitor->settings ?? [];

        $host = $settings['host'] ?? $monitor->url;
        $port = $settings['port'] ?? 80;

        $connection = @fsockopen(
            $host,
            $port,
            $errno,
            $errstr,
            $monitor->timeout_seconds
        );

        $responseTime = (microtime(true) - $start) * 1000;

        if ($connection) {
            fclose($connection);
            return new HealthCheckResult(
                success: true,
                responseTime: $responseTime,
            );
        }

        return new HealthCheckResult(
            success: false,
            responseTime: $responseTime,
            error: "TCP connection to {$host}:{$port} failed: {$errstr} (errno: {$errno})",
        );
    }

    protected function checkPing(HealthMonitor $monitor): HealthCheckResult
    {
        $start = microtime(true);
        $settings = $monitor->settings ?? [];
        $host = $settings['host'] ?? $monitor->url;
        $count = $settings['count'] ?? 3;

        // Use system ping command
        $output = [];
        $returnCode = 0;

        // Different ping command for different OS
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
            exec("ping -c {$count} -W {$monitor->timeout_seconds} " . escapeshellarg($host) . " 2>&1", $output, $returnCode);
        } else {
            // Windows
            exec("ping -n {$count} -w " . ($monitor->timeout_seconds * 1000) . " " . escapeshellarg($host) . " 2>&1", $output, $returnCode);
        }

        $responseTime = (microtime(true) - $start) * 1000;

        // Parse average response time from ping output
        $avgTime = null;
        $outputStr = implode("\n", $output);
        if (preg_match('/avg[^=]*=\s*([\d.]+)/i', $outputStr, $matches)) {
            $avgTime = (float) $matches[1];
        }

        if ($returnCode === 0) {
            return new HealthCheckResult(
                success: true,
                responseTime: $avgTime ?? $responseTime,
                metadata: ['output' => $outputStr],
            );
        }

        return new HealthCheckResult(
            success: false,
            responseTime: $responseTime,
            error: "Ping failed for {$host}",
            metadata: ['output' => $outputStr],
        );
    }

    protected function checkHeartbeat(HealthMonitor $monitor): HealthCheckResult
    {
        // Heartbeat monitors are passive - they expect to be pinged
        // If we're checking, it means no ping was received in the interval
        $expectedInterval = $monitor->interval_seconds * 1.5; // 50% grace period

        if ($monitor->last_check_at === null) {
            return new HealthCheckResult(
                success: false,
                error: 'No heartbeat received yet',
            );
        }

        $secondsSinceLastPing = now()->diffInSeconds($monitor->last_check_at);

        if ($secondsSinceLastPing > $expectedInterval) {
            return new HealthCheckResult(
                success: false,
                error: "No heartbeat for {$secondsSinceLastPing} seconds (expected every {$monitor->interval_seconds}s)",
            );
        }

        return new HealthCheckResult(success: true);
    }

    protected function checkSslExpiry(HealthMonitor $monitor): HealthCheckResult
    {
        $start = microtime(true);
        $settings = $monitor->settings ?? [];
        $warningDays = $settings['warning_days'] ?? 30;

        try {
            $host = parse_url($monitor->url, PHP_URL_HOST) ?? $monitor->url;
            $port = $settings['port'] ?? 443;

            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $client = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                $monitor->timeout_seconds,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$client) {
                return new HealthCheckResult(
                    success: false,
                    responseTime: (microtime(true) - $start) * 1000,
                    error: "Could not connect to {$host}:{$port} - {$errstr}",
                );
            }

            $params = stream_context_get_params($client);
            fclose($client);

            if (!isset($params['options']['ssl']['peer_certificate'])) {
                return new HealthCheckResult(
                    success: false,
                    responseTime: (microtime(true) - $start) * 1000,
                    error: 'Could not retrieve SSL certificate',
                );
            }

            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            $validTo = $cert['validTo_time_t'] ?? 0;
            $expiresAt = \Carbon\Carbon::createFromTimestamp($validTo);
            $daysUntilExpiry = now()->diffInDays($expiresAt, false);

            $responseTime = (microtime(true) - $start) * 1000;

            if ($daysUntilExpiry < 0) {
                return new HealthCheckResult(
                    success: false,
                    responseTime: $responseTime,
                    error: "SSL certificate has expired",
                    metadata: [
                        'expires_at' => $expiresAt->toIso8601String(),
                        'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
                        'subject' => $cert['subject']['CN'] ?? 'Unknown',
                    ],
                );
            }

            if ($daysUntilExpiry < $warningDays) {
                return new HealthCheckResult(
                    success: false,
                    responseTime: $responseTime,
                    error: "SSL certificate expires in {$daysUntilExpiry} days",
                    metadata: [
                        'expires_at' => $expiresAt->toIso8601String(),
                        'days_until_expiry' => $daysUntilExpiry,
                        'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
                    ],
                );
            }

            return new HealthCheckResult(
                success: true,
                responseTime: $responseTime,
                metadata: [
                    'expires_at' => $expiresAt->toIso8601String(),
                    'days_until_expiry' => $daysUntilExpiry,
                    'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
                    'subject' => $cert['subject']['CN'] ?? 'Unknown',
                ],
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                success: false,
                responseTime: (microtime(true) - $start) * 1000,
                error: $e->getMessage(),
            );
        }
    }

    protected function updateMonitorState(HealthMonitor $monitor, HealthCheckResult $result): void
    {
        $wasUp = $monitor->is_up;

        $monitor->update([
            'is_up' => $result->success,
            'last_check_at' => now(),
            'last_response_time' => $result->responseTime,
            'last_status_code' => $result->statusCode,
            'last_error' => $result->error,
            'consecutive_failures' => $result->success ? 0 : $monitor->consecutive_failures + 1,
        ]);

        // State change detection
        if ($wasUp && !$result->success) {
            // Monitor went down
            $this->handleMonitorDown($monitor, $result);
        } elseif (!$wasUp && $result->success) {
            // Monitor recovered
            $this->handleMonitorRecovered($monitor);
        }
    }

    protected function handleMonitorDown(HealthMonitor $monitor, HealthCheckResult $result): void
    {
        Log::warning("Monitor down: {$monitor->name}", [
            'monitor_id' => $monitor->id,
            'error' => $result->error,
        ]);

        $monitor->update(['last_down_at' => now()]);

        // Send notification to team
        try {
            $monitor->team->owner->notify(new MonitorDown($monitor, $result->error));
        } catch (\Exception $e) {
            Log::error("Failed to send monitor down notification", ['error' => $e->getMessage()]);
        }
    }

    protected function handleMonitorRecovered(HealthMonitor $monitor): void
    {
        Log::info("Monitor recovered: {$monitor->name}", ['monitor_id' => $monitor->id]);

        $downtime = $monitor->last_down_at
            ? now()->diffForHumans($monitor->last_down_at, true)
            : null;

        // Send recovery notification
        try {
            $monitor->team->owner->notify(new MonitorRecovered($monitor, $downtime));
        } catch (\Exception $e) {
            Log::error("Failed to send monitor recovered notification", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Run checks for all active monitors.
     */
    public function checkAll(): array
    {
        $results = [];
        $monitors = HealthMonitor::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_check_at')
                    ->orWhereRaw('TIMESTAMPDIFF(SECOND, last_check_at, NOW()) >= interval_seconds');
            })
            ->get();

        foreach ($monitors as $monitor) {
            $results[$monitor->id] = $this->check($monitor);
        }

        return $results;
    }
}

class HealthCheckResult
{
    public function __construct(
        public bool $success,
        public ?float $responseTime = null,
        public ?int $statusCode = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'response_time' => $this->responseTime,
            'status_code' => $this->statusCode,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
