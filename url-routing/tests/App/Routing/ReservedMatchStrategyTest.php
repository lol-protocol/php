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
            ],
        ];
    }

    public function testMatchesKnownReservedWord(): void
    {
        $this->assertTrue($this->strategy->matches(['cuenta'], $this->config));
        $this->assertFalse($this->strategy->matches(['unknown'], $this->config));
    }

    public function testMatchesRequiresAtMostTwoSegments(): void
    {
        $this->assertFalse($this->strategy->matches(['cuenta', '1', 'extra'], $this->config));
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
