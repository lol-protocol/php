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

    /** @return array{int, string, string} [status, body, sessionId actually used] */
    private function get(
        string $host,
        string $uri,
        string $method = 'GET',
        string $postBody = '',
        ?string $sessionId = null
    ): array {
        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/request.php',
            $host, $uri, $method, $postBody, $sessionId ?? ''];

        $env = [
            'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
            'DB_DSN_POS' => 'sqlite:' . self::$dir . '/pos.sqlite',
            'LOG_FILE' => self::$dir . '/app.log',
            'PATH' => (string)getenv('PATH'),
        ];

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

    private function post(string $host, string $uri, array $params, string $sessionId, string $token): array
    {
        $postBody = http_build_query($params + ['csrf_token' => $token]);
        return $this->get($host, $uri, 'POST', $postBody, $sessionId);
    }

    /** Direct access to the shared POS fixture, for tests that need to change catalog state mid-test. */
    private function posDb(): Database
    {
        return new Database('sqlite:' . self::$dir . '/pos.sqlite');
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
            'listado personas' => [self::GENEALOGIA, '/?t=10&q=mart%C3%ADnez', 200, ['Carlos García Martínez', 'Lucía García Martínez']],
            // No login: every collection is reachable regardless of its "publica" flag.
            'coleccion privada' => [self::GENEALOGIA, '/1048294/', 200, ['Borrador de Luis']],
            'editar coleccion' => [self::GENEALOGIA, '/1048293/2/', 200, ['Familia García', 'Visibilidad']],
            'cuenta' => [self::GENEALOGIA, '/0/', 200, ['Ana Demo']],
            'cuenta colecciones' => [self::GENEALOGIA, '/0/1/', 200, ['Familia García']],
            'cuenta pos' => [self::POS, '/0/', 200, ['Ana Demo']],
            'producto' => [self::POS, '/81372047/', 200, ['Camiseta básica', '$199.00 MXN', 'Algodón orgánico', 'Agotado']],
            'producto descontinuado' => [self::POS, '/81372050/', 200, ['descontinuado']],
            'variantes' => [self::POS, '/81372048/1/', 200, ['$749.00 MXN', '$699.00 MXN']],
            'atributos' => [self::POS, '/81372047/2/', 200, ['Color', 'Talla', 'Azul marino']],
            'atributo productos' => [self::POS, '/48215/1/', 200, ['Sudadera con capucha']],
            'coleccion' => [self::POS, '/7300001/', 200, ['Primavera 2026', 'Sudadera con capucha']],
            'grupo' => [self::POS, '/1001/', 200, ['Camisetas', 'Sudaderas', 'Camiseta básica']],
            'listado productos' => [self::POS, '/?t=8', 200, ['Gorra de seis paneles']],
        ];
    }

    #[DataProvider('paginas')]
    public function testPageRendersFromTheDatabase(string $host, string $uri, int $status, array $esperado): void
    {
        [$codigo, $body] = $this->get($host, $uri);

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
            'listado tipo invalido' => [self::GENEALOGIA, '/?t=99', 400],
            'producto inexistente' => [self::POS, '/99999999/', 404],
        ];
    }

    #[DataProvider('errores')]
    public function testErrorStatus(string $host, string $uri, int $status): void
    {
        [$codigo] = $this->get($host, $uri);

        $this->assertSame($status, $codigo);
    }

    public function testSearchTreatsPercentLiterally(): void
    {
        [, $body] = $this->get(self::POS, '/?t=8&q=%25');

        $this->assertStringContainsString('Sin productos disponibles', $body);
    }

    /** No login: any order id is reachable by anyone, not just whoever placed it. */
    public function testAnyOrderIsReachableWithoutASession(): void
    {
        [$codigo, $body] = $this->get(self::POS, '/order/8137204719000/');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('$647.00 MXN', $body);
        $this->assertStringContainsString('MX123456789', $this->get(self::POS, '/order/8137204719000/2/')[1]);

        $this->assertSame(200, $this->get(self::POS, '/order/8137204719001/')[0]);
    }

    /** No login: the account pages act on a fixed account (BaseController::DEFAULT_USER_ID). */
    public function testAccountPagesShowTheFixedAccount(): void
    {
        [, $ordenes] = $this->get(self::POS, '/0/2/');
        $this->assertStringContainsString('8137204719000', $ordenes);
        $this->assertStringNotContainsString('8137204719001', $ordenes);

        [, $aportes] = $this->get(self::GENEALOGIA, '/0/2/');
        $this->assertStringContainsString('José García Álvarez', $aportes);
        $this->assertStringNotContainsString('Carlos García Martínez', $aportes);
    }

    public function testCartStartsEmpty(): void
    {
        [$codigo, $body] = $this->get(self::POS, '/cart/');

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('El carrito está vacío.', $body);
    }

    public function testAddingToCartShowsItInTheCart(): void
    {
        [$sid, $token] = $this->primeSession();

        [$codigo] = $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '2'], $sid, $token);
        $this->assertSame(303, $codigo); // redirects to /cart/ (not observable here: see Support/request.php)

        [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
        $this->assertStringContainsString('Camiseta básica', $body);
        $this->assertStringContainsString('CAM-AZ-M', $body);
        $this->assertStringContainsString('$398.00 MXN', $body); // 2 x $199.00, subtotal and total both
    }

    public function testAddingTwiceAccumulatesButClampsToStock(): void
    {
        [$sid, $token] = $this->primeSession();

        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '2'], $sid, $token);
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '3'], $sid, $token);
        [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
        $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="5"/', $body);

        // CAM-AZ-M has 30 in stock (see seed data); asking for far more clamps instead of erroring.
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '999'], $sid, $token);
        [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
        $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="30"/', $body);
    }

    public function testActualizarChangesQuantityAndQuitarRemovesTheLine(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        [$codigo] = $this->post(self::POS, '/cart/actualizar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '4'], $sid, $token);
        $this->assertSame(303, $codigo);
        [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
        $this->assertStringContainsString('$796.00 MXN', $body); // 4 x $199.00

        $this->post(self::POS, '/cart/quitar/', ['sku' => 'CAM-AZ-M'], $sid, $token);
        [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
        $this->assertStringContainsString('El carrito está vacío.', $body);
    }

    public function testVaciarEmptiesTheWholeCart(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'GOR-AZ', 'cantidad' => '1'], $sid, $token);

        $this->post(self::POS, '/cart/vaciar/', [], $sid, $token);

        [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
        $this->assertStringContainsString('El carrito está vacío.', $body);
    }

    public function testAgregarRejectsAnUnknownOrSoldOutSku(): void
    {
        [$sid, $token] = $this->primeSession();

        $this->assertSame(400, $this->post(self::POS, '/cart/agregar/', ['sku' => 'NO-EXISTE', 'cantidad' => '1'], $sid, $token)[0]);
        // CAM-BL-L has 0 in stock.
        $this->assertSame(400, $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-BL-L', 'cantidad' => '1'], $sid, $token)[0]);
    }

    public function testCartMutationsRequireAValidCsrfTokenAndPost(): void
    {
        // No token at all.
        $sinToken = http_build_query(['sku' => 'CAM-AZ-M', 'cantidad' => '1']);
        $this->assertSame(403, $this->get(self::POS, '/cart/agregar/', 'POST', $sinToken)[0]);

        [$sid, $token] = $this->primeSession();

        // Wrong token, valid session and method.
        $this->assertSame(403, $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, 'no-es-el-token')[0]);

        // GET instead of POST, even with a valid token.
        $conToken = http_build_query(['sku' => 'CAM-AZ-M', 'cantidad' => '1', 'csrf_token' => $token]);
        $this->assertSame(403, $this->get(self::POS, '/cart/agregar/', 'GET', $conToken, $sid)[0]);
    }

    public function testCartMutationsRejectArrayValuedInputsInsteadOfErroring(): void
    {
        [$sid, $token] = $this->primeSession();

        // sku[]=... reaches the controller as an array; must 400, not warn/500.
        $arraySku = 'sku[]=CAM-AZ-M&cantidad=1&csrf_token=' . urlencode($token);
        $this->assertSame(400, $this->get(self::POS, '/cart/agregar/', 'POST', $arraySku, $sid)[0]);

        // csrf_token[]=... must 403 (SessionManager::validateCsrfToken() takes a string), not 500.
        $arrayToken = 'sku=CAM-AZ-M&cantidad=1&csrf_token[]=' . urlencode($token);
        $this->assertSame(403, $this->get(self::POS, '/cart/agregar/', 'POST', $arrayToken, $sid)[0]);
    }

    public function testDeactivatedProductDropsFromTheCartViewAndFromActualizar(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'SUD-AZ-L', 'cantidad' => '1'], $sid, $token);

        $db = $this->posDb();
        $db->update('productos', ['activo' => false], ['id' => 81372048]);
        try {
            [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
            $this->assertStringContainsString('El carrito está vacío.', $body);

            // Re-add is impossible once inactive, so put the line back directly to test actualizar()'s own guard.
            $db->update('productos', ['activo' => true], ['id' => 81372048]);
            $this->post(self::POS, '/cart/agregar/', ['sku' => 'SUD-AZ-L', 'cantidad' => '1'], $sid, $token);
            $db->update('productos', ['activo' => false], ['id' => 81372048]);

            [$codigo] = $this->post(self::POS, '/cart/actualizar/', ['sku' => 'SUD-AZ-L', 'cantidad' => '2'], $sid, $token);
            $this->assertSame(303, $codigo);
            [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
            $this->assertStringContainsString('El carrito está vacío.', $body);
        } finally {
            $db->update('productos', ['activo' => true], ['id' => 81372048]);
        }
    }

    public function testShowReClampsAndPersistsWhenStockDropsBelowCartQuantity(): void
    {
        [$sid, $token] = $this->primeSession();
        // SUD-AZ-L has 3 in stock (see seed data).
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'SUD-AZ-L', 'cantidad' => '3'], $sid, $token);

        $db = $this->posDb();
        $db->update('variantes', ['stock' => 1], ['sku' => 'SUD-AZ-L']);
        try {
            [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
            $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="1"/', $body);

            // The clamp was persisted to the session, not just displayed once.
            [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
            $this->assertMatchesRegularExpression('/name="cantidad"[^>]*value="1"/', $body);
        } finally {
            $db->update('variantes', ['stock' => 3], ['sku' => 'SUD-AZ-L']);
        }
    }

    public function testAgregarRejectsMixingCurrenciesInTheCart(): void
    {
        [$sid, $token] = $this->primeSession();
        $db = $this->posDb();
        $db->insert('productos', ['id' => 99999901, 'nombre' => 'Producto en USD', 'precio_centavos' => 1000, 'moneda' => 'USD', 'activo' => true]);
        $db->insert('variantes', ['sku' => 'TEST-USD', 'producto_id' => 99999901, 'nombre' => 'Única', 'stock' => 10]);

        try {
            $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token); // MXN

            $this->assertSame(400, $this->post(self::POS, '/cart/agregar/', ['sku' => 'TEST-USD', 'cantidad' => '1'], $sid, $token)[0]);
        } finally {
            $db->delete('variantes', ['sku' => 'TEST-USD']);
            $db->delete('productos', ['id' => 99999901]);
        }
    }

    public function testExportIsValidGedcom(): void
    {
        [$codigo, $gedcom] = $this->get(self::GENEALOGIA, '/1048293/3/');

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
