<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;
use Tests\Support\ServidorDePrueba;

/**
 * Follows every link of the seeded sites, page by page, the way a crawler does, and asks that none of them
 * redirect or answer 404. The canonical 301 makes a link written by hand work anyway, at the cost of one more
 * request (and of a URL that is not the one a search engine should see); `enlace()` and `accion()` exist so
 * nobody writes one, and this is what notices when somebody does. A broken link here is a bug in a view.
 *
 * Real requests, to PHP's built-in server (headers are invisible from the CLI: see ResponseHeadersTest).
 */
class EnlacesTest extends TestCase
{
    use ServidorDePrueba;

    private const GENEALOGIA = 'tudominio.local';
    private const POS = 'contrastocolor.local';
    private const TOKEN = 'token-de-prueba-0123456789';
    /** A crawl that does not end is a bug too (a link that makes a new one every time). */
    private const MAX_PAGINAS = 600;

    public static function setUpBeforeClass(): void
    {
        self::servidorIniciar('enlaces', ['OWNER_TOKEN' => self::TOKEN]);
    }

    public static function tearDownAfterClass(): void
    {
        self::servidorDetener();
    }

    /**
     * One GET, without following redirects.
     *
     * @return array{int, string} status, body
     */
    private function pedir(string $host, string $ruta, bool $propietario): array
    {
        // A crawl makes hundreds of requests and the limiter allows 100 a minute per IP: start each one fresh.
        self::reiniciarLimitador();
        $respuesta = $this->pedirAlServidor($host, $ruta, $propietario ? ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)] : []);

        return [$respuesta['status'], $respuesta['body']];
    }

    /**
     * The local links of a page: href="/..." of an <a>, without the fragment. Absolute URLs, mailto: and the like
     * are not this site's to check.
     *
     * @return list<string>
     */
    private static function enlacesDe(string $html): array
    {
        preg_match_all('/<a\s[^>]*href="(\/[^"#]*)"/i', $html, $m);

        return array_values(array_unique(array_map(static fn(string $h): string => html_entity_decode($h, ENT_QUOTES), $m[1])));
    }

    /**
     * Where a crawler starts: the home page, the listings (the search form is not a link) and every URL the sitemap
     * gives, which is how a search engine finds the pages. So the sitemap's own URLs are checked too: each must exist
     * and be the canonical one.
     *
     * @param list<string> $extra
     * @return list<string>
     */
    private function semillas(string $host, array $extra, bool $propietario): array
    {
        $rutas = [];
        [, $indice] = $this->pedir($host, '/sitemap.xml', $propietario);
        preg_match_all('#<loc>([^<]+)</loc>#', $indice, $partes);
        foreach ($partes[1] as $parte) {
            $rutas[] = (string)parse_url($parte, PHP_URL_PATH);
            [, $xml] = $this->pedir($host, (string)parse_url($parte, PHP_URL_PATH), $propietario);
            preg_match_all('#<loc>([^<]+)</loc>#', $xml, $paginas);
            foreach ($paginas[1] as $pagina) {
                $rutas[] = (string)parse_url($pagina, PHP_URL_PATH);
            }
        }
        $this->assertNotSame([], $rutas, 'the sitemap gave nothing');

        return array_values(array_unique(array_merge(['/'], $extra, $rutas)));
    }

    /**
     * Breadth-first from $inicio. Returns the pages that were reached and the links that were not what they should be.
     *
     * @param list<string> $inicio
     * @return array{list<string>, list<string>}
     */
    private function recorrer(string $host, array $inicio, bool $propietario): array
    {
        $porVisitar = $inicio;
        $vistas = [];
        $mal = [];
        $origen = array_fill_keys($inicio, '(inicio)');

        while ($porVisitar !== [] && count($vistas) < self::MAX_PAGINAS) {
            $ruta = array_shift($porVisitar);
            if (isset($vistas[$ruta])) {
                continue;
            }
            [$estado, $cuerpo] = $this->pedir($host, $ruta, $propietario);
            $vistas[$ruta] = $estado;

            // Every page links «Mi cuenta». For a visitor the account answers 401 (it asks for the token): that is
            // the design, not a broken link. Nothing behind it is followed.
            if (!$propietario && ($ruta === '/0/' || str_starts_with($ruta, '/0/'))) {
                if ($estado !== 401) {
                    $mal[] = "{$ruta} -> {$estado}, y a un visitante la cuenta le pide la clave (401) (enlazada desde {$origen[$ruta]})";
                }
                continue;
            }
            if ($estado !== 200) {
                $mal[] = "{$ruta} -> {$estado} (enlazada desde {$origen[$ruta]})";
                continue;
            }
            if (!str_contains($cuerpo, '<html')) {
                continue; // a file (GEDCOM, XML): nothing to follow
            }
            foreach (self::enlacesDe($cuerpo) as $enlace) {
                if (!isset($vistas[$enlace]) && !isset($origen[$enlace])) {
                    $origen[$enlace] = $ruta;
                    $porVisitar[] = $enlace;
                }
            }
        }
        $this->assertLessThan(self::MAX_PAGINAS, count($vistas), 'the crawl did not end: a link makes a new link every time');

        return [array_keys($vistas), $mal];
    }

    /**
     * The kinds of page a crawl reached: «#10» (an id of 10 digits), «0» (the account), a letter or a country.
     *
     * @param list<string> $paginas
     * @return list<string>
     */
    private static function tipos(array $paginas): array
    {
        $tipos = [];
        foreach ($paginas as $ruta) {
            $primero = explode('/', trim($ruta, '/'))[0];
            $tipos[ctype_digit($primero) && $primero !== '0' ? '#' . strlen($primero) : $primero] = true;
        }

        // PHP turns the key '0' into the integer 0.
        return array_map('strval', array_keys($tipos));
    }

    public function testGenealogyLinksAreAllCanonicalAndAllExist(): void
    {
        [$paginas, $mal] = $this->recorrer(self::GENEALOGIA, $this->semillas(self::GENEALOGIA, ['/b/10/', '/b/9/', '/b/8/', '/b/7/', '/b/6/', '/b/5/'], false), false);

        $this->assertSame([], $mal, "Enlaces que redirigen o no existen:\n" . implode("\n", $mal));
        $this->assertGreaterThan(40, count($paginas), 'the crawl found almost nothing: is it following links?');
        foreach (['#10', '#9', '#8', '#7', '#6', '#5', 'mx', 'b'] as $tipo) {
            $this->assertContains($tipo, self::tipos($paginas), "the crawl never reached a page of type {$tipo}");
        }
    }

    public function testPosLinksAreAllCanonicalAndAllExist(): void
    {
        [$paginas, $mal] = $this->recorrer(self::POS, $this->semillas(self::POS, ['/b/8/', '/b/7/', '/b/6/', '/b/5/', '/b/4/', '/c/'], false), false);

        $this->assertSame([], $mal, "Enlaces que redirigen o no existen:\n" . implode("\n", $mal));
        $this->assertGreaterThan(15, count($paginas));
        foreach (['#8', '#7', '#6', '#5', '#4', 'b'] as $tipo) {
            $this->assertContains($tipo, self::tipos($paginas), "the crawl never reached a page of type {$tipo}");
        }
    }

    /** The owner sees more (edit, privacy, the account): her links must be as good as a visitor's. */
    public function testTheOwnersLinksAreAllCanonicalAndAllExist(): void
    {
        [$paginas, $mal] = $this->recorrer(self::GENEALOGIA, $this->semillas(self::GENEALOGIA, ['/0/', '/b/10/', '/b/7/'], true), true);
        [$paginasPos, $malPos] = $this->recorrer(self::POS, $this->semillas(self::POS, ['/0/', '/b/8/'], true), true);

        $this->assertSame([], array_merge($mal, $malPos), "Enlaces que redirigen o no existen:\n" . implode("\n", array_merge($mal, $malPos)));
        $this->assertContains('0', self::tipos($paginas), 'the owner reached the account');
        $this->assertContains('0', self::tipos($paginasPos));
    }
}
