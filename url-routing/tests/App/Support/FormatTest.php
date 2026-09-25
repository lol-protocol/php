<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function testFechaPerLocale(): void
    {
        $this->assertSame('14 feb 1925', Format::fecha('1925-02-14'));
        $this->assertSame('14 Feb 1925', Format::fecha('1925-02-14', 'eng'));
        $this->assertSame('1 sep 2026, 10:15', Format::fecha('2026-09-01 10:15:00'));
    }

    public function testFechaMissingOrUnparseable(): void
    {
        $this->assertSame('—', Format::fecha(null));
        $this->assertSame('—', Format::fecha(''));
        $this->assertSame('circa 1900', Format::fecha('circa 1900'));
    }

    public function testVida(): void
    {
        $this->assertSame('1868–1941', Format::vida('1868-05-12', '1941-10-05'));
        $this->assertSame('1925–', Format::vida('1925-02-14', null));
        $this->assertSame('', Format::vida(null, null));
    }

    public function testDineroUsesIntegerCents(): void
    {
        $this->assertSame('$199.00 MXN', Format::dinero(19900));
        $this->assertSame('$1,234.56 EUR', Format::dinero('123456', 'EUR'));
        $this->assertSame('$0.00 MXN', Format::dinero(null));
    }
}
