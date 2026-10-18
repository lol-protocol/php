<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\ReservedMatchStrategy;
use PHPUnit\Framework\TestCase;

class ReservedMatchStrategyTest extends TestCase
{
    private ReservedMatchStrategy $strategy;
    private array $config;

    protected function setUp(): void
    {
        $this->strategy = new ReservedMatchStrategy();
        $this->config = [
            'reserved' => [
                'cuenta' => ['controller' => 'Genealogy\CuentaController', 'actions' => [1 => 'editar']],
                '' => ['controller' => 'Genealogy\HomeController', 'method' => 'index'],
                'b' => ['controller' => 'Genealogy\HomeController', 'method' => 'buscar', 'ruta' => 3],
            ],
        ];
    }

    public function testMatchesKnownReservedWord(): void
    {
        $this->assertTrue($this->strategy->matches(['cuenta'], $this->config));
        $this->assertFalse($this->strategy->matches(['unknown'], $this->config));
    }

    public function testAtMostOneActionSegment(): void
    {
        $this->assertNull($this->strategy->resolve(['cuenta', '1', 'extra'], $this->config));
    }

    public function testAnEntryWithRutaGetsTheRestOfThePath(): void
    {
        $match = $this->strategy->resolve(['b', '10', 'garcia', '2'], $this->config);

        $this->assertSame('buscar', $match['method']);
        $this->assertSame(['ruta' => ['10', 'garcia', '2']], $match['params']);
        $this->assertSame(['ruta' => []], $this->strategy->resolve(['b'], $this->config)['params']);
        $this->assertNull($this->strategy->resolve(['b', '10', 'garcia', '2', 'x'], $this->config), 'more than ruta allows');
    }

    public function testResolvesDefaultMethod(): void
    {
        $match = $this->strategy->resolve(['cuenta'], $this->config);

        $this->assertSame('Genealogy\CuentaController', $match['controller']);
        $this->assertSame('index', $match['method']);
    }

    public function testResolvesActionCode(): void
    {
        $match = $this->strategy->resolve(['cuenta', '1'], $this->config);

        $this->assertSame('editar', $match['method']);
    }

    public function testUnknownActionCodeIsNotFound(): void
    {
        $this->assertNull($this->strategy->resolve(['cuenta', '99'], $this->config));
    }
}
