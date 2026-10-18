<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * End to end: real index.php, router, controllers, repositories and views,
 * against a migrated + seeded SQLite database per site. Each request runs in
 * its own PHP process (see tests/Support/request.php).
 */
class PagesTest extends TestCase
{
    private const GENEALOGIA = 'tudominio.local';
    private const POS = 'contrastocolor.local';
    private const TOKEN = 'token-de-prueba-0123456789';

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-pages-' . getmypid();
        @mkdir(self::$dir, 0777, true);

        foreach (['genealogy', 'pos'] as $site) {
            $file = self::$dir . "/{$site}.sqlite";
            @unlink($file);
            $migrator = Migrator::forSite(new Database("sqlite:{$file}"), $site);
            $migrator->migrate();
            $migrator->seed();
        }
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$dir));
    }

    /**
     * Runs one request. $tokenConfigurado is the server's OWNER_TOKEN (none by
     * default, the fail-closed case) and $autorizacion the request's
     * Authorization header (none by default, an anonymous visitor).
     *
     * @return array{int, string, string} [status, body, sessionId actually used]
     */
    private function get(
        string $host,
        string $uri,
        string $method = 'GET',
        string $postBody = '',
        ?string $sessionId = null,
        ?string $tokenConfigurado = null,
        ?string $autorizacion = null,
        array $entorno = []
    ): array {
        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/request.php',
            $host, $uri, $method, $postBody, $sessionId ?? '', $autorizacion ?? ''];

        // $entorno first: what a test passes wins over the defaults (e.g. a database of its own).
        $env = $entorno + [
            'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
            'DB_DSN_POS' => 'sqlite:' . self::$dir . '/pos.sqlite',
            'LOG_FILE' => self::$dir . '/app.log',
            'PATH' => (string)getenv('PATH'),
        ];
        if ($tokenConfigurado !== null) {
            $env['OWNER_TOKEN'] = $tokenConfigurado;
        }

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $body = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertMatchesRegularExpression('/STATUS:\d{3}$/', $stderr, "No status for {$uri}; stderr: {$stderr}");
        $this->assertStringNotContainsString('Warning', $stderr, "PHP warning on {$uri}: {$stderr}");
        $this->assertStringNotContainsString('Deprecated', $stderr, "PHP deprecation on {$uri}: {$stderr}");

        preg_match('/^SESSION:(.*)$/m', $stderr, $ms);

        return [(int)substr($stderr, -3), $body, $ms[1] ?? ''];
    }

    /**
     * Starts a session with a real CSRF token by visiting a page that renders one (a
     * product's variantes page always has an "agregar al carrito" form), for tests
     * that need to POST a cart mutation.
     *
     * @return array{string, string} [sessionId, csrfToken]
     */
    private function primeSession(): array
    {
        [, $body, $sid] = $this->get(self::POS, '/81372047/1/');
        preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m, 'Could not read a CSRF token from the primed page');

        return [$sid, $m[1]];
    }

    /** A search's canonical URL, as UrlHelper::busqueda() writes it (page 1, text without digits only). */
    private static function buscar(int $digitos, string $texto): string
    {
        return "/b/{$digitos}/" . str_replace('.', '%2e', strtolower(rawurlencode(mb_strtolower($texto)))) . '/';
    }

    /** The credentials as a browser sends them: HTTP Basic with any user name. */
    private static function basic(string $clave): string
    {
        return 'Basic ' . base64_encode('propietario:' . $clave);
    }

    /** A request from someone who is not logged in, on a server that has an OWNER_TOKEN. */
    private function getConTokenConfigurado(string $host, string $uri, ?string $autorizacion = null): array
    {
        return $this->get($host, $uri, tokenConfigurado: self::TOKEN, autorizacion: $autorizacion);
    }

    /** A request carrying the owner's credentials. */
    private function getComoPropietario(string $host, string $uri): array
    {
        return $this->getConTokenConfigurado($host, $uri, self::basic(self::TOKEN));
    }

    private function post(string $host, string $uri, array $params, string $sessionId, string $token): array
    {
        $postBody = http_build_query($params + ['csrf_token' => $token]);
        return $this->get($host, $uri, 'POST', $postBody, $sessionId);
    }

    /** @return array<string, array{string, string, int, list<string>}> */
    public static function paginas(): array
    {
        return [
            'persona' => [self::GENEALOGIA, '/6128473105/', 200, ['Juan García Hernández', '14 feb 1925', 'Elena Martínez Soto', 'Cónyuge']],
            'ascendencia' => [self::GENEALOGIA, '/6128473108/1/', 200, ['Abuelos', 'Antonio García Fernández', 'Bisabuelos', 'José García Álvarez']],
            'descendencia' => [self::GENEALOGIA, '/6128473101/2/', 200, ['Nietos', 'Juan García Hernández', 'Bisnietos', 'Lucía García Martínez']],
            'vinculos' => [self::GENEALOGIA, '/6128473106/3/', 200, ['Padrino/madrina', 'Carlos García Martínez']],
            'cronologia' => [self::GENEALOGIA, '/6128473103/4/', 200, ['Migración', 'Llega a Guadalajara desde Oviedo.']],
            'suceso' => [self::GENEALOGIA, '/412000006/', 200, ['Matrimonio', 'contrayente', 'Acta de matrimonio']],
            'registro fuente' => [self::GENEALOGIA, '/81372001/1/', 200, ['Parroquia de San José de Analco', 'foja 112']],
            'coleccion arbol' => [self::GENEALOGIA, '/1048293/1/', 200, ['class="arbol"', 'Carlos García Martínez']],
            'grupo dispersion' => [self::GENEALOGIA, '/582317/2/', 200, ['Guadalajara', '50.0']],
            'organizacion miembros' => [self::GENEALOGIA, '/10232/1/', 200, ['Elena Martínez Soto', 'feligrés']],
            'lugar' => [self::GENEALOGIA, '/mx/jal/', 200, ['Jalisco', 'Tlaquepaque']],
            'lugar personas' => [self::GENEALOGIA, '/mx/jal/tlq/1/', 200, ['Rosa García Hernández']],
            'listado personas' => [self::GENEALOGIA, '/b/10/mart%c3%adnez/', 200, ['Carlos García Martínez', 'Lucía García Martínez']],
            'producto' => [self::POS, '/81372047/', 200, ['Camiseta básica', '$199.00 MXN', 'Algodón orgánico', 'Agotado']],
            'producto descontinuado' => [self::POS, '/81372050/', 200, ['descontinuado']],
            'variantes' => [self::POS, '/81372048/1/', 200, ['$749.00 MXN', '$699.00 MXN']],
            'atributos' => [self::POS, '/81372047/2/', 200, ['Color', 'Talla', 'Azul marino']],
            'atributo productos' => [self::POS, '/48215/1/', 200, ['Sudadera con capucha']],
            'coleccion' => [self::POS, '/7300001/', 200, ['Primavera 2026', 'Sudadera con capucha']],
            'grupo' => [self::POS, '/1001/', 200, ['Camisetas', 'Sudaderas', 'Camiseta básica']],
            'listado productos' => [self::POS, '/b/8/', 200, ['Gorra de seis paneles']],
        ];
    }

    /** As the owner, who sees everything: what a visitor sees of living people is tested below. */
    #[DataProvider('paginas')]
    public function testPageRendersFromTheDatabase(string $host, string $uri, int $status, array $esperado): void
    {
        [$codigo, $body] = $this->getComoPropietario($host, $uri);

        $this->assertSame($status, $codigo, $body);
        foreach ($esperado as $texto) {
            $this->assertStringContainsString($texto, $body, "Missing «{$texto}» on {$uri}");
        }
    }

    /** @return array<string, array{string, string, int}> */
    public static function errores(): array
    {
        return [
            'persona inexistente' => [self::GENEALOGIA, '/6128473199/', 404],
            'accion inexistente' => [self::GENEALOGIA, '/6128473105/9/', 404],
            'lugar inexistente' => [self::GENEALOGIA, '/zz/', 404],
            'listado tipo invalido' => [self::GENEALOGIA, '/b/99/', 400],
            'producto inexistente' => [self::POS, '/99999999/', 404],
        ];
    }

    #[DataProvider('errores')]
    public function testErrorStatus(string $host, string $uri, int $status): void
    {
        [$codigo] = $this->get($host, $uri);

        $this->assertSame($status, $codigo);
    }

    /** The brand comes from .env, not from the view: the genealogy header and title follow SITE_NAME_GENEALOGY. */
    public function testTheGenealogyHeaderShowsTheConfiguredBrand(): void
    {
        [, $body] = $this->get(self::GENEALOGIA, '/', entorno: ['SITE_NAME_GENEALOGY' => 'conce.com']);

        $this->assertStringContainsString('<a class="marca" href="/">conce.com</a>', $body);
        $this->assertStringContainsString(' · conce.com</title>', $body);
    }

    /** Nothing configured (or blank) leaves the plain name of the site; the POS keeps its own name either way. */
    public function testTheGenealogyHeaderFallsBackWhenNoBrandIsConfigured(): void
    {
        foreach ([[], ['SITE_NAME_GENEALOGY' => '  ']] as $entorno) {
            [, $body] = $this->get(self::GENEALOGIA, '/', entorno: $entorno);
            $this->assertStringContainsString('<a class="marca" href="/">Genealogía</a>', $body);
        }

        [, $body] = $this->get(self::POS, '/', entorno: ['SITE_NAME_GENEALOGY' => 'conce.com']);
        $this->assertStringContainsString('<a class="marca" href="/">Contrastocolor</a>', $body);
        $this->assertStringNotContainsString('conce.com', $body);
    }

    /** The brand is text a deployer types: it must come out escaped. */
    public function testTheConfiguredBrandIsEscaped(): void
    {
        [, $body] = $this->get(self::GENEALOGIA, '/', entorno: ['SITE_NAME_GENEALOGY' => '<b>x</b> & "y"']);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt; &amp; &quot;y&quot;', $body);
        $this->assertStringNotContainsString('<b>x</b>', $body);
    }

    /** @return array<string, array{string, string}> */
    public static function noCanonicas(): array
    {
        return [
            'sin barra final' => [self::GENEALOGIA, '/6128473105'],
            'mayusculas' => [self::GENEALOGIA, '/MX/JAL/'],
            'barras dobles' => [self::GENEALOGIA, '//mx//jal/'],
            'pos sin barra' => [self::POS, '/81372047'],
            'carrito en mayuscula' => [self::POS, '/C/'],
            'carrito, ruta antigua' => [self::POS, '/cart/'],
            'pago, ruta antigua' => [self::POS, '/checkout/'],
            'pago, paso antiguo' => [self::POS, '/checkout/payment/'],
        ];
    }

    /** The Location itself isn't observable here (see Support/request.php); Tests\App\Routing\CanonicaTest checks it. */
    #[DataProvider('noCanonicas')]
    public function testNonCanonicalUrlsRedirectPermanently(string $host, string $uri): void
    {
        $this->assertSame(301, $this->get($host, $uri)[0]);
    }

    public function testAMergedPersonaIdRedirectsToThePersonaThatStayed(): void
    {
        $this->assertSame(301, $this->get(self::GENEALOGIA, '/6128473188/')[0]);
        $this->assertSame(301, $this->get(self::GENEALOGIA, '/6128473188/1/')[0], 'and keeps the action');
        $this->assertSame(404, $this->get(self::GENEALOGIA, '/6128473187/')[0], 'an id that never existed is still a 404');
    }

    public function testRobotsKeepsCrawlersOutOfTheAccountAndPointsAtTheSitemap(): void
    {
        [$codigo, $robots] = $this->get(self::GENEALOGIA, '/robots.txt');

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Disallow: /0/', $robots);
        $this->assertStringContainsString('Sitemap: http://tudominio.local/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /b/', $robots, 'search results');

        [, $pos] = $this->get(self::POS, '/robots.txt');
        foreach (['/0/', '/b/', '/c/', '/p/'] as $ruta) {
            $this->assertStringContainsString("Disallow: {$ruta}\n", $pos);
        }
        $this->assertStringContainsString('Sitemap: http://contrastocolor.local/sitemap.xml', $pos);
    }

    public function testPosSitemapListsTheCatalogOnly(): void
    {
        [, $indice] = $this->get(self::POS, '/sitemap.xml');
        foreach (['8', '7', '6', '5', '4'] as $tipo) {
            $this->assertStringContainsString("<loc>http://contrastocolor.local/sitemap/{$tipo}/1.xml</loc>", $indice);
        }
        $this->assertStringNotContainsString('/sitemap/16/', $indice, 'orders are never listed');

        [$codigo, $productos] = $this->get(self::POS, '/sitemap/8/1.xml');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('<loc>http://contrastocolor.local/81372047/</loc>', $productos);
        $this->assertStringNotContainsString('/81372050/', $productos, 'discontinued');
        $this->assertSame(404, $this->get(self::POS, '/sitemap/16/1.xml')[0]);
    }

    public function testSitemapIndexListsAPartPerType(): void
    {
        [$codigo, $indice] = $this->get(self::GENEALOGIA, '/sitemap.xml');

        $this->assertSame(200, $codigo);
        foreach (['10', '9', '8', '7', '6', '5', 'l'] as $tipo) {
            $this->assertStringContainsString("<loc>http://tudominio.local/sitemap/{$tipo}/1.xml</loc>", $indice);
        }
    }

    /** Even for the owner: a sitemap is what a visitor may see, whoever asks for it. */
    public function testSitemapListsOnlyWhatAVisitorMaySee(): void
    {
        [, $personas] = $this->getComoPropietario(self::GENEALOGIA, '/sitemap/10/1.xml');
        $this->assertStringContainsString('<loc>http://tudominio.local/6128473101/</loc>', $personas);
        $this->assertStringNotContainsString(self::CARLOS, $personas, 'alive');
        $this->assertStringNotContainsString(self::MANUEL, $personas, 'only in a private colección');

        [, $colecciones] = $this->getComoPropietario(self::GENEALOGIA, '/sitemap/7/1.xml');
        $this->assertStringContainsString('/1048293/', $colecciones);
        $this->assertStringNotContainsString('/1048294/', $colecciones, 'private');

        [, $lugares] = $this->get(self::GENEALOGIA, '/sitemap/l/1.xml');
        $this->assertStringContainsString('<loc>http://tudominio.local/mx/jal/gdl/</loc>', $lugares);
    }

    public function testSitemapPartsThatDontExistAre404(): void
    {
        foreach (['/sitemap/10/2.xml', '/sitemap/99/1.xml', '/sitemap/10/0.xml', '/sitemap/10/1.txt', '/sitemap/10.xml'] as $uri) {
            $this->assertSame(404, $this->get(self::GENEALOGIA, $uri)[0], $uri);
        }
    }

    public function testAPostIsNeverRedirected(): void
    {
        // A 301 would turn it into a GET: it is answered where it was sent (403: no CSRF token).
        $this->assertSame(403, $this->get(self::POS, '/C/1/', 'POST', 'sku=CAM-AZ-M')[0]);
    }

    public function testOrdersAreOnlyReachableByTheirCode(): void
    {
        $this->assertSame(404, $this->getComoPropietario(self::POS, '/order/8137204719000/')[0], 'the old /order/ path is gone');
        $this->assertSame(404, $this->getComoPropietario(self::POS, '/0008137204719000/')[0], 'the row id, padded, is not a code');
        $this->assertSame(200, $this->getComoPropietario(self::POS, '/4821937065182734/')[0]);
    }

    public function testTheSearchFormIsRedirectedToItsPath(): void
    {
        $this->assertSame(301, $this->get(self::GENEALOGIA, '/?t=10&q=Garc%C3%ADa')[0]);
        $this->assertSame(400, $this->get(self::GENEALOGIA, '/?t=x')[0]);
    }

    /** @return array<string, array{string, string, int}> */
    public static function busquedas(): array
    {
        return [
            'todo' => [self::GENEALOGIA, '/b/10/', 200],
            'texto' => [self::GENEALOGIA, '/b/10/jos%c3%a9/', 200],
            'pagina' => [self::POS, '/b/8/2/', 200],
            'texto solo de digitos, con su pagina' => [self::POS, '/b/8/2026/1/', 200],
            'pagina 1 explicita' => [self::GENEALOGIA, '/b/10/jose/1/', 301],
            'texto en mayusculas codificadas' => [self::GENEALOGIA, '/b/10/jos%C3%89/', 301],
            'sin tipo' => [self::GENEALOGIA, '/b/', 404],
            'pagina que no es numero' => [self::GENEALOGIA, '/b/10/jose/x/', 404],
            'pagina cero' => [self::GENEALOGIA, '/b/10/jose/0/', 404],
            'demasiados segmentos' => [self::GENEALOGIA, '/b/10/jose/2/x/', 404],
            'texto que no es UTF-8' => [self::GENEALOGIA, '/b/10/%ff/', 404],
        ];
    }

    #[DataProvider('busquedas')]
    public function testSearchPaths(string $host, string $uri, int $status): void
    {
        $this->assertSame($status, $this->get($host, $uri)[0], $uri);
    }

    public function testASearchPathFindsWhatTheFormDid(): void
    {
        [, $body] = $this->get(self::GENEALOGIA, '/b/10/jos%c3%a9/');

        $this->assertStringContainsString('José García Álvarez', $body);
        $this->assertStringContainsString('Resultados para «josé»', $body);
        $this->assertStringContainsString('José García Álvarez', $this->get(self::GENEALOGIA, '/b/10/alvarez/')[1], 'without the accent');
    }

    /** @return array<string, array{string}> */
    public static function noSonLugares(): array
    {
        return [
            '1 letra: funcion' => ['/x/'],
            '3 letras: idioma' => ['/eng/'],
            '4+ letras: texto' => ['/mexico/'],
            'subdivision de 4 letras' => ['/mx/jali/'],
        ];
    }

    /** A place starts with a 2-letter country (ISO 3166-1); below it, codes of 1-3 letters. */
    #[DataProvider('noSonLugares')]
    public function testOnlyACountryStartsAPlace(string $uri): void
    {
        $this->assertSame(404, $this->get(self::GENEALOGIA, $uri)[0], $uri);
    }

    public function testHiddenPersonaPagesAreKeptOutOfSearchEngines(): void
    {
        $noindex = '<meta name="robots" content="noindex">';

        $this->assertStringContainsString($noindex, $this->get(self::GENEALOGIA, self::CARLOS)[1], 'alive');
        $this->assertStringContainsString($noindex, $this->get(self::GENEALOGIA, self::MANUEL . '1/')[1], 'private');
        $this->assertStringNotContainsString($noindex, $this->get(self::GENEALOGIA, '/6128473101/')[1]);
        $this->assertStringNotContainsString($noindex, $this->getComoPropietario(self::GENEALOGIA, self::CARLOS)[1], 'not hidden from the owner');
    }

    public function testEveryPageNamesItsCanonicalUrlAndItsOtherLanguages(): void
    {
        [, $body] = $this->get('spa.tudominio.local', '/6128473101/');

        $this->assertStringContainsString('<link rel="canonical" href="http://spa.tudominio.local/6128473101/">', $body);
        $this->assertStringContainsString('<link rel="alternate" hreflang="es" href="http://spa.tudominio.local/6128473101/">', $body);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="http://eng.tudominio.local/6128473101/">', $body);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="http://tudominio.local/6128473101/">', $body);

        [, $pos] = $this->get(self::POS, '/81372047/');
        $this->assertStringContainsString('hreflang="en" href="http://eng.contrastocolor.local/81372047/"', $pos);
    }

    /** @return array<string, array{string, string, string}> host, uri, what the 404 must say */
    public static function pistas(): array
    {
        return [
            'id sin ceros' => [self::GENEALOGIA, '/47/', '<a href="/0000000047/">/0000000047/</a> (persona)'],
            'id demasiado largo' => [self::GENEALOGIA, '/123456789012/', 'Ningún tipo usa 12 dígitos'],
            'accion que no existe' => [self::GENEALOGIA, '/6128473101/9/', 'Sus acciones: 1 ascendencia, 2 descendencia'],
            'tipo sin acciones' => [self::POS, '/7300001/1/', 'solo el id, sin acciones'],
            'idioma en la ruta' => [self::GENEALOGIA, '/eng/', 'el idioma va en el subdominio'],
            'palabra' => [self::POS, '/carrito/', 'no llevan palabras'],
            'lugar demasiado hondo' => [self::GENEALOGIA, '/mx/jal/gdl/x/', 'Un lugar es país'],
        ];
    }

    #[DataProvider('pistas')]
    public function testA404SaysWhyFromTheShapeOfTheUrl(string $host, string $uri, string $pista): void
    {
        [$codigo, $body] = $this->get($host, $uri);

        $this->assertSame(404, $codigo);
        $this->assertStringContainsString($pista, $body);
    }

    public function testA404NeverSuggestsAnOrderCode(): void
    {
        $this->assertStringNotContainsString('(orden)', $this->get(self::POS, '/47/')[1]);
    }

    public function testCheckoutPlacesTheOrderAndOnlyItsSessionCanOpenIt(): void
    {
        $db = new Database('sqlite:' . self::$dir . '/pos.sqlite');
        $direccion = 'Calle Prueba 123, Guadalajara, JAL 44100, MX';
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'SUD-RO-M', 'cantidad' => '2'], $sid, $token);

        try {
            $this->assertSame(303, $this->post(self::POS, '/p/1/', ['direccion' => $direccion], $sid, $token)[0]);
            $this->assertSame(200, $this->get(self::POS, '/p/2/', 'GET', '', $sid)[0]);
            [, $confirmar] = $this->get(self::POS, '/p/3/', 'GET', '', $sid);
            $this->assertStringContainsString($direccion, $confirmar);
            $this->assertStringContainsString('Confirmar compra', $confirmar);

            $this->assertSame(303, $this->post(self::POS, '/p/3/', [], $sid, $token)[0]);

            $orden = $db->fetchOne('SELECT id, codigo, estado FROM ordenes WHERE direccion_envio = ?', [$direccion]);
            $this->assertNotNull($orden);
            $this->assertSame('pendiente', $orden['estado']);
            $this->assertMatchesRegularExpression('/^[1-9]\d{15}$/', (string)$orden['codigo']);
            $this->assertSame(6, (int)$db->fetchValue("SELECT stock FROM variantes WHERE sku = 'SUD-RO-M'"), '8 - 2');
            $this->assertSame(2, (int)$db->fetchValue('SELECT cantidad FROM orden_items WHERE orden_id = ?', [$orden['id']]));

            [$codigo, $pagina] = $this->get(self::POS, "/{$orden['codigo']}/", 'GET', '', $sid);
            $this->assertSame(200, $codigo, 'the session that placed it');
            $this->assertStringContainsString($direccion, $pagina);
            $this->assertStringContainsString('¡Gracias!', $pagina);
            $this->assertStringContainsString('El carrito está vacío.', $this->get(self::POS, '/c/', 'GET', '', $sid)[1]);
            $this->assertSame(404, $this->get(self::POS, "/{$orden['codigo']}/")[0], 'anyone else');
        } finally {
            $db->execute("UPDATE variantes SET stock = 8 WHERE sku = 'SUD-RO-M'");
            $ids = 'SELECT id FROM ordenes WHERE direccion_envio = ?';
            $db->execute("DELETE FROM orden_eventos WHERE orden_id IN ({$ids})", [$direccion]);
            $db->execute("DELETE FROM orden_items WHERE orden_id IN ({$ids})", [$direccion]);
            $db->execute('DELETE FROM ordenes WHERE direccion_envio = ?', [$direccion]);
        }
    }

    public function testCheckoutStepsNeedAnAddressFirst(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        $this->assertSame(303, $this->get(self::POS, '/p/2/', 'GET', '', $sid)[0]);
        $this->assertSame(303, $this->post(self::POS, '/p/3/', [], $sid, $token)[0], 'no order without an address');
        $this->assertSame(400, $this->post(self::POS, '/p/1/', ['direccion' => 'corta'], $sid, $token)[0]);
        $this->assertSame(403, $this->post(self::POS, '/p/1/', ['direccion' => 'Calle Prueba 123, Ciudad'], $sid, 'otro-token')[0]);
    }

    /** @return list<array> the page's JSON-LD objects */
    private static function jsonLd(string $body): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $body, $m);
        return array_map(static fn(string $j): array => json_decode($j, true, flags: JSON_THROW_ON_ERROR), $m[1]);
    }

    public function testPlacePagesHaveBreadcrumbs(): void
    {
        [, $body] = $this->get(self::GENEALOGIA, '/mx/jal/gdl/1/');

        $this->assertMatchesRegularExpression('#aria-label="Migas de pan">\s*<a href="/">Inicio</a> › <a href="/mx/">México</a> › <a href="/mx/jal/">Jalisco</a> › <a href="/mx/jal/gdl/">Guadalajara</a> › <span aria-current="page">Personas nacidas aquí</span>#', $body);
        $migas = array_values(array_filter(self::jsonLd($body), static fn(array $o): bool => $o['@type'] === 'BreadcrumbList'))[0];
        $this->assertSame(['Inicio', 'México', 'Jalisco', 'Guadalajara', 'Personas nacidas aquí'], array_column($migas['itemListElement'], 'name'));
        $this->assertSame('http://tudominio.local/mx/jal/', $migas['itemListElement'][2]['item']);
    }

    public function testAVisiblePersonaIsDescribedForSearchEnginesAndAHiddenOneIsNot(): void
    {
        $personas = array_values(array_filter(self::jsonLd($this->get(self::GENEALOGIA, '/6128473101/')[1]), static fn(array $o): bool => $o['@type'] === 'Person'));
        $this->assertSame('José García Álvarez', $personas[0]['name']);
        $this->assertSame('http://tudominio.local/6128473101/', $personas[0]['url']);

        foreach ([self::CARLOS, self::MANUEL] as $oculta) {
            $tipos = array_column(self::jsonLd($this->get(self::GENEALOGIA, $oculta)[1]), '@type');
            $this->assertNotContains('Person', $tipos, $oculta);
        }
    }

    public function testAProductIsDescribedWithItsPriceAndAvailability(): void
    {
        $producto = static fn(string $body): array => array_values(array_filter(self::jsonLd($body), static fn(array $o): bool => $o['@type'] === 'Product'))[0];

        $camiseta = $producto($this->get(self::POS, '/81372047/')[1]);
        $this->assertSame('Camiseta básica', $camiseta['name']);
        $this->assertSame(['@type' => 'Offer', 'price' => '199.00', 'priceCurrency' => 'MXN', 'availability' => 'https://schema.org/InStock'], $camiseta['offers']);
        $this->assertSame('https://schema.org/Discontinued', $producto($this->get(self::POS, '/81372050/')[1])['offers']['availability']);
    }

    public function testOrdersCannotBeListedThroughTheSearch(): void
    {
        $this->assertSame(400, $this->getComoPropietario(self::POS, '/b/16/')[0]);
    }

    public function testSearchTreatsPercentLiterally(): void
    {
        [, $body] = $this->get(self::POS, '/b/8/%25/');

        $this->assertStringContainsString('Sin productos disponibles', $body);
    }

    /**
     * What only the owner may see: [host, uri, a string that must never reach anyone
     * else, the status an anonymous visitor gets when a token IS configured]. Private
     * data answers 404 so it can't be told from a missing id; the account area and the
     * edit page of a public tree answer 401 so the owner can log in from there.
     *
     * @return array<string, array{string, string, string, int}>
     */
    public static function privadas(): array
    {
        return [
            'coleccion privada' => [self::GENEALOGIA, '/1048294/', 'Borrador de Luis', 404],
            'coleccion privada, arbol' => [self::GENEALOGIA, '/1048294/1/', 'Borrador de Luis', 404],
            'coleccion privada, editar' => [self::GENEALOGIA, '/1048294/2/', 'Borrador de Luis', 404],
            'coleccion privada, GEDCOM' => [self::GENEALOGIA, '/1048294/3/', ' INDI', 404],
            'coleccion publica, editar' => [self::GENEALOGIA, '/1048293/2/', 'Visibilidad', 401],
            'cuenta' => [self::GENEALOGIA, '/0/', 'ana@example.com', 401],
            'cuenta, colecciones' => [self::GENEALOGIA, '/0/1/', 'Familia García', 401],
            'cuenta, aportes' => [self::GENEALOGIA, '/0/2/', 'José García Álvarez', 401],
            'cuenta pos' => [self::POS, '/0/', 'ana@example.com', 401],
            'cuenta pos, perfil' => [self::POS, '/0/1/', 'ana@example.com', 401],
            'cuenta pos, ordenes' => [self::POS, '/0/2/', '4821937065182734', 401],
            'cuenta pos, deseos' => [self::POS, '/0/3/', 'Ana Demo', 401],
            'cuenta pos, direcciones' => [self::POS, '/0/4/', 'Av. Juárez 100', 401],
            'cuenta pos, preferencias' => [self::POS, '/0/5/', 'Ana Demo', 401],
            'pedido' => [self::POS, '/4821937065182734/', '647.00', 404],
            'pedido, factura' => [self::POS, '/4821937065182734/1/', '647.00', 404],
            'pedido, seguimiento' => [self::POS, '/4821937065182734/2/', 'MX123456789', 404],
            'pedido, devolucion' => [self::POS, '/4821937065182734/3/', '647.00', 404],
            'pedido de otra persona' => [self::POS, '/7305918264731590/', 'Calle Uría 5', 404],
        ];
    }

    /** Fail closed: with no OWNER_TOKEN configured, nothing private is served to anyone. */
    #[DataProvider('privadas')]
    public function testPrivatePagesAreHiddenWhenNoOwnerTokenIsConfigured(string $host, string $uri, string $leak, int $conToken): void
    {
        [$codigo, $body] = $this->get($host, $uri);

        $this->assertSame(404, $codigo, "{$uri} must not exist for an anonymous visitor");
        $this->assertStringNotContainsString($leak, $body);
    }

    /** Even the right credentials open nothing when the server has no (usable) token to compare against. */
    public function testCredentialsAreUselessWithoutAConfiguredToken(): void
    {
        $this->assertSame(404, $this->get(self::GENEALOGIA, '/0/', autorizacion: self::basic(self::TOKEN))[0]);
        $this->assertSame(404, $this->get(self::GENEALOGIA, '/0/', autorizacion: self::basic(''))[0]);
    }

    /** A token shorter than AccessPolicy::MIN_TOKEN_LENGTH counts as not set, even when it matches. */
    public function testAShortTokenIsTreatedAsNotConfigured(): void
    {
        [$codigo, $body] = $this->get(self::GENEALOGIA, '/0/', tokenConfigurado: 'corto', autorizacion: self::basic('corto'));

        $this->assertSame(404, $codigo);
        $this->assertStringNotContainsString('ana@example.com', $body);
    }

    /** With a token configured, an anonymous visitor still sees nothing: a 404 that hides it, or a 401 asking to log in. */
    #[DataProvider('privadas')]
    public function testAnonymousVisitorsAreRefusedEvenWhenATokenIsConfigured(string $host, string $uri, string $leak, int $esperado): void
    {
        [$codigo, $body] = $this->getConTokenConfigurado($host, $uri);

        $this->assertSame($esperado, $codigo, $uri);
        $this->assertStringNotContainsString($leak, $body);
    }

    /** A hidden tree must be indistinguishable from an id that does not exist: that is what hiding it means. */
    public function testAPrivateTreeLooksExactlyLikeAMissingOne(): void
    {
        [$codigoOculta, $oculta] = $this->getConTokenConfigurado(self::GENEALOGIA, '/1048294/');
        [$codigoAusente, $ausente] = $this->getConTokenConfigurado(self::GENEALOGIA, '/1048200/');

        $this->assertSame(404, $codigoOculta);
        $this->assertSame($codigoAusente, $codigoOculta);
        $this->assertSame($ausente, $oculta);

        [, $pedidoAjeno] = $this->getConTokenConfigurado(self::POS, '/7305918264731590/');
        [, $pedidoAusente] = $this->getConTokenConfigurado(self::POS, '/4821937065189999/');
        $this->assertSame($pedidoAusente, $pedidoAjeno);
    }

    /** @return array<string, array{?string}> */
    public static function credencialesIncorrectas(): array
    {
        return [
            'clave equivocada' => [self::basic('otra-clave-0123456789abcdef')],
            'prefijo de la clave' => [self::basic(substr(self::TOKEN, 0, -1))],
            'clave con un caracter de mas' => [self::basic(self::TOKEN . 'x')],
            'clave vacia' => [self::basic('')],
            'solo el usuario, sin dos puntos' => ['Basic ' . base64_encode('propietario')],
            'base64 invalido' => ['Basic %%%no-es-base64%%%'],
            'esquema desconocido' => ['Digest ' . self::TOKEN],
            'bearer equivocado' => ['Bearer otra-clave-0123456789abcdef'],
            'el token sin esquema' => [self::TOKEN],
        ];
    }

    #[DataProvider('credencialesIncorrectas')]
    public function testWrongCredentialsAreRejected(string $autorizacion): void
    {
        [$codigo, $body] = $this->getConTokenConfigurado(self::GENEALOGIA, '/0/', $autorizacion);

        $this->assertSame(401, $codigo);
        $this->assertStringNotContainsString('ana@example.com', $body);
    }

    /** @return array<string, array{string, string, string}> [host, uri, text only the owner sees] */
    public static function paraElPropietario(): array
    {
        return [
            'coleccion privada' => [self::GENEALOGIA, '/1048294/', 'Borrador de Luis'],
            'coleccion privada, GEDCOM' => [self::GENEALOGIA, '/1048294/3/', ' INDI'],
            'coleccion publica, editar' => [self::GENEALOGIA, '/1048293/2/', 'Visibilidad'],
            'cuenta' => [self::GENEALOGIA, '/0/', 'ana@example.com'],
            'cuenta, colecciones' => [self::GENEALOGIA, '/0/1/', 'Familia García'],
            'cuenta pos' => [self::POS, '/0/', 'Ana Demo'],
            'cuenta pos, direcciones' => [self::POS, '/0/4/', 'Av. Juárez 100'],
            'pedido' => [self::POS, '/4821937065182734/', '$647.00 MXN'],
            'pedido, seguimiento' => [self::POS, '/4821937065182734/2/', 'MX123456789'],
        ];
    }

    #[DataProvider('paraElPropietario')]
    public function testTheOwnerSeesPrivatePages(string $host, string $uri, string $esperado): void
    {
        [$codigo, $body] = $this->getComoPropietario($host, $uri);

        $this->assertSame(200, $codigo, $uri);
        $this->assertStringContainsString($esperado, $body);
    }

    public function testTheOwnerCanAlsoUseABearerToken(): void
    {
        [$codigo, $body] = $this->getConTokenConfigurado(self::GENEALOGIA, '/1048294/', 'Bearer ' . self::TOKEN);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Borrador de Luis', $body);
    }

    /** Logging in at /0/ opens a session, so the owner can then browse private pages with no header at all. */
    public function testTheOwnerStaysLoggedInThroughTheSession(): void
    {
        [$codigo, , $sid] = $this->get(self::GENEALOGIA, '/0/', tokenConfigurado: self::TOKEN, autorizacion: self::basic(self::TOKEN));
        $this->assertSame(200, $codigo);
        $this->assertNotSame('', $sid);

        $privadas = ['/1048294/' => 'Borrador de Luis', '/1048294/3/' => ' INDI', '/1048293/2/' => 'Visibilidad', '/0/1/' => 'Familia García'];
        foreach ($privadas as $uri => $esperado) {
            [$codigo, $body] = $this->get(self::GENEALOGIA, $uri, sessionId: $sid, tokenConfigurado: self::TOKEN);
            $this->assertSame(200, $codigo, "{$uri} with only the session");
            $this->assertStringContainsString($esperado, $body);
        }

        // The session is the owner's, not the visitor's: another visitor without it still sees nothing.
        $this->assertSame(404, $this->getConTokenConfigurado(self::GENEALOGIA, '/1048294/')[0]);
    }

    public function testThePosOwnerSessionOpensOrdersToo(): void
    {
        [, , $sid] = $this->get(self::POS, '/0/', tokenConfigurado: self::TOKEN, autorizacion: self::basic(self::TOKEN));

        [$codigo, $body] = $this->get(self::POS, '/4821937065182734/', sessionId: $sid, tokenConfigurado: self::TOKEN);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('$647.00 MXN', $body);
    }

    /** The session remembers a token, not a boolean: rotating OWNER_TOKEN must end sessions opened with the old one. */
    public function testRotatingTheTokenEndsOldSessions(): void
    {
        [, , $sid] = $this->get(self::GENEALOGIA, '/0/', tokenConfigurado: self::TOKEN, autorizacion: self::basic(self::TOKEN));
        $this->assertSame(200, $this->get(self::GENEALOGIA, '/1048294/', sessionId: $sid, tokenConfigurado: self::TOKEN)[0]);

        [$codigo, $body] = $this->get(self::GENEALOGIA, '/1048294/', sessionId: $sid, tokenConfigurado: 'otro-token-0123456789-xyz');

        $this->assertSame(404, $codigo);
        $this->assertStringNotContainsString('Borrador de Luis', $body);
    }

    /** Removing OWNER_TOKEN altogether closes everything again, sessions included. */
    public function testRemovingTheTokenEndsOldSessions(): void
    {
        [, , $sid] = $this->get(self::GENEALOGIA, '/0/', tokenConfigurado: self::TOKEN, autorizacion: self::basic(self::TOKEN));

        $this->assertSame(404, $this->get(self::GENEALOGIA, '/1048294/', sessionId: $sid)[0]);
    }

    /** Failed logins must leave no trace in the session that a later request could mistake for the owner. */
    public function testAFailedLoginDoesNotOpenASession(): void
    {
        [$codigo, , $sid] = $this->get(self::GENEALOGIA, '/0/', tokenConfigurado: self::TOKEN, autorizacion: self::basic('mala-clave-0123456789ab'));
        $this->assertSame(401, $codigo);

        $this->assertSame(404, $this->get(self::GENEALOGIA, '/1048294/', sessionId: $sid, tokenConfigurado: self::TOKEN)[0]);
    }

    public function testAMadeUpSessionIdIsNotTheOwner(): void
    {
        $this->assertSame(404, $this->get(self::GENEALOGIA, '/1048294/', sessionId: 'abcdef0123456789abcdef0123456789', tokenConfigurado: self::TOKEN)[0]);
    }

    /** The password may contain colons: only the first one separates the user name. */
    public function testATokenWithColonsWorksThroughBasicAuth(): void
    {
        $token = 'con:dos:puntos-0123456789';

        [$codigo] = $this->get(self::GENEALOGIA, '/0/', tokenConfigurado: $token, autorizacion: 'Basic ' . base64_encode("yo:{$token}"));

        $this->assertSame(200, $codigo);
    }

    public function testPublicPagesNeverNeedCredentials(): void
    {
        foreach (['/1048293/', '/1048293/1/', '/1048293/3/', '/6128473105/', '/mx/jal/'] as $uri) {
            $this->assertSame(200, $this->get(self::GENEALOGIA, $uri)[0], "{$uri} without a token");
            $this->assertSame(200, $this->getConTokenConfigurado(self::GENEALOGIA, $uri)[0], "{$uri} with a token configured");
        }
    }

    public function testSearchDoesNotRevealPrivateColeccionesToVisitors(): void
    {
        [, $anonimo] = $this->get(self::GENEALOGIA, '/b/7/');
        $this->assertStringContainsString('Familia García', $anonimo);
        $this->assertStringNotContainsString('Borrador de Luis', $anonimo);

        [, $buscado] = $this->getConTokenConfigurado(self::GENEALOGIA, '/b/7/borrador/');
        $this->assertStringNotContainsString('Borrador de Luis', $buscado);

        [, $propietario] = $this->getComoPropietario(self::GENEALOGIA, '/b/7/');
        $this->assertStringContainsString('Borrador de Luis', $propietario);
        $this->assertStringContainsString('Familia García', $propietario);
    }

    /** The account pages act on a fixed account (BaseController::DEFAULT_USER_ID). */
    public function testAccountPagesShowTheFixedAccount(): void
    {
        [, $ordenes] = $this->getComoPropietario(self::POS, '/0/2/');
        $this->assertStringContainsString('4821937065182734', $ordenes);
        $this->assertStringNotContainsString('7305918264731590', $ordenes);

        [, $aportes] = $this->getComoPropietario(self::GENEALOGIA, '/0/2/');
        $this->assertStringContainsString('José García Álvarez', $aportes);
        $this->assertStringNotContainsString('Carlos García Martínez', $aportes);
    }

    /** The owner is not a special case for ids that don't exist: still a plain 404. */
    public function testTheOwnerStillGets404ForMissingOrders(): void
    {
        $this->assertSame(404, $this->getComoPropietario(self::POS, '/4821937065189999/')[0]);
        $this->assertSame(404, $this->getComoPropietario(self::GENEALOGIA, '/1048200/')[0]);
    }

    public function testCartStartsEmpty(): void
    {
        [$codigo, $body] = $this->get(self::POS, '/c/');

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('El carrito está vacío.', $body);
    }

    public function testAddingToCartShowsItInTheCart(): void
    {
        [$sid, $token] = $this->primeSession();

        [$codigo] = $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '2'], $sid, $token);
        $this->assertSame(303, $codigo); // redirects to /c/ (not observable here: see Support/request.php)

        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('Camiseta básica', $body);
        $this->assertStringContainsString('CAM-AZ-M', $body);
        $this->assertStringContainsString('$398.00 MXN', $body); // 2 x $199.00, subtotal and total both
    }

    public function testAddingTwiceAccumulatesButClampsToStock(): void
    {
        [$sid, $token] = $this->primeSession();

        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '2'], $sid, $token);
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '3'], $sid, $token);
        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="5"/', $body);

        // CAM-AZ-M has 30 in stock (see seed data); asking for far more clamps instead of erroring.
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '999'], $sid, $token);
        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="30"/', $body);
    }

    public function testActualizarChangesQuantityAndQuitarRemovesTheLine(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        [$codigo] = $this->post(self::POS, '/c/2/', ['sku' => 'CAM-AZ-M', 'cantidad' => '4'], $sid, $token);
        $this->assertSame(303, $codigo);
        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('$796.00 MXN', $body); // 4 x $199.00

        $this->post(self::POS, '/c/3/', ['sku' => 'CAM-AZ-M'], $sid, $token);
        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('El carrito está vacío.', $body);
    }

    public function testVaciarEmptiesTheWholeCart(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);
        $this->post(self::POS, '/c/1/', ['sku' => 'GOR-AZ', 'cantidad' => '1'], $sid, $token);

        $this->post(self::POS, '/c/4/', [], $sid, $token);

        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('El carrito está vacío.', $body);
    }

    public function testAgregarRejectsAnUnknownOrSoldOutSku(): void
    {
        [$sid, $token] = $this->primeSession();

        $this->assertSame(400, $this->post(self::POS, '/c/1/', ['sku' => 'NO-EXISTE', 'cantidad' => '1'], $sid, $token)[0]);
        // CAM-BL-L has 0 in stock.
        $this->assertSame(400, $this->post(self::POS, '/c/1/', ['sku' => 'CAM-BL-L', 'cantidad' => '1'], $sid, $token)[0]);
    }

    public function testCartMutationsRequireAValidCsrfTokenAndPost(): void
    {
        // No token at all.
        $sinToken = http_build_query(['sku' => 'CAM-AZ-M', 'cantidad' => '1']);
        $this->assertSame(403, $this->get(self::POS, '/c/1/', 'POST', $sinToken)[0]);

        [$sid, $token] = $this->primeSession();

        // Wrong token, valid session and method.
        $this->assertSame(403, $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, 'no-es-el-token')[0]);

        // GET instead of POST, even with a valid token: the URL exists, but only for POST.
        $conToken = http_build_query(['sku' => 'CAM-AZ-M', 'cantidad' => '1', 'csrf_token' => $token]);
        $this->assertSame(405, $this->get(self::POS, '/c/1/', 'GET', $conToken, $sid)[0]);
        foreach (['/c/2/', '/c/3/', '/c/4/'] as $uri) {
            $this->assertSame(405, $this->get(self::POS, $uri)[0], $uri);
        }
        $this->assertSame(405, $this->get(self::GENEALOGIA, '/6128473101/5/', 'PUT')[0], 'the viva page takes GET and POST');
    }

    /** Runs $work with the POS database changed by $sql, then undoes it with $restore. */
    private function conCambioEnPos(string $sql, string $restore, callable $work): void
    {
        $db = new Database('sqlite:' . self::$dir . '/pos.sqlite');
        $db->execute($sql);
        try {
            $work();
        } finally {
            $db->execute($restore);
        }
    }

    public function testCartTotalsEachCurrencySeparately(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);
        $this->post(self::POS, '/c/1/', ['sku' => 'GOR-AZ', 'cantidad' => '1'], $sid, $token);

        $this->conCambioEnPos(
            "UPDATE productos SET moneda = 'USD' WHERE id = 81372049",
            "UPDATE productos SET moneda = 'MXN' WHERE id = 81372049",
            function () use ($sid): void {
                [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
                $this->assertStringContainsString('Total MXN', $body);
                $this->assertStringContainsString('Total USD', $body);
                $this->assertStringContainsString('$249.00 USD', $body);
                $this->assertStringNotContainsString('$448.00', $body, '199 MXN + 249 USD must not be added together');
            }
        );
    }

    public function testDeactivatedProductLeavesTheCart(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        $this->conCambioEnPos(
            'UPDATE productos SET activo = 0 WHERE id = 81372047',
            'UPDATE productos SET activo = 1 WHERE id = 81372047',
            function () use ($sid, $token): void {
                $this->assertSame(303, $this->post(self::POS, '/c/2/', ['sku' => 'CAM-AZ-M', 'cantidad' => '3'], $sid, $token)[0]);
                [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
                $this->assertStringContainsString('El carrito está vacío.', $body);
            }
        );
    }

    public function testCartClampsToStockThatDroppedAfterAdding(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '5'], $sid, $token);

        $this->conCambioEnPos(
            "UPDATE variantes SET stock = 2 WHERE sku = 'CAM-AZ-M'",
            "UPDATE variantes SET stock = 30 WHERE sku = 'CAM-AZ-M'",
            function () use ($sid): void {
                [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
                $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="2"/', $body);
                $this->assertStringContainsString('$398.00 MXN', $body); // 2 x $199.00, not 5
            }
        );
    }

    public function testArrayInputsAreRejectedNotErrors(): void
    {
        [$sid, $token] = $this->primeSession();

        // get() also fails on any PHP warning, e.g. "Array to string conversion".
        $tokenArray = http_build_query(['sku' => 'CAM-AZ-M', 'csrf_token' => [$token]]);
        $this->assertSame(403, $this->get(self::POS, '/c/1/', 'POST', $tokenArray, $sid)[0]);
        $this->assertSame(400, $this->post(self::POS, '/c/1/', ['sku' => ['CAM-AZ-M']], $sid, $token)[0]);
    }

    public function testCheckoutRedirectsWithAFlashWhenTheCartIsEmpty(): void
    {
        [$sid] = $this->primeSession();

        [$codigo] = $this->get(self::POS, '/p/', 'GET', '', $sid);
        $this->assertSame(303, $codigo);

        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('Tu carrito está vacío.', $body);
    }

    public function testCheckoutRedirectsWithAFlashWhenTheCartMixesCurrencies(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);
        $this->post(self::POS, '/c/1/', ['sku' => 'GOR-AZ', 'cantidad' => '1'], $sid, $token);

        $this->conCambioEnPos(
            "UPDATE productos SET moneda = 'USD' WHERE id = 81372049",
            "UPDATE productos SET moneda = 'MXN' WHERE id = 81372049",
            function () use ($sid): void {
                [$codigo] = $this->get(self::POS, '/p/', 'GET', '', $sid);
                $this->assertSame(303, $codigo);

                [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
                $this->assertStringContainsString('distintas monedas', $body);
            }
        );
    }

    public function testCheckoutRendersWhenTheCartHasItemsInOneCurrency(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        [$codigo] = $this->get(self::POS, '/p/', 'GET', '', $sid);
        $this->assertSame(200, $codigo);
    }

    public function testAgregarFlashesAMessageWhenTheQuantityIsClampedToStock(): void
    {
        [$sid, $token] = $this->primeSession();

        // CAM-AZ-M has 30 in stock (see seed data); asking for more clamps and flashes.
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '999'], $sid, $token);

        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('no hay suficiente stock', $body);
    }

    public function testActualizarRejectsANonNumericQuantityInsteadOfSilentlyRemovingTheLine(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/c/1/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        $this->assertSame(400, $this->post(self::POS, '/c/2/', ['sku' => 'CAM-AZ-M', 'cantidad' => 'abc'], $sid, $token)[0]);

        [, $body] = $this->get(self::POS, '/c/', 'GET', '', $sid);
        $this->assertStringContainsString('CAM-AZ-M', $body); // line was not touched
    }

    public function testExportIsValidGedcom(): void
    {
        [$codigo, $gedcom] = $this->getComoPropietario(self::GENEALOGIA, '/1048293/3/');

        $this->assertSame(200, $codigo);
        $this->assertStringStartsWith("0 HEAD\r\n", $gedcom);
        $this->assertStringEndsWith("0 TRLR\r\n", $gedcom);
        $this->assertSame(9, substr_count($gedcom, ' INDI'));
        $this->assertStringContainsString("1 NAME Juan /García Hernández/", $gedcom);
        $this->assertStringContainsString("2 DATE 14 FEB 1925", $gedcom);

        // Every pointer used must be defined in the file.
        preg_match_all('/^\d (?:FAMS|FAMC|HUSB|WIFE|CHIL) (@\w+@)/m', $gedcom, $usados);
        preg_match_all('/^0 (@\w+@) /m', $gedcom, $definidos);
        $this->assertSame([], array_values(array_diff(array_unique($usados[1]), $definidos[1])));
    }

    // --- people who may be alive -------------------------------------------------------------------
    // The demo seed has Carlos (1952) and Lucía (1955), presumed alive until 2062: these tests stick to
    // them so they don't depend on the day they run. Juan, Rosa and Elena cross the 110-year line in
    // 2035-2039 and are covered, with a fixed date, by PrivacidadRepositoriesTest.

    private const CARLOS = '/6128473108/';

    /** What must not reach a visitor: every way the demo has of saying who Carlos and Lucía are. */
    private const DATOS_DE_VIVAS = ['Carlos', 'Lucía', 'García Martínez', '22 mar 1952', '9 ago 1955', '1952–', '1955–'];

    public function testALivingPersonaPageNamesNobodyAndListsNothing(): void
    {
        foreach (['', '1/', '2/', '3/', '4/'] as $accion) {
            [$codigo, $body] = $this->get(self::GENEALOGIA, self::CARLOS . $accion);

            $this->assertSame(200, $codigo, "persona {$accion}");
            $this->assertStringContainsString('Persona viva', $body);
            $this->assertStringContainsString('podrían estar vivas', $body);
            foreach (self::DATOS_DE_VIVAS as $dato) {
                $this->assertStringNotContainsString($dato, $body, "{$dato} on persona {$accion}");
            }
            // Nor who his parents are: that would say who he is.
            $this->assertStringNotContainsString('Antonio', $body);
            $this->assertStringNotContainsString('Guadalajara', $body);
        }
    }

    public function testADeceasedPersonaStillShowsEverythingButItsLivingRelatives(): void
    {
        [, $body] = $this->get(self::GENEALOGIA, '/6128473101/2/');

        $this->assertStringContainsString('Antonio García Fernández', $body);
        $this->assertStringContainsString('Persona viva', $body, 'living descendants stay in the tree, unnamed');
        foreach (self::DATOS_DE_VIVAS as $dato) {
            $this->assertStringNotContainsString($dato, $body, $dato);
        }

        [, $ficha] = $this->get(self::GENEALOGIA, '/6128473103/');
        $this->assertStringContainsString('1898', $ficha);
        $this->assertStringContainsString('Oviedo', $ficha);
    }

    public function testTheSearchNeverFindsLivingPeople(): void
    {
        foreach (['carlos', 'lucía', 'garcía martínez', 'persona viva'] as $q) {
            [, $body] = $this->get(self::GENEALOGIA, self::buscar(10, $q));

            $this->assertStringContainsString('Sin resultados', $body, $q);
            $this->assertStringNotContainsString('Persona viva ', $body, $q);
        }

        [, $todos] = $this->get(self::GENEALOGIA, '/b/10/');
        $this->assertStringContainsString('José García Álvarez', $todos);
        $this->assertStringNotContainsString('Carlos', $todos);
    }

    public function testTheGedcomOfAPublicTreeCarriesNothingAboutLivingPeople(): void
    {
        [$codigo, $gedcom] = $this->get(self::GENEALOGIA, '/1048293/3/');

        $this->assertSame(200, $codigo);
        $this->assertSame(9, substr_count($gedcom, ' INDI'), 'the shape of the tree is kept');
        $this->assertStringContainsString('1 NAME José /García Álvarez/', $gedcom);
        foreach (self::DATOS_DE_VIVAS as $dato) {
            $this->assertStringNotContainsString($dato, $gedcom, $dato);
        }
        $this->assertStringContainsString('1 NAME Persona viva', $gedcom);
    }

    /** @return list<string> every URL the demo database can produce, for an exhaustive crawl */
    private function urlsDeLaDemo(): array
    {
        $db = new Database('sqlite:' . self::$dir . '/genealogy.sqlite');
        $ids = static fn(string $sql): array => array_column($db->fetchAll($sql), 'id');
        $urls = ['/'];

        foreach ($ids('SELECT id FROM personas') as $id) {
            array_push($urls, "/{$id}/", "/{$id}/1/", "/{$id}/2/", "/{$id}/3/", "/{$id}/4/");
        }
        foreach ($ids('SELECT id FROM sucesos') as $id) {
            $urls[] = "/{$id}/";
        }
        foreach ($ids('SELECT id FROM registros') as $id) {
            array_push($urls, "/{$id}/", "/{$id}/1/");
        }
        foreach ($ids('SELECT id FROM colecciones') as $id) {
            array_push($urls, "/{$id}/", "/{$id}/1/", "/{$id}/2/", "/{$id}/3/");
        }
        foreach ($ids('SELECT id FROM grupos') as $id) {
            array_push($urls, "/{$id}/", "/{$id}/1/", "/{$id}/2/");
        }
        foreach ($ids('SELECT id FROM organizaciones') as $id) {
            array_push($urls, "/{$id}/", "/{$id}/1/", "/{$id}/2/");
        }
        foreach (array_column($db->fetchAll('SELECT ruta FROM lugares'), 'ruta') as $ruta) {
            array_push($urls, "/{$ruta}/", "/{$ruta}/1/", "/{$ruta}/2/");
        }
        foreach ([10, 9, 8, 7, 6, 5] as $tipo) {
            $urls[] = "/b/{$tipo}/";
            foreach (['carlos', 'lucía', 'garcía', 'nacimiento', 'acta', 'guadalajara', 'manuel', 'cano', 'padrino', 'oviedo'] as $q) {
                $urls[] = self::buscar($tipo, $q);
            }
        }

        return $urls;
    }

    /**
     * The safety net: a visitor crawls every page the demo can produce, one by one, and
     * nothing about Carlos or Lucía may show up anywhere: not in a tree, a list, a place,
     * an event, a record, a surname page, the search, or the GEDCOM. A new page or query
     * that forgets Privacidad fails here.
     */
    public function testNoPageShowsAVisitorAnythingAboutLivingPeople(): void
    {
        $urls = $this->urlsDeLaDemo();
        $this->assertGreaterThan(100, count($urls));

        foreach ($urls as $uri) {
            [$codigo, $body] = $this->get(self::GENEALOGIA, $uri);
            // A redirect's empty body would pass without checking anything: every URL here must be canonical.
            $this->assertNotSame(301, $codigo, $uri);
            foreach (array_merge(self::DATOS_DE_VIVAS, self::DATOS_PRIVADOS) as $dato) {
                $this->assertStringNotContainsString($dato, $body, "«{$dato}» on {$uri}");
            }
        }
    }

    // --- the owner's page to say who lives -----------------------------------------------------------------
    // /{persona}/5/ changes personas.viva in the database the whole class shares, so every test that
    // saves puts the flag back (VIVA_ORIGINAL) before it ends: the crawler and the other pages rely on Carlos
    // being presumed alive.

    private const PRIVACIDAD_CARLOS = '/6128473108/5/';

    private function marcaVivaDeCarlos(): mixed
    {
        $db = new Database('sqlite:' . self::$dir . '/genealogy.sqlite');

        return $db->fetchOne('SELECT viva FROM personas WHERE id = 6128473108')['viva'];
    }

    private function restaurarMarcaDeCarlos(): void
    {
        (new Database('sqlite:' . self::$dir . '/genealogy.sqlite'))->execute('UPDATE personas SET viva = NULL WHERE id = 6128473108');
    }

    /** A new database has no user 1: the owner, who has the right token, must be told so instead of seeing a bare 404. */
    public function testTheOwnersAccountAreaSaysWhenTheFixedUserDoesNotExist(): void
    {
        $vacias = [];
        foreach (['genealogy' => 'DB_DSN_GENEALOGY', 'pos' => 'DB_DSN_POS'] as $sitio => $variable) {
            $archivo = self::$dir . "/vacia-{$sitio}.sqlite";
            @unlink($archivo);
            Migrator::forSite(new Database("sqlite:{$archivo}"), $sitio)->migrate();
            $vacias[$variable] = "sqlite:{$archivo}";
        }

        foreach ([self::GENEALOGIA, self::POS] as $host) {
            [$codigo, $body] = $this->get($host, '/0/', tokenConfigurado: self::TOKEN, autorizacion: self::basic(self::TOKEN), entorno: $vacias);

            $this->assertSame(404, $codigo, $host);
            $this->assertStringContainsString('No existe el usuario 1', $body, $host);
            $this->assertStringContainsString('DESPLIEGUE.md', $body, $host);

            // Whoever is not the owner is still asked for the credentials, and learns nothing of the database.
            [$codigo, $body] = $this->get($host, '/0/', tokenConfigurado: self::TOKEN, entorno: $vacias);
            $this->assertSame(401, $codigo, $host);
            $this->assertStringNotContainsString('usuario', $body, $host);
        }
    }

    public function testThePageToMarkWhoLivesIsClosedToVisitors(): void
    {
        $this->assertSame(404, $this->get(self::GENEALOGIA, self::PRIVACIDAD_CARLOS)[0], 'no OWNER_TOKEN: closed to everyone');

        [$codigo, $body] = $this->getConTokenConfigurado(self::GENEALOGIA, self::PRIVACIDAD_CARLOS);
        $this->assertSame(401, $codigo, 'the persona is public, so asking for credentials confirms nothing');
        $this->assertStringNotContainsString('Carlos', $body);

        $this->assertSame(401, $this->getConTokenConfigurado(self::GENEALOGIA, self::PRIVACIDAD_CARLOS, self::basic('otra-clave-cualquiera-0123'))[0]);
    }

    public function testAVisitorsPostCannotChangeTheFlag(): void
    {
        $codigo = $this->get(self::GENEALOGIA, self::PRIVACIDAD_CARLOS, 'POST', 'viva=no', tokenConfigurado: self::TOKEN)[0];

        $this->assertContains($codigo, [401, 403]);
        $this->assertNull($this->marcaVivaDeCarlos());
    }

    public function testTheOwnerSeesTheFormAndTheLinkToIt(): void
    {
        [$codigo, $body] = $this->getComoPropietario(self::GENEALOGIA, self::PRIVACIDAD_CARLOS);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Carlos García Martínez', $body, 'the owner sees the real name');
        $this->assertStringContainsString('Sin marca: se decide por las fechas', $body);
        $this->assertStringContainsString('Solo «Persona viva»', $body, 'what a visitor gets');
        $this->assertMatchesRegularExpression('/name="csrf_token" value="[0-9a-f]{64}"/', $body);
        foreach (['si', 'no', 'deducir'] as $valor) {
            $this->assertStringContainsString('name="viva" value="' . $valor . '"', $body);
        }
        $this->assertStringContainsString('name="viva" value="deducir" checked', $body);

        $this->assertStringContainsString('/6128473108/5/', $this->getComoPropietario(self::GENEALOGIA, self::CARLOS)[1], 'the persona page links to it');
        $this->assertStringNotContainsString('/6128473108/5/', $this->get(self::GENEALOGIA, self::CARLOS)[1], 'a visitor is not shown the link');
    }

    public function testTheOwnerCanMarkAPersonaAsDeceasedAndBackToDeduced(): void
    {
        try {
            $conectar = fn(string $metodo, string $cuerpo, ?string $sid) => $this->get(
                self::GENEALOGIA, self::PRIVACIDAD_CARLOS, $metodo, $cuerpo, $sid, self::TOKEN, self::basic(self::TOKEN)
            );
            [, $pagina, $sid] = $conectar('GET', '', null);
            preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $pagina, $m);
            $token = $m[1];

            // Falleció: the visitor now sees the whole ficha.
            $this->assertSame(303, $conectar('POST', http_build_query(['csrf_token' => $token, 'viva' => 'no']), $sid)[0]);
            $this->assertEquals(0, $this->marcaVivaDeCarlos());
            $this->assertNotNull($this->marcaVivaDeCarlos());
            [$codigo, $visto] = $this->get(self::GENEALOGIA, self::CARLOS);
            $this->assertSame(200, $codigo);
            $this->assertStringContainsString('Carlos', $visto);
            $this->assertStringNotContainsString('podrían estar vivas', $visto);
            $this->assertStringContainsString('Marcada como fallecida', $conectar('GET', '', $sid)[1]);

            // Vive: hidden again, whatever the dates say.
            $this->assertSame(303, $conectar('POST', http_build_query(['csrf_token' => $token, 'viva' => 'si']), $sid)[0]);
            $this->assertEquals(1, $this->marcaVivaDeCarlos());
            $this->assertStringNotContainsString('Carlos', $this->get(self::GENEALOGIA, self::CARLOS)[1]);

            // Deducir: the flag is cleared.
            $this->assertSame(303, $conectar('POST', http_build_query(['csrf_token' => $token, 'viva' => 'deducir']), $sid)[0]);
            $this->assertNull($this->marcaVivaDeCarlos());
        } finally {
            $this->restaurarMarcaDeCarlos();
        }
    }

    public function testSavingNeedsTheCsrfTokenAndAKnownValue(): void
    {
        try {
            $conectar = fn(string $cuerpo, ?string $sid) => $this->get(
                self::GENEALOGIA, self::PRIVACIDAD_CARLOS, 'POST', $cuerpo, $sid, self::TOKEN, self::basic(self::TOKEN)
            );
            [, $pagina, $sid] = $this->getConSesionDePropietario(self::PRIVACIDAD_CARLOS);
            preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $pagina, $m);

            $this->assertSame(403, $conectar('viva=no', $sid)[0], 'no token');
            $this->assertSame(403, $conectar('csrf_token=incorrecto&viva=no', $sid)[0], 'wrong token');
            $this->assertSame(403, $conectar('csrf_token[]=x&viva=no', $sid)[0], 'token sent as an array');
            $this->assertSame(400, $conectar(http_build_query(['csrf_token' => $m[1], 'viva' => 'quizas']), $sid)[0], 'unknown value');
            $this->assertSame(400, $conectar(http_build_query(['csrf_token' => $m[1]]), $sid)[0], 'no value');
            $this->assertSame(400, $conectar('csrf_token=' . $m[1] . '&viva[]=no', $sid)[0], 'value sent as an array');
            $this->assertNull($this->marcaVivaDeCarlos(), 'none of them changed anything');
        } finally {
            $this->restaurarMarcaDeCarlos();
        }
    }

    public function testThePageToMarkWhoLivesAnswers404ForAPersonaThatDoesNotExist(): void
    {
        $this->assertSame(404, $this->getComoPropietario(self::GENEALOGIA, '/6128479999/5/')[0]);
    }

    /** @return array{int, string, string} [status, body, session id] of an owner's GET that starts a session. */
    private function getConSesionDePropietario(string $uri): array
    {
        return $this->get(self::GENEALOGIA, $uri, tokenConfigurado: self::TOKEN, autorizacion: self::basic(self::TOKEN));
    }

    // --- people who are only in private collections ------------------------------------------------------
    // Manuel Ruiz Cano (1870-1944) is dead, and is only in the private collection "Borrador de Luis".

    private const MANUEL = '/6128473110/';

    /** What must not reach a visitor: every way the demo has of saying who Manuel is, or of what he took part in. */
    private const DATOS_PRIVADOS = [
        'Manuel', 'Ruiz Cano', '4 mar 1870', '19 ago 1944', '20 ago 1944', '10 may 1921',
        'despedir a su padrino', 'Acta de defunción de Manuel',
    ];

    public function testAPrivatePersonaPageNamesNobodyAndListsNothing(): void
    {
        foreach (['', '1/', '2/', '3/', '4/'] as $accion) {
            [$codigo, $body] = $this->get(self::GENEALOGIA, self::MANUEL . $accion);

            $this->assertSame(200, $codigo, "persona {$accion}");
            $this->assertStringContainsString('Persona privada', $body);
            $this->assertStringContainsString('colecciones privadas', $body);
            $this->assertStringNotContainsString('Persona viva', $body, 'he is not alive: the page must not say so');
            foreach (self::DATOS_PRIVADOS as $dato) {
                $this->assertStringNotContainsString($dato, $body, "{$dato} on persona {$accion}");
            }
            $this->assertStringNotContainsString('Oviedo', $body);
        }
    }

    public function testTheEventsAndTheRecordOfAPrivatePersonaAreNotFoundByAVisitor(): void
    {
        foreach (['/412000014/', '/412000015/', '/412000016/', '/81372004/', '/81372004/1/'] as $uri) {
            [$codigo, $body] = $this->get(self::GENEALOGIA, $uri);

            $this->assertSame(404, $codigo, $uri);
            $this->assertSame($this->get(self::GENEALOGIA, '/99999999/')[1], $body, "{$uri} must look like a page that does not exist");
        }
    }

    public function testASearchNeverFindsAPrivatePersona(): void
    {
        foreach (['manuel', 'cano', 'persona privada'] as $q) {
            [, $body] = $this->get(self::GENEALOGIA, self::buscar(10, $q));

            $this->assertStringNotContainsString('Manuel', $body, $q);
            $this->assertStringNotContainsString('Persona privada', $body, $q);
        }
    }

    /** His godson's page keeps the link, unnamed: the tree keeps its shape and says nothing about him. */
    public function testAVisiblePersonasPageShowsHimAsAPrivatePersonaOrNotAtAll(): void
    {
        foreach (['', '3/', '4/'] as $accion) {
            [$codigo, $body] = $this->get(self::GENEALOGIA, '/6128473103/' . $accion);

            $this->assertSame(200, $codigo);
            foreach (self::DATOS_PRIVADOS as $dato) {
                $this->assertStringNotContainsString($dato, $body, "{$dato} on Antonio {$accion}");
            }
        }
    }

    public function testTheOwnerSeesAPrivatePersonaWhole(): void
    {
        [$codigo, $body] = $this->getComoPropietario(self::GENEALOGIA, self::MANUEL);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Manuel', $body);
        $this->assertStringContainsString('Ruiz Cano', $body);
        $this->assertStringNotContainsString('Persona privada', $body);

        [$codigo, $body] = $this->getComoPropietario(self::GENEALOGIA, '/412000015/');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('1944', $body);
    }

    /** Guards the crawl above against passing because it saw nothing: the owner's view does show them. */
    public function testTheOwnerCrawlDoesSeeLivingPeople(): void
    {
        $vistas = [];
        foreach (['/6128473108/', '/6128473101/2/', '/1048293/1/', '/b/10/carlos/', '/1048293/3/'] as $uri) {
            [, $body] = $this->getComoPropietario(self::GENEALOGIA, $uri);
            $vistas[$uri] = str_contains($body, 'Carlos');
        }

        $this->assertSame(array_fill_keys(array_keys($vistas), true), $vistas);
    }

    public function testDatabaseErrorsDoNotLeakDetails(): void
    {
        $roto = self::$dir . '/roto.sqlite';
        file_put_contents($roto, 'this is not a database');

        $cmd = [PHP_BINARY, dirname(__DIR__, 2) . '/Support/request.php', self::GENEALOGIA, '/6128473105/'];
        $env = ['DB_DSN_GENEALOGY' => "sqlite:{$roto}", 'LOG_FILE' => self::$dir . '/app.log', 'PATH' => (string)getenv('PATH')];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $body = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertStringEndsWith('STATUS:500', $stderr);
        $this->assertStringContainsString('Error interno del servidor', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString($roto, $body);
    }
}
