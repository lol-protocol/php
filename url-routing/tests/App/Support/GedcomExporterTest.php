<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\GedcomExporter;
use PHPUnit\Framework\TestCase;

class GedcomExporterTest extends TestCase
{
    /** @return string the GEDCOM lines of one individual, after his NAME and SEX */
    private function eventos(?string $nacimiento, ?string $lugar, ?string $defuncion = null): string
    {
        $gedcom = (new GedcomExporter())->export([[
            'id' => 1, 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'sexo' => 'F', 'padre_id' => null, 'madre_id' => null,
            'nacimiento' => $nacimiento, 'lugar_nacimiento' => $lugar, 'defuncion' => $defuncion,
        ]], 'Prueba');

        return explode("1 SEX F\r\n", $gedcom)[1];
    }

    public function testAFullDateAndAPlaceAreExported(): void
    {
        $this->assertStringStartsWith("1 BIRT\r\n2 DATE 2 MAR 1950\r\n2 PLAC Guadalajara\r\n", $this->eventos('1950-03-02', 'Guadalajara'));
    }

    /** What a hidden event leaves (see Privacidad::sqlEventoDe()): GEDCOM takes a bare year. */
    public function testAYearOnItsOwnIsExportedAsADateWithoutDayOrMonth(): void
    {
        $eventos = $this->eventos('1950', null, '1960');

        $this->assertStringStartsWith("1 BIRT\r\n2 DATE 1950\r\n1 DEAT\r\n2 DATE 1960\r\n", $eventos);
        $this->assertStringNotContainsString('PLAC', $eventos);
    }

    public function testNothingKnownMeansNoEventAtAll(): void
    {
        $this->assertStringNotContainsString('BIRT', $this->eventos(null, null));
        $this->assertStringNotContainsString('BIRT', $this->eventos('', ''));
    }

    public function testAPlaceWithoutADateIsStillExported(): void
    {
        $this->assertStringStartsWith("1 BIRT\r\n2 PLAC Oviedo\r\n", $this->eventos(null, 'Oviedo'));
    }
}
