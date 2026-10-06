<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
    }

    public function testGetReturnsBoundValue(): void
    {
        $key = 'test.' . uniqid();
        $this->container->bind($key, fn() => 'value');

        $this->assertSame('value', $this->container->get($key));
    }

    public function testNonSingletonFactoryRunsOnEveryGet(): void
    {
        $key = 'test.' . uniqid();
        $calls = 0;
        $this->container->bind($key, function () use (&$calls) {
            $calls++;
            return $calls;
        });

        $this->assertSame(1, $this->container->get($key));
        $this->assertSame(2, $this->container->get($key));
    }

    public function testSingletonFactoryRunsOnlyOnce(): void
    {
        $key = 'test.' . uniqid();
        $calls = 0;
        $this->container->singleton($key, function () use (&$calls) {
            $calls++;
            return $calls;
        });

        $this->assertSame(1, $this->container->get($key));
        $this->assertSame(1, $this->container->get($key));
        $this->assertSame(1, $calls);
    }

    /**
     * Regression test: the cache used to use isset(), which treats a stored
     * null/false as "not cached" and re-invokes the factory on every get().
     */
    public function testSingletonCachesFalsyValues(): void
    {
        $key = 'test.' . uniqid();
        $calls = 0;
        $this->container->singleton($key, function () use (&$calls) {
            $calls++;
            return null;
        });

        $this->assertNull($this->container->get($key));
        $this->assertNull($this->container->get($key));
        $this->assertSame(1, $calls, 'Factory should only run once even when it returns null');
    }

    public function testSingletonCachesFalseValue(): void
    {
        $key = 'test.' . uniqid();
        $calls = 0;
        $this->container->singleton($key, function () use (&$calls) {
            $calls++;
            return false;
        });

        $this->assertFalse($this->container->get($key));
        $this->assertFalse($this->container->get($key));
        $this->assertSame(1, $calls);
    }

    public function testGetThrowsForUnboundKey(): void
    {
        $this->expectException(\Exception::class);
        $this->container->get('missing.' . uniqid());
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $this->assertSame(Container::getInstance(), Container::getInstance());
    }
}
