<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\ByLengthMatchStrategy;
use PHPUnit\Framework\TestCase;

class ByLengthMatchStrategyTest extends TestCase
{
    private ByLengthMatchStrategy $strategy;
    private array $config;

    protected function setUp(): void
    {
        $this->strategy = new ByLengthMatchStrategy();
        $this->config = [
            'by_length' => [
                4 => [
                    'type' => 'persona',
                    'controller' => 'Genealogy\PersonaController',
                    'actions' => [1 => 'ascendencia'],
                ],
            ],
        ];
    }

    public function testMatchesOnlyAllDigitFirstSegment(): void
    {
        $this->assertTrue($this->strategy->matches(['1234'], $this->config));
        $this->assertFalse($this->strategy->matches(['abcd'], $this->config));
        $this->assertFalse($this->strategy->matches([], $this->config));
    }

    public function testResolvesToTypeByDigitLength(): void
    {
        $match = $this->strategy->resolve(['1234'], $this->config);

        $this->assertSame('Genealogy\PersonaController', $match['controller']);
        $this->assertSame('show', $match['method']);
        $this->assertSame(['id' => '1234'], $match['params']);
    }

    public function testResolvesActionSegment(): void
    {
        $match = $this->strategy->resolve(['1234', '1'], $this->config);

        $this->assertSame('ascendencia', $match['method']);
    }

    public function testUnknownActionCodeIsNotFound(): void
    {
        $this->assertNull($this->strategy->resolve(['1234', '99'], $this->config));
    }

    public function testUnknownDigitLengthIsNotFound(): void
    {
        $this->assertNull($this->strategy->resolve(['12'], $this->config));
    }

    public function testTooManySegmentsIsNotFound(): void
    {
        $this->assertNull($this->strategy->resolve(['1234', '1', 'extra'], $this->config));
    }

    public function testNonNumericActionCodeIsNotFound(): void
    {
        $this->assertNull($this->strategy->resolve(['1234', 'abc'], $this->config));
    }
}
