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

    #[DataProvider('aliasInvalidos')]
    public function testSqlEventoDeRejectsInvalidAliasesToo(string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Privacidad::publica()->sqlEventoDe($alias, 'nacimiento', 'fecha');
    }

    /** @return array<string, array{string, string}> [tipo, campo] */
    public static function eventosInvalidos(): array
    {
        return [
            'tipo vacío' => ['', 'fecha'],
            'tipo con comilla' => ["nacimiento' OR 1=1 --", 'fecha'],
            'tipo con espacio' => ['naci miento', 'fecha'],
            'campo desconocido' => ['nacimiento', 'descripcion'],
            'campo con inyección' => ['nacimiento', 'fecha; DROP TABLE sucesos'],
        ];
    }

    #[DataProvider('eventosInvalidos')]
    public function testSqlEventoDeRejectsWhatIsNotAPlainEventTypeAndKnownField(string $tipo, string $campo): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Privacidad::publica()->sqlEventoDe('p', $tipo, $campo);
    }

    public function testAnEventIsVisibleToTheOwnerAlwaysAndToAVisitorWhenNoOneInItLives(): void
    {
        $this->assertSame('TRUE', Privacidad::propietario()->sqlSucesoVisible('e'));

        $sql = Privacidad::publica()->sqlSucesoVisible('e');
        $this->assertStringStartsWith('NOT EXISTS', $sql);
        $this->assertStringContainsString('e_vp.suceso_id = e.id', $sql);
        $this->assertStringContainsString('COALESCE(e_vq.viva', $sql, 'a participant counts as alive by the same rule as everywhere else');
    }

    #[DataProvider('aliasInvalidos')]
    public function testSqlSucesoVisibleRejectsInvalidAliasesToo(string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Privacidad::publica()->sqlSucesoVisible($alias);
    }

    /** The owner's SQL is the plain columns; a visitor's keeps the year of a hidden event and drops its place. */
    public function testAHiddenEventsPlaceAndDayAreWhatSqlEventoDeTakesAway(): void
    {
        $propietario = Privacidad::propietario();
        $this->assertStringContainsString('SELECT s.fecha FROM sucesos s', $propietario->sqlEventoDe('p', 'nacimiento', 'fecha'));
        $this->assertStringContainsString('SELECT s.lugar_ruta FROM sucesos s', $propietario->sqlEventoDe('p', 'nacimiento', 'lugar_ruta'));
        $this->assertStringNotContainsString('SUBSTR', $propietario->sqlEventoDe('p', 'nacimiento', 'fecha'));

        $visita = Privacidad::publica();
        $this->assertStringContainsString('ELSE SUBSTR(CAST(s.fecha AS TEXT), 1, 4) END', $visita->sqlEventoDe('p', 'nacimiento', 'fecha'));
        $this->assertStringContainsString('THEN s.lugar_ruta END', $visita->sqlEventoDe('p', 'nacimiento', 'lugar_ruta'));
        $this->assertStringContainsString('THEN l.nombre END', $visita->sqlEventoDe('p', 'nacimiento', 'lugar_nombre'));
    }

    /** Being a parent or a witness at someone's birth or death is not being born or dying. */
    public function testOnlyEventsThePersonaIsThePrincipalOfAreHers(): void
    {
        foreach ([Privacidad::publica(), Privacidad::propietario()] as $privacidad) {
            $this->assertStringContainsString("sp.rol = 'principal'", $privacidad->sqlEventoDe('p', 'defuncion', 'fecha'));
        }
        $this->assertStringContainsString("x_dp.rol = 'principal'", Privacidad::publica()->sqlViva('x'), 'her own death, not one she registered');
    }

    /** A persona is private when she is in some colección and in none that is public. */
    public function testThePrivateExpressionNeedsACollectionAndNoPublicOne(): void
    {
        $sql = Privacidad::publica()->sqlPrivada('x');

        $this->assertStringContainsString('EXISTS (SELECT 1 FROM coleccion_personas x_mp WHERE x_mp.persona_id = x.id)', $sql);
        $this->assertStringContainsString('NOT EXISTS (SELECT 1 FROM coleccion_personas x_pp JOIN colecciones x_pc', $sql);
        $this->assertStringContainsString('x_pc.publica', $sql);
    }

    public function testHiddenMeansAliveOrPrivateOrWithdrawn(): void
    {
        $visita = Privacidad::publica(new \DateTimeImmutable('2026-10-09'));
        $sql = $visita->sqlOculta('x');

        $this->assertSame('(' . $visita->sqlViva('x') . ' OR ' . $visita->sqlPrivada('x') . ' OR ' . Privacidad::sqlRetirada('x') . ')', $sql);
        $this->assertStringContainsString("rr.tipo = 'persona' AND rr.recurso_id = x.id AND rr.estado IN ('oculto', 'retirado')", Privacidad::sqlRetirada('x'));
    }

    public function testTheVisitorsPersonasNameTheTwoKindsOfMaskApart(): void
    {
        $sql = Privacidad::publica(new \DateTimeImmutable('2026-10-09'))->personas();

        $this->assertSame('Persona viva', Privacidad::NOMBRE_OCULTO);
        $this->assertSame('Persona privada', Privacidad::NOMBRE_PRIVADO);
        $this->assertStringContainsString("'" . Privacidad::NOMBRE_OCULTO . "'", $sql);
        $this->assertStringContainsString("'" . Privacidad::NOMBRE_PRIVADO . "'", $sql);
        $this->assertStringContainsString('x.viva_presunta OR x.privada OR x.retirada', $sql);
    }

    /** Events and records are filtered by the same notion of hidden, not by "alive" alone. */
    public function testEventsAndRecordsAreHiddenByPrivatePeopleToo(): void
    {
        $visita = Privacidad::publica();

        $this->assertStringContainsString('coleccion_personas', $visita->sucesos());
        $this->assertStringContainsString('coleccion_personas', $visita->registros());
    }

    /** The owner's SQL never mentions collections: nothing is hidden from her. */
    public function testTheOwnersSqlIsUnchangedByPrivateCollections(): void
    {
        $dueno = Privacidad::propietario();

        $this->assertStringNotContainsString('coleccion_personas', $dueno->personas());
        $this->assertSame('sucesos', $dueno->sucesos());
        $this->assertSame('registros', $dueno->registros());
    }

    #[DataProvider('aliasInvalidos')]
    public function testThePrivateAndHiddenExpressionsRejectInvalidAliasesToo(string $alias): void
    {
        foreach (['sqlPrivada', 'sqlOculta'] as $metodo) {
            try {
                Privacidad::publica()->$metodo($alias);
                $this->fail("{$metodo}('{$alias}') should be rejected");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheRequestsViewFollowsWhoAsks(): void
    {
        $this->assertTrue(Privacidad::paraSolicitud()->ocultaVivas(), 'an anonymous request sees the visitor\'s view');

        putenv('OWNER_TOKEN=token-de-prueba-0123456789');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token-de-prueba-0123456789';

        $this->assertFalse(Privacidad::paraSolicitud()->ocultaVivas());
    }
}
