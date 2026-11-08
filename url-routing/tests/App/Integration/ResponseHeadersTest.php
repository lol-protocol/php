<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ServidorDePrueba;

/**
 * The headers of a response, which PagesTest cannot see: the CLI has no real
 * HTTP response, so header() does nothing there (see tests/Support/request.php).
 * These requests go to PHP's built-in web server instead.
 *
 * What it guards: a page that is hidden from a visitor must be
 * indistinguishable from one that does not exist, headers included. The ids are
 * guessable URLs, so any header that only the hidden page sent would tell
 * anyone which private collections and orders exist.
 */
class ResponseHeadersTest extends TestCase
{
    use ServidorDePrueba;

    private const GENEALOGIA = 'tudominio.local';
    private const POS = 'contrastocolor.local';
    private const TOKEN = 'token-de-prueba-0123456789';

    /** Headers that legitimately differ between two requests to the same page. */
    private const VARIABLES = ['date', 'set-cookie'];

    public static function setUpBeforeClass(): void
    {
        self::servidorIniciar('headers', ['OWNER_TOKEN' => self::TOKEN, 'URL_LOG_DIR' => '{dir}/urls'], static function (string $dir): void {
            // 582399 was a duplicate of the grupo García (582317), merged into it (bin/fusionar.php grupo).
            (new Database('sqlite:' . $dir . '/genealogy.sqlite'))->execute('INSERT INTO grupo_alias (id, grupo_id) VALUES (582399, 582317)');
            // 130 surnames «Zeta001»…«Zeta130»: three pages of a listing (50 per page).
            $pdo = (new Database('sqlite:' . $dir . '/genealogy.sqlite'))->pdo();
            $pdo->beginTransaction();
            $grupo = $pdo->prepare('INSERT INTO grupos (id, apellido) VALUES (?, ?)');
            for ($i = 1; $i <= 130; $i++) {
                $grupo->execute([600000 + $i, 'Zeta' . str_pad((string)$i, 3, '0', STR_PAD_LEFT)]);
            }
            $pdo->commit();

            // One persona whose page changed on a known day (the rest have no date).
            (new Database('sqlite:' . $dir . '/genealogy.sqlite'))->execute("UPDATE personas SET actualizado_en = '2026-03-04 10:11:12' WHERE id = 6128473101");
        });
    }

    /**
     * Every request in this class comes from 127.0.0.1, and the limiter allows 100 a minute per IP: the whole class
     * makes more than that, so each test starts with a fresh allowance (only the limiter's own files go).
     */
    protected function setUp(): void
    {
        self::reiniciarLimitador();
    }

    public static function tearDownAfterClass(): void
    {
        self::servidorDetener();
    }

    /**
     * Everything about a response that one request may share with another: status,
     * body and headers, without the ones that change on every request.
     *
     * @param array{status: int, headers: array<string, list<string>>, body: string} $r
     * @return array{int, string, array<string, list<string>>}
     */
    private static function huella(array $r): array
    {
        $cabeceras = array_diff_key($r['headers'], array_flip(self::VARIABLES));
        ksort($cabeceras);

        return [$r['status'], $r['body'], $cabeceras];
    }

    /** @return array<string, array{string, string, string}> [host, hidden uri, missing uri of the same kind] */
    public static function paginasOcultas(): array
    {
        return [
            'coleccion privada' => [self::GENEALOGIA, '/1048294/', '/1048200/'],
            'coleccion privada, arbol' => [self::GENEALOGIA, '/1048294/1/', '/1048200/1/'],
            'coleccion privada, editar' => [self::GENEALOGIA, '/1048294/2/', '/1048200/2/'],
            'coleccion privada, GEDCOM' => [self::GENEALOGIA, '/1048294/3/', '/1048200/3/'],
            'pedido de otra persona' => [self::POS, '/7305918264731590/', '/4821937065189999/'],
            'pedido, factura' => [self::POS, '/4821937065182734/1/', '/4821937065189999/1/'],
        ];
    }

    #[DataProvider('paginasOcultas')]
    public function testAHiddenPageAnswersExactlyLikeAMissingOne(string $host, string $oculta, string $inexistente): void
    {
        $ocultaR = $this->pedirAlServidor($host, $oculta);
        $faltaR = $this->pedirAlServidor($host, $inexistente);

        // The comparison below is only worth something if headers are being observed at all.
        $this->assertSame(404, $faltaR['status']);
        $this->assertArrayHasKey('cache-control', $faltaR['headers'], 'The test server sent no Cache-Control: headers are not being observed');
        $this->assertArrayHasKey('x-content-type-options', $faltaR['headers']);

        $this->assertSame(self::huella($faltaR), self::huella($ocultaR), "{$oculta} must look exactly like {$inexistente}");
    }

