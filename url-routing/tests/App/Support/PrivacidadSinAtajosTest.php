<?php

declare(strict_types=1);

namespace Tests\App\Support;

use PHPUnit\Framework\TestCase;

/**
 * Everything a visitor sees about a persona, an event or a record goes through Support/Privacidad
 * (personas(), sucesos(), registros()): that is where a living, private or withdrawn persona is hidden.
 * A query that reads the real tables directly skips all of it, so this lists, file by file, how many
 * times the code names them in a FROM or JOIN, and fails when the count changes. A new query that needs
 * the real table is allowed: say so here, with why it cannot show a hidden persona.
 */
class PrivacidadSinAtajosTest extends TestCase
{
    /** Where the real tables are read, and why that cannot leak (file => [how many, why]). */
    private const PERMITIDAS = [
        'Support/Privacidad.php' => [9, 'builds the masked views and the hidden/visible conditions everything else uses'],
        'Repositories/Genealogy/PersonaRepository.php' => [13, 'walks parent/child ids; every row it returns is joined to personas() (masked) before it is shown, and buscar() asks sqlVisible() of the real table'],
        'Repositories/Genealogy/SitemapRepository.php' => [1, 'asks NOT sqlOculta() of the real table'],
        'Repositories/Genealogy/FusionRepository.php' => [7, 'the owner\'s merge tool (bin/fusionar.php): it never serves a page'],
    ];

    /** @return array<string, int> file (relative to url-routing/) => how many FROM/JOIN personas|sucesos|registros it has */
    private static function lecturas(): array
    {
        $raiz = dirname(__DIR__, 3);
        $cuenta = [];
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        foreach ($archivos as $archivo) {
            $ruta = substr($archivo->getPathname(), strlen($raiz) + 1);
            if ($archivo->getExtension() !== 'php' || preg_match('#^(vendor|tests|database|docs)/#', $ruta) === 1) {
                continue;
            }
            $n = preg_match_all('/\b(?:FROM|JOIN)\s+(?:personas|sucesos|registros)\b/', (string)file_get_contents($archivo->getPathname()));
            if ($n > 0) {
                $cuenta[$ruta] = $n;
            }
        }
        ksort($cuenta);

        return $cuenta;
    }

    public function testNoQueryReadsTheRealTablesOutsideTheListedPlaces(): void
    {
        $esperado = array_map(static fn(array $p): int => $p[0], self::PERMITIDAS);
        ksort($esperado);

        $this->assertSame(
            $esperado,
            self::lecturas(),
            'A query reads personas, sucesos or registros directly. A visitor must only see them through '
            . 'Privacidad::personas(), sucesos() or registros(); if this one really cannot show a hidden persona, '
            . 'list it in PERMITIDAS with the reason.'
        );
    }

    public function testEveryPlaceExplainsWhyItIsSafe(): void
    {
        foreach (self::PERMITIDAS as $archivo => [, $porQue]) {
            $this->assertNotSame('', trim($porQue), $archivo);
            $this->assertFileExists(dirname(__DIR__, 3) . '/' . $archivo);
        }
    }
}
