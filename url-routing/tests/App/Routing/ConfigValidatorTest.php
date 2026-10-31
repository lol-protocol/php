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
            'archivos' => ['robots.txt' => ['controller' => 'X\Archivos', 'method' => 'robots']],
            'redirect' => ['viejo' => '/nuevo/'],
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

    public function testArchivosEntryMissingMethodThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'archivos' => ['robots.txt' => ['controller' => 'X\Archivos']],
        ]);
    }

    public function testRedirectToAnotherHostThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'redirect' => ['viejo' => '//otro.example/'],
        ]);
    }

    public function testPlaceEntryMissingControllerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate([
            'place' => ['actions' => []],
        ]);
    }

    public function testByLengthKeyThatIsNotAPositiveDigitCountThrows(): void
    {
        foreach (['cuatro', 0, -3] as $clave) {
            try {
                ConfigValidator::validate(['by_length' => [$clave => ['type' => 'persona', 'controller' => 'X\Y']]]);
                $this->fail("by_length key {$clave} should be rejected");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('positive number of digits', $e->getMessage());
            }
        }
    }

    /** /0/ is a 1-digit segment: with a 1-digit type the router would resolve the type and never reach the account. */
    public function testReservedDigitKeyShadowedByAByLengthTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Reserved entry '0' is shadowed by by_length[1]");

        ConfigValidator::validate([
            'by_length' => [1 => ['type' => 'raro', 'controller' => 'X\Raro']],
            'reserved' => ['0' => ['controller' => 'X\Cuenta']],
        ]);
    }

    public function testReservedTwoLetterKeyShadowedByPlaceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Reserved entry 'mx' is shadowed by place");

        ConfigValidator::validate([
            'place' => ['controller' => 'X\Place'],
            'reserved' => ['mx' => ['controller' => 'X\Otro']],
        ]);
    }

    public function testReservedKeysThatNothingShadowsPass(): void
    {
        ConfigValidator::validate([
            'by_length' => [4 => ['type' => 'persona', 'controller' => 'X\Y']],
            'place' => ['controller' => 'X\Place'],
            'reserved' => [
                '' => ['controller' => 'X\Home'],
                '0' => ['controller' => 'X\Cuenta'],
                'b' => ['controller' => 'X\Busqueda'],
            ],
        ]);

        $this->addToAssertionCount(1);
    }
}
