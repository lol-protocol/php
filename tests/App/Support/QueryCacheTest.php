<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\QueryCache;
use PHPUnit\Framework\TestCase;

class QueryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        QueryCache::flush();
    }

    protected function tearDown(): void
    {
        QueryCache::flush();
    }

    public function testSetAndGet(): void
    {
        QueryCache::set('key1', 'value1');

        $this->assertSame('value1', QueryCache::get('key1'));
        $this->assertTrue(QueryCache::has('key1'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull(QueryCache::get('missing'));
        $this->assertFalse(QueryCache::has('missing'));
    }

    public function testExpiredEntryIsTreatedAsMissing(): void
    {
        QueryCache::set('expiring', 'value', -1);

        $this->assertNull(QueryCache::get('expiring'));
        $this->assertFalse(QueryCache::has('expiring'));
    }

    public function testRememberCachesCallbackResult(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'computed';
        };

        $first = QueryCache::remember('remembered', 60, $callback);
        $second = QueryCache::remember('remembered', 60, $callback);

        $this->assertSame('computed', $first);
        $this->assertSame('computed', $second);
        $this->assertSame(1, $calls);
    }

    public function testForgetRemovesEntry(): void
    {
        QueryCache::set('to-forget', 'value');
        QueryCache::forget('to-forget');

        $this->assertNull(QueryCache::get('to-forget'));
    }

    public function testFlushByTagRemovesOnlyMatchingEntries(): void
    {
        QueryCache::set('a', '1', 60, ['products']);
        QueryCache::set('b', '2', 60, ['orders']);

        QueryCache::flush(['products']);

        $this->assertNull(QueryCache::get('a'));
        $this->assertSame('2', QueryCache::get('b'));
    }

    public function testInvalidateDependenciesForgetsDependents(): void
    {
        QueryCache::set('parent', 'p');
        QueryCache::set('child', 'c');
        QueryCache::addDependency('parent', 'child');

        QueryCache::invalidateDependencies('parent');

        $this->assertNull(QueryCache::get('child'));
    }

    public function testGetSizeReflectsStoredEntries(): void
    {
        QueryCache::set('one', 1);
        QueryCache::set('two', 2);

        $this->assertSame(2, QueryCache::getSize());
    }

    public function testGetStatsReportsActiveAndExpiredItems(): void
    {
        QueryCache::set('active', 'v', 60);
        QueryCache::set('expired', 'v', -1);

        $stats = QueryCache::getStats();

        $this->assertSame(2, $stats['total_items']);
        $this->assertSame(1, $stats['expired_items']);
        $this->assertSame(1, $stats['active_items']);
    }
}