    /**
     * robots.txt only keeps crawlers from walking these paths; the header keeps a URL that someone links to
     * out of the results. The pages meant to be found (home, a persona, a producto) must not carry it.
     *
     * @return array<string, array{string, string, bool}> [host, uri, carries X-Robots-Tag: noindex]
     */
    public static function noindex(): array
    {
        return [
            'genealogía: búsqueda' => [self::GENEALOGIA, '/b/10/', true],
            'genealogía: la cuenta, aunque pida la clave' => [self::GENEALOGIA, '/0/', true],
            'genealogía: pedir un retiro' => [self::GENEALOGIA, '/r/582317/', true],
            'genealogía: inicio' => [self::GENEALOGIA, '/', false],
            'genealogía: una persona' => [self::GENEALOGIA, '/6128473101/', false],
            'genealogía: un lugar' => [self::GENEALOGIA, '/mx/jal/', false],
            'POS: carrito' => [self::POS, '/c/', true],
            'POS: pago' => [self::POS, '/p/', true],
            'POS: búsqueda' => [self::POS, '/b/8/', true],
            'POS: un producto' => [self::POS, '/81372047/', false],
        ];
    }

    #[DataProvider('noindex')]
    public function testFunctionalPagesCarryNoindex(string $host, string $uri, bool $lleva): void
    {
        $respuesta = $this->pedirAlServidor($host, $uri);

        $this->assertArrayHasKey('x-content-type-options', $respuesta['headers'], 'headers are not being observed');
        $this->assertSame($lleva ? ['noindex'] : null, $respuesta['headers']['x-robots-tag'] ?? null, $uri);
    }

    /** A GEDCOM file is for whoever asked for it; a hidden collection's export must still look like a missing one (above). */
    public function testTheGedcomExportCarriesNoindex(): void
    {
        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, '/1048293/3/', ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(['noindex'], $respuesta['headers']['x-robots-tag']);
    }

    /** @return array<string, array{string, string}> the merged-away grupo's URL => where it moved to */
    public static function grupoFusionado(): array
    {
        return [
            'la página' => ['/582399/', '/582317/'],
            'la red' => ['/582399/1/', '/582317/1/'],
            'la dispersión' => ['/582399/2/', '/582317/2/'],
        ];
    }

    #[DataProvider('grupoFusionado')]
    public function testAMergedGroupRedirectsToTheOneThatStayed(string $vieja, string $nueva): void
    {
        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, $vieja);

