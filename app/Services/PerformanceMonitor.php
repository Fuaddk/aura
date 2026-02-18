<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PerformanceMonitor
{
    private const CACHE_PREFIX = 'perf_metrics_';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Track request performance
     *
     * @param string $endpoint Request endpoint/route name
     * @param callable $callback Function to measure
     * @param array $context Additional context
     * @return mixed Result from callback
     */
    public function track(string $endpoint, callable $callback, array $context = []): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            $result = $callback();

            $this->recordMetric($endpoint, [
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'memory_mb' => (memory_get_usage(true) - $startMemory) / 1024 / 1024,
                'status' => 'success',
                'context' => $context,
                'timestamp' => now()->toISOString(),
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->recordMetric($endpoint, [
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'memory_mb' => (memory_get_usage(true) - $startMemory) / 1024 / 1024,
                'status' => 'error',
                'error' => $e->getMessage(),
                'context' => $context,
                'timestamp' => now()->toISOString(),
            ]);

            throw $e;
        }
    }

    /**
     * Record a metric
     */
    private function recordMetric(string $endpoint, array $data): void
    {
        $key = self::CACHE_PREFIX . $endpoint;
        $metrics = Cache::get($key, []);

        $metrics[] = $data;

        // Keep only last 100 metrics per endpoint
        if (count($metrics) > 100) {
            $metrics = array_slice($metrics, -100);
        }

        Cache::put($key, $metrics, self::CACHE_TTL);

        // Log slow requests (>1000ms)
        if ($data['duration_ms'] > 1000) {
            Log::warning('Slow request detected', [
                'endpoint' => $endpoint,
                'duration_ms' => round($data['duration_ms'], 2),
                'memory_mb' => round($data['memory_mb'], 2),
                'context' => $data['context'] ?? [],
            ]);
        }
    }

    /**
     * Get metrics for an endpoint
     */
    public function getMetrics(string $endpoint): array
    {
        return Cache::get(self::CACHE_PREFIX . $endpoint, []);
    }

    /**
     * Get aggregated statistics for an endpoint
     */
    public function getStats(string $endpoint): array
    {
        $metrics = $this->getMetrics($endpoint);

        if (empty($metrics)) {
            return [];
        }

        $durations = array_column($metrics, 'duration_ms');
        $memories = array_column($metrics, 'memory_mb');
        $successCount = count(array_filter($metrics, fn($m) => ($m['status'] ?? 'error') === 'success'));

        return [
            'total_requests' => count($metrics),
            'success_rate' => round(($successCount / count($metrics)) * 100, 2),
            'avg_duration_ms' => round(array_sum($durations) / count($durations), 2),
            'max_duration_ms' => round(max($durations), 2),
            'min_duration_ms' => round(min($durations), 2),
            'p95_duration_ms' => round($this->percentile($durations, 95), 2),
            'avg_memory_mb' => round(array_sum($memories) / count($memories), 2),
            'max_memory_mb' => round(max($memories), 2),
        ];
    }

    /**
     * Get all tracked endpoints
     */
    public function getAllEndpoints(): array
    {
        // This is a simplified version - in production use a database table
        $keys = Cache::get('perf_endpoints', []);
        return array_map(fn($k) => str_replace(self::CACHE_PREFIX, '', $k), $keys);
    }

    /**
     * Clear metrics for an endpoint
     */
    public function clearMetrics(string $endpoint): void
    {
        Cache::forget(self::CACHE_PREFIX . $endpoint);
    }

    /**
     * Clear all metrics
     */
    public function clearAll(): void
    {
        foreach ($this->getAllEndpoints() as $endpoint) {
            $this->clearMetrics($endpoint);
        }
        Cache::forget('perf_endpoints');
    }

    /**
     * Calculate percentile
     */
    private function percentile(array $values, float $percentile): float
    {
        sort($values);
        $index = (count($values) - 1) * ($percentile / 100);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        return $values[$lower] + ($values[$upper] - $values[$lower]) * $weight;
    }

    /**
     * Track AI API call performance
     */
    public function trackAICall(string $model, string $operation, callable $callback): mixed
    {
        return $this->track("ai.{$model}.{$operation}", $callback, [
            'type' => 'ai_api',
            'model' => $model,
            'operation' => $operation,
        ]);
    }

    /**
     * Track database query performance
     */
    public function trackQuery(string $queryName, callable $callback): mixed
    {
        return $this->track("db.{$queryName}", $callback, [
            'type' => 'database',
        ]);
    }
}
