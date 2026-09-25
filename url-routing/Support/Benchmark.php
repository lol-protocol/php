<?php

declare(strict_types=1);

namespace App\Support;

class Benchmark
{
    private static array $marks = [];
    private static array $results = [];

    public static function start(string $label): void
    {
        self::$marks[$label] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
        ];
    }

    public static function end(string $label): array
    {
        if (!isset(self::$marks[$label])) {
            return [];
        }

        $startTime = self::$marks[$label]['time'];
        $startMemory = self::$marks[$label]['memory'];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $result = [
            'label' => $label,
            'duration_ms' => round(($endTime - $startTime) * 1000, 3),
            'memory_used_kb' => round(($endMemory - $startMemory) / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        self::$results[$label] = $result;
        unset(self::$marks[$label]);

        return $result;
    }

    public static function measure(string $label, callable $callback): mixed
    {
        self::start($label);
        $result = $callback();
        self::end($label);
        return $result;
    }

    public static function getResults(): array
    {
        return self::$results;
    }

    public static function getResult(string $label): array|null
    {
        return self::$results[$label] ?? null;
    }

    public static function report(): string
    {
        if (empty(self::$results)) {
            return 'No benchmarks recorded.';
        }

        $report = "Performance Benchmark Report\n";
        $report .= str_repeat('=', 60) . "\n\n";

        foreach (self::$results as $result) {
            $report .= "Benchmark: {$result['label']}\n";
            $report .= "  Duration: {$result['duration_ms']}ms\n";
            $report .= "  Memory Used: {$result['memory_used_kb']}KB\n";
            $report .= "  Peak Memory: {$result['peak_memory_mb']}MB\n";
            $report .= "\n";
        }

        return $report;
    }

    public static function reset(): void
    {
        self::$marks = [];
        self::$results = [];
    }

    public static function getMetric(string $label, string $metric): float|null
    {
        if (!isset(self::$results[$label])) {
            return null;
        }

        return match ($metric) {
            'duration' => self::$results[$label]['duration_ms'],
            'memory' => self::$results[$label]['memory_used_kb'],
            'peak' => self::$results[$label]['peak_memory_mb'],
            default => null,
        };
    }
}
