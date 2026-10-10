<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrivacidadTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('OWNER_TOKEN');
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    /** @return array<string, array{string, string}> [today, cut-off] */
    public static function fechas(): array
    {
        return [
            'hoy' => ['2026-10-09', '1916-10-09'],
            'fin de año' => ['2026-12-31', '1916-12-31'],
            'principio de año' => ['2040-01-01', '1930-01-01'],
            'bisiesto' => ['2024-02-29', '1914-03-01'],
        ];
    }

    #[DataProvider('fechas')]
    public function testTheCutOffIsTheConfiguredNumberOfYearsBack(string $hoy, string $umbral): void
    {
        $this->assertSame(110, Privacidad::ANIOS_VIVA);
        $this->assertSame($umbral, Privacidad::publica(new \DateTimeImmutable($hoy))->umbral());
    }

    public function testTheVisitorsViewHidesAndTheOwnersDoesNot(): void
    {
        $this->assertTrue(Privacidad::publica()->ocultaVivas());
        $this->assertFalse(Privacidad::propietario()->ocultaVivas());
    }

    /** The owner's tables are the tables themselves: the owner's SQL stays what it was before there was privacy. */
    public function testTheOwnersViewReadsTheTablesUntouched(): void
    {
        $dueno = Privacidad::propietario();

        $this->assertSame('sucesos', $dueno->sucesos());
        $this->assertSame('registros', $dueno->registros());
        $this->assertSame('(SELECT q.*, FALSE AS oculta FROM personas q)', $dueno->personas());
        $this->assertSame('(SELECT x FROM y)', $dueno->siVisible('SELECT x FROM y'));
    }

    public function testTheVisitorsViewWrapsEveryTable(): void
    {
        $visita = Privacidad::publica(new \DateTimeImmutable('2026-10-09'));

        $this->assertStringContainsString("'" . Privacidad::NOMBRE_OCULTO . "'", $visita->personas());
        $this->assertStringContainsString("> '1916-10-09'", $visita->personas());
        $this->assertStringContainsString('NOT EXISTS', $visita->sucesos());
        $this->assertStringContainsString('NOT EXISTS', $visita->registros());
        $this->assertSame(
            'CASE WHEN p.oculta THEN NULL ELSE (SELECT x FROM y) END',
            $visita->siVisible('SELECT x FROM y')
        );
        $this->assertStringContainsString('CASE WHEN q.oculta', $visita->siVisible('SELECT 1', 'q'));
    }

    /** What the SQL says about a persona: it can only be alive when nothing records its death. */
    public function testTheAliveExpressionReadsTheFlagThenDeathThenBirth(): void
    {
        $sql = Privacidad::publica(new \DateTimeImmutable('2026-10-09'))->sqlViva('x');

        $this->assertStringStartsWith('COALESCE(x.viva,', $sql);
        $this->assertStringContainsString("tipo = 'defuncion'", $sql);
        $this->assertStringContainsString("tipo = 'nacimiento'", $sql);
        $this->assertStringContainsString('IS TRUE', $sql, 'an unknown birth must come out false, not NULL');
    }

    /** Every inner alias derives from the one given, so the expression can nest without clashing with outer aliases. */
    public function testInnerAliasesDeriveFromTheGivenOne(): void
    {
        $sql = Privacidad::publica()->sqlViva('zz');

        preg_match_all('/\bzz_[a-z]+\b/', $sql, $internos);
        $this->assertNotEmpty($internos[0]);
        // Anything that is not an alias derived from "zz" must be a table, column or keyword of the schema.
        $this->assertDoesNotMatchRegularExpression('/\b(?:s|sp|e|p)\b\.(?:id|tipo|fecha)/', $sql);
    }

    /** @return array<string, array{string}> */
    public static function aliasInvalidos(): array
    {
        return [
            'vacío' => [''],
            'con espacio' => ['p x'],
            'inyección' => ['p; DROP TABLE personas; --'],
            'con punto' => ['a.b'],
            'empieza con número' => ['1p'],
            'con comilla' => ["p'"],
        ];
    }

    #[DataProvider('aliasInvalidos')]
    public function testAnInvalidAliasIsRejectedBeforeItReachesSql(string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Privacidad::publica()->sqlViva($alias);
    }

    #[DataProvider('aliasInvalidos')]
    public function testSiVisibleRejectsInvalidAliasesToo(string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Privacidad::publica()->siVisible('SELECT 1', $alias);
    }

    public function testTheRequestsViewFollowsWhoAsks(): void
    {
        $this->assertTrue(Privacidad::paraSolicitud()->ocultaVivas(), 'an anonymous request sees the visitor\'s view');

        putenv('OWNER_TOKEN=token-de-prueba-0123456789');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token-de-prueba-0123456789';

        $this->assertFalse(Privacidad::paraSolicitud()->ocultaVivas());
    }
}
