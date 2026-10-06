<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\PlaceMatchStrategy;
use PHPUnit\Framework\TestCase;

class PlaceMatchStrategyTest extends TestCase
{
    private PlaceMatchStrategy $strategy;
    private array $config;

    protected function setUp(): void
    {
        $this->strategy = new PlaceMatchStrategy();
        $this->config = [
            'place' => [
                'controller' => 'Genealogy\LugarController',
                'actions' => [1 => 'personas'],
            ],
        ];
    }

    public function testMatchesAllAlphaFirstSegment(): void
    {
        $this->assertTrue($this->strategy->matches(['mx'], $this->config));
        $this->assertFalse($this->strategy->matches(['123'], $this->config));
        $this->assertFalse($this->strategy->matches([], $this->config));
        $this->assertFalse($this->strategy->matches(['mx'], []));
    }

    public function testResolvesSingleLevelPlace(): void
    {
        $match = $this->strategy->resolve(['mx'], $this->config);

        $this->assertSame('Genealogy\LugarController', $match['controller']);
        $this->assertSame('show', $match['method']);
        $this->assertSame(['codes' => ['mx']], $match['params']);
    }

    public function testResolvesUpToThreeLevels(): void
    {
        $match = $this->strategy->resolve(['mx', 'jal', 'gdl'], $this->config);

        $this->assertSame(['codes' => ['mx', 'jal', 'gdl']], $match['params']);
    }

    public function testResolvesTrailingActionCode(): void
    {
        $match = $this->strategy->resolve(['mx', '1'], $this->config);

        $this->assertSame('personas', $match['method']);
        $this->assertSame(['codes' => ['mx']], $match['params']);
    }

    public function testRejectsMoreThanThreeLevels(): void
    {
        $this->assertNull($this->strategy->resolve(['a', 'b', 'c', 'd'], $this->config));
    }

    public function testRejectsNonAlphaCode(): void
    {
        $this->assertNull($this->strategy->resolve(['mx', 'j4l'], $this->config));
    }
}
