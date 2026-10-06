<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\ConfigValidator;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    public function testValidConfigPassesWithoutException(): void
    {
        ConfigValidator::validate([
            'by_length' => [4 => ['type' => 'persona', 'controller' => 'X\Y']],
            'reserved' => ['home' => ['controller' => 'X\Home']],
            'literal' => ['buscar' => ['controller' => 'X\Buscar']],
            'order' => ['controller' => 'X\Order'],
            'place' => ['controller' => 'X\Place'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testEmptyConfigPassesWithoutException(): void
    {
        ConfigValidator::validate([]);
        $this->addToAssertionCount(1);
    }

    public function testByLengthEntryMissingControllerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'by_length' => [4 => ['type' => 'persona']],
        ]);
    }

    public function testReservedEntryMissingControllerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'reserved' => ['home' => ['method' => 'index']],
        ]);
    }

    public function testLiteralEntryMissingControllerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'literal' => ['buscar' => ['method' => 'index']],
        ]);
    }

    public function testOrderEntryMissingControllerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'order' => ['actions' => []],
        ]);
    }

    public function testPlaceEntryMissingControllerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'place' => ['actions' => []],
        ]);
    }
}
