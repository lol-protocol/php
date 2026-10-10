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
        ?string $autorizacion = null
    ): array {
        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/request.php',
            $host, $uri, $method, $postBody, $sessionId ?? '', $autorizacion ?? ''];

        $env = [
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
            'listado personas' => [self::GENEALOGIA, '/?t=10&q=mart%C3%ADnez', 200, ['Carlos García Martínez', 'Lucía García Martínez']],
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
            'cuenta pos, ordenes' => [self::POS, '/0/2/', '8137204719000', 401],
            'cuenta pos, deseos' => [self::POS, '/0/3/', 'Ana Demo', 401],
            'cuenta pos, direcciones' => [self::POS, '/0/4/', 'Av. Juárez 100', 401],
            'cuenta pos, preferencias' => [self::POS, '/0/5/', 'Ana Demo', 401],
            'pedido' => [self::POS, '/order/8137204719000/', '647.00', 404],
            'pedido, factura' => [self::POS, '/order/8137204719000/1/', '647.00', 404],
            'pedido, seguimiento' => [self::POS, '/order/8137204719000/2/', 'MX123456789', 404],
            'pedido, devolucion' => [self::POS, '/order/8137204719000/3/', '647.00', 404],
            'pedido de otra persona' => [self::POS, '/order/8137204719001/', 'Calle Uría 5', 404],
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

        [, $pedidoAjeno] = $this->getConTokenConfigurado(self::POS, '/order/8137204719001/');
        [, $pedidoAusente] = $this->getConTokenConfigurado(self::POS, '/order/8137204719999/');
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
            'pedido' => [self::POS, '/order/8137204719000/', '$647.00 MXN'],
            'pedido, seguimiento' => [self::POS, '/order/8137204719000/2/', 'MX123456789'],
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

        [$codigo, $body] = $this->get(self::POS, '/order/8137204719000/', sessionId: $sid, tokenConfigurado: self::TOKEN);

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
        [, $anonimo] = $this->get(self::GENEALOGIA, '/?t=7');
        $this->assertStringContainsString('Familia García', $anonimo);
        $this->assertStringNotContainsString('Borrador de Luis', $anonimo);

        [, $buscado] = $this->getConTokenConfigurado(self::GENEALOGIA, '/?t=7&q=borrador');
        $this->assertStringNotContainsString('Borrador de Luis', $buscado);

        [, $propietario] = $this->getComoPropietario(self::GENEALOGIA, '/?t=7');
        $this->assertStringContainsString('Borrador de Luis', $propietario);
        $this->assertStringContainsString('Familia García', $propietario);
    }

    /** The account pages act on a fixed account (BaseController::DEFAULT_USER_ID). */
    public function testAccountPagesShowTheFixedAccount(): void
    {
        [, $ordenes] = $this->getComoPropietario(self::POS, '/0/2/');
        $this->assertStringContainsString('8137204719000', $ordenes);
        $this->assertStringNotContainsString('8137204719001', $ordenes);

        [, $aportes] = $this->getComoPropietario(self::GENEALOGIA, '/0/2/');
        $this->assertStringContainsString('José García Álvarez', $aportes);
        $this->assertStringNotContainsString('Carlos García Martínez', $aportes);
    }

    /** The owner is not a special case for ids that don't exist: still a plain 404. */
    public function testTheOwnerStillGets404ForMissingOrders(): void
    {
        $this->assertSame(404, $this->getComoPropietario(self::POS, '/order/8137204719999/')[0]);
        $this->assertSame(404, $this->getComoPropietario(self::GENEALOGIA, '/1048200/')[0]);
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
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'GOR-AZ', 'cantidad' => '1'], $sid, $token);

        $this->conCambioEnPos(
            "UPDATE productos SET moneda = 'USD' WHERE id = 81372049",
            "UPDATE productos SET moneda = 'MXN' WHERE id = 81372049",
            function () use ($sid): void {
                [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
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
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '1'], $sid, $token);

        $this->conCambioEnPos(
            'UPDATE productos SET activo = 0 WHERE id = 81372047',
            'UPDATE productos SET activo = 1 WHERE id = 81372047',
            function () use ($sid, $token): void {
                $this->assertSame(303, $this->post(self::POS, '/cart/actualizar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '3'], $sid, $token)[0]);
                [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
                $this->assertStringContainsString('El carrito está vacío.', $body);
            }
        );
    }

    public function testCartClampsToStockThatDroppedAfterAdding(): void
    {
        [$sid, $token] = $this->primeSession();
        $this->post(self::POS, '/cart/agregar/', ['sku' => 'CAM-AZ-M', 'cantidad' => '5'], $sid, $token);

        $this->conCambioEnPos(
            "UPDATE variantes SET stock = 2 WHERE sku = 'CAM-AZ-M'",
            "UPDATE variantes SET stock = 30 WHERE sku = 'CAM-AZ-M'",
            function () use ($sid): void {
                [, $body] = $this->get(self::POS, '/cart/', 'GET', '', $sid);
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
        $this->assertSame(403, $this->get(self::POS, '/cart/agregar/', 'POST', $tokenArray, $sid)[0]);
        $this->assertSame(400, $this->post(self::POS, '/cart/agregar/', ['sku' => ['CAM-AZ-M']], $sid, $token)[0]);
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
            [, $body] = $this->get(self::GENEALOGIA, '/?t=10&q=' . rawurlencode($q));

            $this->assertStringContainsString('Sin resultados', $body, $q);
            $this->assertStringNotContainsString('Persona viva ', $body, $q);
        }

        [, $todos] = $this->get(self::GENEALOGIA, '/?t=10');
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
            $urls[] = "/?t={$tipo}";
            foreach (['carlos', 'lucía', 'garcía', 'nacimiento', 'acta', 'guadalajara'] as $q) {
                $urls[] = "/?t={$tipo}&q=" . rawurlencode($q);
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
            [, $body] = $this->get(self::GENEALOGIA, $uri);
            foreach (self::DATOS_DE_VIVAS as $dato) {
                $this->assertStringNotContainsString($dato, $body, "«{$dato}» on {$uri}");
            }
        }
    }

    /** Guards the crawl above against passing because it saw nothing: the owner's view does show them. */
    public function testTheOwnerCrawlDoesSeeLivingPeople(): void
    {
        $vistas = [];
        foreach (['/6128473108/', '/6128473101/2/', '/1048293/1/', '/?t=10&q=carlos', '/1048293/3/'] as $uri) {
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
