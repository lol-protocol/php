<?php

declare(strict_types=1);

namespace App\Support;

class Config
{
    /** @var array<string, mixed> */
    private static array $config = [];

    /**
     * @param array<string, mixed> $defaults
     */
    public static function load(array $defaults = [], ?string $envFile = null): void
    {
        self::$config = $defaults;

        $envFile ??= dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') === false || strpos($line, '#') === 0) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                // The real environment wins: .env only fills in what's unset.
                if (getenv($key) !== false) {
                    continue;
                }
                putenv($key . '=' . trim(trim($value), '"\''));
            }
        }

        self::fijarZonaHoraria();
    }

    /** The zone every date() and strtotime() of a process uses when none is configured. */
    public const ZONA_HORARIA = 'UTC';

    /**
     * Pins the time zone from APP_TIMEZONE (UTC by default), so the web server and the cron jobs read and write the
     * dates of the database in the same one. The columns are TIMESTAMP without a zone: written by PHP-FPM with one
     * php.ini and read by a CLI script with another, a pedido de retiro would look hours older or younger than it is
     * and the automatic hiding would fire early or late. Changing it on a site that already has rows moves their dates.
     */
    private static function fijarZonaHoraria(): void
    {
        $zona = (string)self::get('APP_TIMEZONE', self::ZONA_HORARIA);
        if (!in_array($zona, \DateTimeZone::listIdentifiers(), true)) {
            $zona = self::ZONA_HORARIA;
        }
        date_default_timezone_set($zona);
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

    /**
     * @param array<array-key, mixed> $default
     * @return array<array-key, mixed>
     */
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