        $this->assertSame(301, $respuesta['status']);
        $this->assertSame([$nueva], $respuesta['headers']['location']);
    }

    public function testASitemapGivesAPageItsDayOnlyWhenItsRowHasOne(): void
    {
        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, '/sitemap/10/1.xml');

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('<loc>http://tudominio.local/6128473101/</loc><lastmod>2026-03-04</lastmod>', $respuesta['body']);
        $this->assertStringContainsString('<loc>http://tudominio.local/6128473104/</loc></url>', $respuesta['body'], 'a row without a date says nothing');
    }

    /**
     * Every 404 and 301 is counted by the shape of its URL. The server writes the file as the request ends, so the
     * test asks again (briefly) until the line is there.
     *
     * @return list<string> the lines of today's file
     */
    private function lineasDeUrls(): array
    {
        $archivo = self::$dir . '/urls/urls-' . gmdate('Y-m-d') . '.tsv';
        for ($intento = 0; $intento < 20; $intento++) {
            clearstatcache();
            if (is_file($archivo)) {
                return file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            }
            usleep(50_000);
        }

        return [];
    }

    public function testA404AndA301AreCountedByShapeAndNeverByPath(): void
    {
        $this->pedirAlServidor(self::GENEALOGIA, '/6128473777/');                    // a persona that does not exist
        $this->pedirAlServidor(self::GENEALOGIA, '/maria-del-carmen-garcia/');       // a name typed into a URL
        $this->pedirAlServidor(self::GENEALOGIA, '/6128473105');                     // not canonical: 301
        $this->pedirAlServidor(self::GENEALOGIA, '/582399/1/');                      // a merged grupo: 301 to the one that stays
        $this->pedirAlServidor(self::POS, '/cart/');                                 // an old URL: 301 to /c/
        $this->pedirAlServidor(self::GENEALOGIA, '/wp-login.php');
        $this->pedirAlServidor(self::GENEALOGIA, '/6128473101/');                    // 200: not counted

        $lineas = $this->lineasDeUrls();
        $todo = implode("\n", $lineas);

        $this->assertContains("404\t/#10/\t-", $lineas);
        $this->assertContains("404\t/?/\t-", $lineas, 'a name with hyphens is a «?»');
        $this->assertContains("301\t/#10\t/#10/", $lineas);
        $this->assertContains("301\t/#6/#1/\t/#6/#1/", $lineas);
        $this->assertContains("301\t/cart/\t/c/", $lineas, 'the old URL is a word the site routes itself');
        $this->assertContains("404\t/*.php\t-", $lineas);
        $this->assertStringNotContainsString('maria', $todo);
        $this->assertStringNotContainsString('6128473', $todo, 'no id survives');
        $this->assertStringNotContainsString('garcia', $todo);
    }

    /**
     * The same URL, two answers: the page, or, with `X-Fragmento: 1`, only the list and its navigation (what the script
     * at /b.js appends). No second URL shape, and a request without the header (a crawler, a browser without JavaScript)
     * never gets a fragment.
     */
    public function testAListingAnswersThePageOrOnlyItsFragmentOnTheSameUrl(): void
    {
        $pagina = $this->pedirAlServidor(self::GENEALOGIA, '/b/6/zeta/2/');
        $fragmento = $this->pedirAlServidor(self::GENEALOGIA, '/b/6/zeta/2/', ['X-Fragmento' => '1']);

        $this->assertSame(200, $pagina['status']);
        $this->assertSame(200, $fragmento['status']);
        $this->assertStringContainsString('<html', $pagina['body']);
        $this->assertStringContainsString('<h1>Apellidos</h1>', $pagina['body']);
        $this->assertStringContainsString('<script src="/b.js"></script>', $pagina['body']);

        $this->assertStringNotContainsString('<html', $fragmento['body']);
        $this->assertStringNotContainsString('<h1>', $fragmento['body'], 'no page around it');
        $this->assertStringNotContainsString('<script', $fragmento['body']);
        $this->assertStringContainsString('<ul class="lista">', $fragmento['body']);
        $this->assertStringContainsString('href="/600051/"', $fragmento['body'], 'the first row of page 2');
        $this->assertStringContainsString('href="/600100/"', $fragmento['body']);
        $this->assertStringNotContainsString('href="/600050/"', $fragmento['body'], 'only page 2');
        $this->assertStringContainsString('rel="next"', $fragmento['body'], 'the fragment says where the next page is');
        $this->assertStringContainsString('href="/b/6/zeta/3/"', $fragmento['body']);
        $this->assertStringContainsString('rel="prev"', $fragmento['body']);
    }

    public function testTheFragmentOfTheLastPageHasNoNextLink(): void
    {
        $fragmento = $this->pedirAlServidor(self::GENEALOGIA, '/b/6/zeta/3/', ['X-Fragmento' => '1']);

        $this->assertStringContainsString('href="/600130/"', $fragmento['body']);
        $this->assertStringNotContainsString('rel="next"', $fragmento['body']);
    }

    /** Only the exact value 1 asks for a fragment: anything else is the page. */
    public function testOnlyTheExactHeaderValueAsksForAFragment(): void
    {
        foreach (['0', 'true', '11'] as $valor) {
            $this->assertStringContainsString('<html', $this->pedirAlServidor(self::GENEALOGIA, '/b/6/zeta/', ['X-Fragmento' => $valor])['body'], "X-Fragmento: {$valor}");
        }
    }

    /** The same URL varies with a request header: a cache has to be told. */
    public function testAListingVariesOnTheFragmentHeader(): void
    {
        foreach ([[], ['X-Fragmento' => '1']] as $cabeceras) {
            $this->assertContains('X-Fragmento', $this->pedirAlServidor(self::GENEALOGIA, '/b/6/zeta/', $cabeceras)['headers']['vary'] ?? [], json_encode($cabeceras) ?: '');
        }
    }

    public function testThePosListingHasTheFragmentToo(): void
    {
        $pagina = $this->pedirAlServidor(self::POS, '/b/8/');
        $fragmento = $this->pedirAlServidor(self::POS, '/b/8/', ['X-Fragmento' => '1']);

        $this->assertStringContainsString('<html', $pagina['body']);
        $this->assertStringNotContainsString('<html', $fragmento['body']);
        $this->assertStringContainsString('<ul class="lista">', $fragmento['body']);
    }

    /** The script is a plain file under public/: a real web server serves it itself, the built-in one is let to. */
    public function testTheScriptIsServedAndNothingElseUnderPublic(): void
    {
        $script = $this->pedirAlServidor(self::GENEALOGIA, '/b.js');
        $this->assertSame(200, $script['status']);
        $this->assertStringContainsString('IntersectionObserver', $script['body']);
        $this->assertMatchesRegularExpression('#javascript#', $script['headers']['content-type'][0] ?? '');

        $this->assertSame(404, $this->pedirAlServidor(self::GENEALOGIA, '/.htaccess')['status'], 'a dotfile');
        $this->assertSame(404, $this->pedirAlServidor(self::GENEALOGIA, '/index.php')['status'], 'the front controller is not a file to read');
        foreach (['/../Support/Router.php', '/..%2fSupport%2fRouter.php', '/../.env.example'] as $fuera) {
            $respuesta = $this->pedirAlServidor(self::GENEALOGIA, $fuera);
            $this->assertStringNotContainsString('<?php', $respuesta['body'], $fuera);
            $this->assertStringNotContainsString('IDS_DIGITO_VERIFICADOR', $respuesta['body'], $fuera);
        }
    }

    /** No browser here: at least that the script is valid JavaScript, when node is around. */
    public function testTheScriptParses(): void
    {
        if (trim((string)shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node is not installed');
        }
        exec('node --check ' . escapeshellarg(dirname(__DIR__, 3) . '/public/b.js') . ' 2>&1', $salida, $codigo);

        $this->assertSame(0, $codigo, implode("\n", $salida));
    }

    public function testAGroupThatNeverExistedIsStillA404(): void
    {
        $this->assertSame(404, $this->pedirAlServidor(self::GENEALOGIA, '/582398/')['status']);
    }

    /** The owner's pages do depend on who asks, so a shared cache must not keep them. */
    public function testTheOwnersPagesAreMarkedPrivate(): void
    {
        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, '/1048294/', ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(['private, no-store'], $respuesta['headers']['cache-control']);
        $this->assertContains('Authorization', $respuesta['headers']['vary']);
    }

    /** What is known to exist does ask for the credentials; only its absence is a 404. */
    public function testTheAccountAreaAsksForCredentialsInsteadOfPretendingNotToExist(): void
    {
        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, '/0/');

        $this->assertSame(401, $respuesta['status']);
        $this->assertArrayHasKey('www-authenticate', $respuesta['headers']);
    }

    /**
     * ob_gzhandler already sends `Vary: Accept-Encoding` on every response, compressed or not, so
     * HttpSecurityHeaders must not send its own: it was harmless but every response carried the same
     * header twice. It has to be there exactly once, also next to the owner's `Vary: Authorization`.
     */
    public function testVaryAcceptEncodingIsSentOnceWhetherOrNotTheResponseIsCompressed(): void
    {
        if (!extension_loaded('zlib')) {
            $this->markTestSkipped('Without zlib the app neither compresses nor sends Vary: Accept-Encoding.');
        }

        $propietario = ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)];
        $casos = [
            'home, sin comprimir' => ['/', []],
            'home, comprimida' => ['/', ['Accept-Encoding' => 'gzip']],
            'un 404, comprimido' => ['/99999999/', ['Accept-Encoding' => 'gzip']],
            'pagina del propietario, sin comprimir' => ['/1048294/', $propietario],
            'pagina del propietario, comprimida' => ['/1048294/', $propietario + ['Accept-Encoding' => 'gzip']],
        ];
        foreach ($casos as $nombre => [$uri, $cabeceras]) {
            $respuesta = $this->pedirAlServidor(self::GENEALOGIA, $uri, $cabeceras);

            $valores = [];
            foreach ($respuesta['headers']['vary'] ?? [] as $linea) {
                foreach (explode(',', $linea) as $valor) {
                    $valores[] = strtolower(trim($valor));
                }
            }
            $this->assertSame(1, count(array_keys($valores, 'accept-encoding', true)), "{$nombre}: Vary was " . implode(' | ', $respuesta['headers']['vary'] ?? ['(none)']));
        }
    }

    // --- sessions: only for whoever needs one -------------------------------------------------------

    private static function archivosDeSesion(): int
    {
        return count(glob(self::$dir . '/sesiones/sess_*') ?: []);
    }

    private static function cookieDeSesion(array $respuesta): ?string
    {
        foreach ($respuesta['headers']['set-cookie'] ?? [] as $cookie) {
            if (preg_match('/^PHPSESSID=([^;]+)/', $cookie, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /** @return array<string, array{string, string}> [host, uri] */
    public static function paginasQueSoloSeLeen(): array
    {
        return [
            'inicio' => [self::GENEALOGIA, '/'],
            'una persona' => [self::GENEALOGIA, '/6128473101/'],
            'una persona que podria vivir' => [self::GENEALOGIA, '/6128473105/'],
            'la busqueda' => [self::GENEALOGIA, '/b/10/garcia/'],
            'una pagina que no existe' => [self::GENEALOGIA, '/99999999/'],
            'una coleccion privada' => [self::GENEALOGIA, '/1048294/'],
            'el POS' => [self::POS, '/'],
        ];
    }

    /**
     * Reading a page is not a reason to open a session: a visitor who only looks gets no cookie, and the server
     * keeps nothing about them. A crawler never sends the cookie back, so with a session per request each of its
     * hits used to leave a file behind.
     */
    #[DataProvider('paginasQueSoloSeLeen')]
    public function testAVisitorWhoOnlyReadsGetsNoCookieAndLeavesNoSession(string $host, string $uri): void
    {
        $antes = self::archivosDeSesion();

        $respuesta = $this->pedirAlServidor($host, $uri);

        $this->assertNotSame(0, $respuesta['status']);
        $this->assertArrayNotHasKey('set-cookie', $respuesta['headers'], "{$uri} must not set a cookie");
        $this->assertSame($antes, self::archivosDeSesion(), "{$uri} must not leave a session file");
    }

    /**
     * Not opening a session must not make a page cacheable: PHP's session was what sent these headers, and a
     * withdrawal has to take effect on the next request, not when a cache expires.
     */
    #[DataProvider('paginasQueSoloSeLeen')]
    public function testWithoutASessionEveryResponseIsStillNotCacheable(string $host, string $uri): void
    {
        $respuesta = $this->pedirAlServidor($host, $uri);

        $this->assertSame(['no-store, no-cache, must-revalidate'], $respuesta['headers']['cache-control'], $uri);
        $this->assertSame(['no-cache'], $respuesta['headers']['pragma'], $uri);
        $this->assertSame(['Thu, 19 Nov 1981 08:52:00 GMT'], $respuesta['headers']['expires'], $uri);
    }

    /** A page with a form needs a CSRF token that survives until the POST, so it is what starts the session. */
    public function testAPageWithAFormStartsASession(): void
    {
        $antes = self::archivosDeSesion();

        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, '/r/6128473105/');

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('name="csrf_token"', $respuesta['body']);
        $cookie = self::cookieDeSesion($respuesta);
        $this->assertNotNull($cookie, 'the form has a token to keep, so it sets the cookie');
        $this->assertSame($antes + 1, self::archivosDeSesion());
        $this->assertStringContainsString('HttpOnly', implode(';', $respuesta['headers']['set-cookie']));
        $this->assertStringContainsString('SameSite=Lax', implode(';', $respuesta['headers']['set-cookie']));
    }

    /**
     * The owner who logs in with the credentials gets a session, and later requests with only the cookie are
     * recognised as theirs: browsing public pages after /0/ still shows what only the owner sees.
     */
    public function testTheOwnerGetsASessionWhenLoggingInAndItIsResumedWithJustTheCookie(): void
    {
        $respuesta = $this->pedirAlServidor(self::GENEALOGIA, '/0/', ['Authorization' => 'Basic ' . base64_encode('propietario:' . self::TOKEN)]);
        $this->assertSame(200, $respuesta['status']);
        $cookie = self::cookieDeSesion($respuesta);
        $this->assertNotNull($cookie, 'logging in opens the session');

        // Only the cookie now: no credentials. The collection is private, so this is the owner or a 404.
        $conCookie = $this->pedirAlServidor(self::GENEALOGIA, '/1048294/', ['Cookie' => "PHPSESSID={$cookie}"]);
        $this->assertSame(200, $conCookie['status'], 'the session is the owner\'s');
        $this->assertSame(['private, no-store'], $conCookie['headers']['cache-control']);

        $sinCookie = $this->pedirAlServidor(self::GENEALOGIA, '/1048294/');
        $this->assertSame(404, $sinCookie['status'], 'without the cookie it is the visitor\'s 404');
    }
}
