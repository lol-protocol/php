<?php

declare(strict_types=1);

namespace App\Support;

/**
 * In-process request-scoped cache. Static state lives only for the lifetime
 * of the PHP process handling the current request (or worker, under
 * PHP-FPM/Swoole) — it is not shared across requests or servers. Use it to
 * avoid recomputing the same query within a single request, not as a
 * substitute for a real shared cache (Redis/Memcached).
 */
class QueryCache
{
    private static array $cache = [];
    private static array $tags = [];
    private static array $dependencies = [];

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (isset(self::$cache[$key])) {
            $item = self::$cache[$key];
            if ($item['expiry'] > time()) {
                return $item['value'];
            }
            unset(self::$cache[$key]);
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function set(string $key, mixed $value, int $ttl = 3600, array $tags = []): void
    {
        self::$cache[$key] = [
            'value' => $value,
            'expiry' => time() + $ttl,
            'tags' => $tags,
            'created' => time(),
        ];

        foreach ($tags as $tag) {
            if (!isset(self::$tags[$tag])) {
                self::$tags[$tag] = [];
            }
            self::$tags[$tag][] = $key;
        }
    }

    public static function get(string $key): mixed
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $item = self::$cache[$key];
        if ($item['expiry'] <= time()) {
            self::forget($key);
            return null;
        }

        return $item['value'];
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function forget(string $key): void
    {
        if (isset(self::$cache[$key])) {
            $tags = self::$cache[$key]['tags'];
            unset(self::$cache[$key]);

            foreach ($tags as $tag) {
                if (isset(self::$tags[$tag])) {
                    self::$tags[$tag] = array_filter(
                        self::$tags[$tag],
                        fn($k) => $k !== $key
                    );
                }
            }
        }
    }

    public static function flush(array $tags = []): void
    {
        if (empty($tags)) {
            self::$cache = [];
            self::$tags = [];
            return;
        }

        $keysToDelete = [];
        foreach ($tags as $tag) {
            if (isset(self::$tags[$tag])) {
                $keysToDelete = array_merge($keysToDelete, self::$tags[$tag]);
                unset(self::$tags[$tag]);
            }
        }

        foreach (array_unique($keysToDelete) as $key) {
            unset(self::$cache[$key]);
        }
    }

    public static function addDependency(string $key, string $dependency): void
    {
        if (!isset(self::$dependencies[$key])) {
            self::$dependencies[$key] = [];
        }
        self::$dependencies[$key][] = $dependency;
    }

    public static function invalidateDependencies(string $key): void
    {
        if (isset(self::$dependencies[$key])) {
            foreach (self::$dependencies[$key] as $dependent) {
                self::forget($dependent);
            }
        }

        foreach (self::$dependencies as $depKey => $deps) {
            if (in_array($key, $deps, true)) {
                self::forget($depKey);
            }
        }
    }

    public static function getStats(): array
    {
        $totalItems = count(self::$cache);
        $totalSize = 0;
        $expiredCount = 0;

        foreach (self::$cache as $item) {
            $totalSize += strlen(json_encode($item['value']));
            if ($item['expiry'] <= time()) {
                $expiredCount++;
            }
        }

        return [
            'total_items' => $totalItems,
            'expired_items' => $expiredCount,
            'active_items' => $totalItems - $expiredCount,
            'total_size_kb' => round($totalSize / 1024, 2),
            'tags' => count(self::$tags),
            'dependencies' => count(self::$dependencies),
        ];
    }

    public static function cleanup(): void
    {
        $now = time();
        $keysToDelete = [];

        foreach (self::$cache as $key => $item) {
            if ($item['expiry'] <= $now) {
                $keysToDelete[] = $key;
            }
        }

        foreach ($keysToDelete as $key) {
            self::forget($key);
        }
    }

    public static function getSize(): int
    {
        return count(self::$cache);
    }
}
