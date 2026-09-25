<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\LiteralMatchStrategy;
use PHPUnit\Framework\TestCase;

class LiteralMatchStrategyTest extends TestCase
{
    private LiteralMatchStrategy $strategy;
    private array $config;

    protected function setUp(): void
    {
        $this->strategy = new LiteralMatchStrategy();
        $this->config = [
            'literal' => [
                'buscar' => ['controller' => 'Genealogy\BuscarController', 'method' => 'index'],
            ],
        ];
    }

    public function testMatchesWhenLiteralConfigured(): void
    {
        $this->assertTrue($this->strategy->matches(['buscar'], $this->config));
        $this->assertFalse($this->strategy->matches(['buscar'], []));
    }

    public function testResolvesKnownLiteralPath(): void
    {
        $match = $this->strategy->resolve(['buscar'], $this->config);

        $this->assertSame('Genealogy\BuscarController', $match['controller']);
        $this->assertSame('index', $match['method']);
        $this->assertSame([], $match['params']);
    }

    public function testResolvesJoinedMultiSegmentPath(): void
    {
        $config = [
            'literal' => [
                'admin/panel' => ['controller' => 'Admin\PanelController', 'method' => 'index'],
            ],
        ];

        $match = $this->strategy->resolve(['admin', 'panel'], $config);

        $this->assertSame('Admin\PanelController', $match['controller']);
    }

    public function testReturnsNullForUnknownPath(): void
    {
        $this->assertNull($this->strategy->resolve(['no-such-path'], $this->config));
    }
}
