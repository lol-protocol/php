<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\GrupoRepository;
use App\Repositories\Repository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * The search ignores case and accents on both sides: "aberg" finds "Åberg", "garcia" finds "García" and
 * also "Garci\u{0301}a" (the same name written as a letter and a combining accent, which is what macOS
 * hands over), and "GARCÍA" finds "garcia". Run on every engine available, because SQLite and PostgreSQL
 * do not lowercase the same characters and each has its own TRANSLATE().
 */
class BusquedaSinAcentosTest extends TestCase
{
    /** Stored surnames, by id (grupos.id is six digits). */
    private const APELLIDOS = [
        100001 => 'Åberg',
        100002 => 'Łukasz',
        100003 => 'Søren',
        100004 => 'Dvořák',
        100005 => 'Şahin',
        100006 => 'Müller',
        100007 => 'García',
        100008 => "Garci\u{0301}a",
        100009 => 'Nuñez',
        100010 => 'NUÑEZ',
        100011 => 'İnce',
        100012 => 'Kowalski',
        100013 => 'Čorić',
        100014 => 'Ștefan',
        100015 => 'Haapāla',
    ];

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /** @return array<string, array{string, list<int>}> what is typed => the ids it must find */
    public static function busquedas(): array
    {
        return [
            'å' => ['aberg', [100001]],
            'Å escrita en mayúscula' => ['ÅBERG', [100001]],
            'ł' => ['lukasz', [100002]],
            'Ł en mayúscula' => ['ŁUKASZ', [100002]],
            'ø' => ['soren', [100003]],
            'ř y á' => ['dvorak', [100004]],
            'ş' => ['sahin', [100005]],
            'ü' => ['muller', [100006]],
            'García, con y sin acento' => ['garcia', [100007, 100008]],
            'García escrito con la tilde aparte' => ["Garci\u{0301}a", [100007, 100008]],
            'García con tilde' => ['GARCÍA', [100007, 100008]],
            'ñ en minúscula y mayúscula' => ['nunez', [100009, 100010]],
            'Ñ escrita' => ['NUÑEZ', [100009, 100010]],
            'İ con punto' => ['ince', [100011]],
            'solo ASCII' => ['kowalski', [100012]],
            'parte del apellido' => ['owal', [100012]],
            'č, ć' => ['coric', [100013]],
            'ș' => ['stefan', [100014]],
            'ā' => ['haapala', [100015]],
            'el % no es comodín' => ['%', []],
            'sin coincidencia' => ['zzz', []],
        ];
    }

    #[DataProvider('drivers')]
    public function testLaBusquedaEncuentraSinAcentosNiMayusculas(string $driver): void
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        foreach (self::APELLIDOS as $id => $apellido) {
            $db->execute('INSERT INTO grupos (id, apellido) VALUES (?, ?)', [$id, $apellido]);
        }
        $repo = new GrupoRepository($db);

        foreach (self::busquedas() as $nombre => [$texto, $esperados]) {
            $ids = array_map(static fn(array $fila): int => (int)$fila['id'], $repo->buscar($texto));
            sort($ids);

            $this->assertSame($esperados, $ids, "{$driver}: {$nombre} ({$texto})");
        }
    }

    // --- the map itself ------------------------------------------------------------------------

    /** @return array<string, string> */
    private static function acentos(): array
    {
        return (new \ReflectionClassConstant(Repository::class, 'ACENTOS'))->getValue();
    }

    public function testCadaEntradaEsUnaLetraNoASCIIQueSeVuelveUnaLetraASCII(): void
    {
        foreach (self::acentos() as $letra => $base) {
            $this->assertSame(1, mb_strlen((string)$letra), "{$letra} is more than one code point");
            $this->assertGreaterThan(127, mb_ord((string)$letra), "{$letra} is ASCII: sinAcentos() skips the translation for ASCII texts");
            $this->assertMatchesRegularExpression('/^[a-z]$/', $base, "{$letra} => {$base}");
        }
    }

    public function testCadaLetraTieneSuMayusculaYSuMinuscula(): void
    {
        $acentos = self::acentos();

        foreach ($acentos as $letra => $base) {
            // dotless i has no ASCII capital to list: its capital is "I", which LOWER() already handles
            if ($letra === 'ı') {
                continue;
            }
            $otra = mb_strtolower((string)$letra) === $letra ? mb_strtoupper((string)$letra) : mb_strtolower((string)$letra);
            // "İ" lowercases to "i" plus a combining dot, which is erased on its own
            if ($letra === 'İ') {
                continue;
            }
            $this->assertArrayHasKey($otra, $acentos, "{$letra} is listed but {$otra} is not");
            $this->assertSame($base, $acentos[$otra], "{$letra} and {$otra} must become the same letter");
        }
    }

    public function testLasMarcasCombinantesSonDelBloqueLatino(): void
    {
        $marcas = (new \ReflectionClassConstant(Repository::class, 'MARCAS_COMBINANTES'))->getValue();

        $this->assertNotSame('', $marcas);
        foreach (mb_str_split($marcas) as $marca) {
            $this->assertGreaterThanOrEqual(0x0300, mb_ord($marca));
            $this->assertLessThanOrEqual(0x036F, mb_ord($marca));
        }
        $this->assertSame(count(array_unique(mb_str_split($marcas))), mb_strlen($marcas), 'a mark is listed twice');
    }

    public function testUnTextoASCIIQuedaIgualEnLaBaseDeDatos(): void
    {
        $db = TestDatabases::fresh('sqlite', 'genealogy');
        $db->execute('INSERT INTO grupos (id, apellido) VALUES (100001, ?)', ['Kowalski Smith-Jones']);

        $expresion = (new \ReflectionMethod(Repository::class, 'sinAcentos'))->invoke(null, 'apellido');

        $this->assertSame('kowalski smith-jones', $db->fetchValue("SELECT {$expresion} FROM grupos WHERE id = 100001"));
    }
}
