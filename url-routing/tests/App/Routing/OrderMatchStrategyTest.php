<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\OrderMatchStrategy;
use PHPUnit\Framework\TestCase;

class OrderMatchStrategyTest extends TestCase
{
    private OrderMatchStrategy $strategy;
    private array $config;

    protected function setUp(): void
    {
        $this->strategy = new OrderMatchStrategy();
        $this->config = [
            'order' => [
                'controller' => 'POS\OrderController',
                'actions' => [1 => 'cancelar'],
            ],
        ];
    }

    public function testMatchesOnlyOrderLiteral(): void
    {
        $this->assertTrue($this->strategy->matches(['order', '123'], $this->config));
        $this->assertFalse($this->strategy->matches(['orders', '123'], $this->config));
        $this->assertFalse($this->strategy->matches(['order', '123'], []));
    }

    public function testResolvesOrderId(): void
    {
        $match = $this->strategy->resolve(['order', '123'], $this->config);

        $this->assertSame('POS\OrderController', $match['controller']);
        $this->assertSame('show', $match['method']);
        $this->assertSame(['id' => '123'], $match['params']);
    }

    public function testResolvesActionCode(): void
    {
        $match = $this->strategy->resolve(['order', '123', '1'], $this->config);

        $this->assertSame('cancelar', $match['method']);
    }

    public function testRejectsMissingId(): void
    {
        $this->assertNull($this->strategy->resolve(['order'], $this->config));
    }

    public function testRejectsNonNumericId(): void
    {
        $this->assertNull($this->strategy->resolve(['order', 'abc'], $this->config));
    }

    public function testRejectsTooManySegments(): void
    {
        $this->assertNull($this->strategy->resolve(['order', '123', '1', 'extra'], $this->config));
    }
}
