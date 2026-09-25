<?php

declare(strict_types=1);

namespace App\Support;

class Config
{
    private static array $config = [];

    public static function load(array $defaults = []): void
    {
        self::$config = $defaults;

        // Load from .env if exists
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') === false || strpos($line, '#') === 0) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                putenv(trim($key) . '=' . trim($value));
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return self::$config[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = strtolower((string)self::get($key, $default ? 'true' : 'false'));
        return $value === 'true' || $value === '1' || $value === 'yes';
    }

    public static function getArray(string $key, array $default = []): array
    {
        $value = self::get($key, null);
        if ($value === null) {
            return $default;
        }
        if (is_array($value)) {
            return $value;
        }
        return array_map('trim', explode(',', (string)$value));
    }
}
