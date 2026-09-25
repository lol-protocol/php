<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every controller/method a route config points at must exist — otherwise
 * that URL shape dispatches fine and then dies with a 500 at runtime.
 */
class RouteTargetsExistTest extends TestCase
{
    private const ROUTES_DIR = __DIR__ . '/../../../config/routes';

    /** @return array<string, array{string, string}> */
    public static function routeTargets(): array
    {
        $targets = [];

        foreach (['genealogy', 'pos'] as $site) {
            $config = require self::ROUTES_DIR . "/{$site}.php";

            foreach ($config['by_length'] ?? [] as $entry) {
                self::collect($targets, $site, $entry['controller'], 'show', $entry['actions'] ?? []);
            }
            foreach (['place', 'order'] as $section) {
                if (isset($config[$section])) {
                    $entry = $config[$section];
                    self::collect($targets, $site, $entry['controller'], 'show', $entry['actions'] ?? []);
                }
            }
            foreach (['literal', 'reserved'] as $section) {
                foreach ($config[$section] ?? [] as $entry) {
                    self::collect($targets, $site, $entry['controller'], $entry['method'] ?? 'index', $entry['actions'] ?? []);
                }
            }
        }

        return $targets;
    }

    private static function collect(array &$targets, string $site, string $controller, string $default, array $actions): void
    {
        foreach (array_merge([$default], array_values($actions)) as $method) {
            $targets["{$site}: {$controller}::{$method}"] = [$controller, $method];
        }
    }

    #[DataProvider('routeTargets')]
    public function testRouteTargetExists(string $controller, string $method): void
    {
        $class = 'App\\Controllers\\' . $controller;

        $this->assertTrue(class_exists($class), "Missing controller {$class}");
        $this->assertTrue(method_exists($class, $method), "Missing method {$class}::{$method}");
    }
}
