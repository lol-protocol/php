<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\AccessPolicy;
use App\Support\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccessPolicyTest extends TestCase
{
    private const TOKEN = 'token-de-prueba-0123456789';

    protected function setUp(): void
    {
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
    }

    private function limpiar(): void
    {
        putenv('OWNER_TOKEN');
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_PW']);
        http_response_code(200);
    }

    private static function basic(string $usuario, string $clave): string
    {
        return 'Basic ' . base64_encode("{$usuario}:{$clave}");
    }

    /** @return array<string, array{?string, bool}> */
    public static function tokens(): array
    {
        return [
            'sin token' => [null, false],
            'vacío' => ['', false],
            'uno menos del mínimo' => [str_repeat('a', AccessPolicy::MIN_TOKEN_LENGTH - 1), false],
            'justo el mínimo' => [str_repeat('a', AccessPolicy::MIN_TOKEN_LENGTH), true],
            'largo' => [bin2hex(random_bytes(32)), true],
        ];
    }

    #[DataProvider('tokens')]
    public function testOnlyALongEnoughTokenIsUsable(?string $token, bool $esperado): void
    {
        $this->assertSame($esperado, AccessPolicy::tokenValido($token));
    }

    /** @return array<string, array{?string, ?string}> */
    public static function credenciales(): array
    {
        return [
            'basic con usuario' => [self::basic('ana', self::TOKEN), self::TOKEN],
            'basic sin usuario' => [self::basic('', self::TOKEN), self::TOKEN],
            'la clave puede tener dos puntos' => [self::basic('ana', 'a:b:c'), 'a:b:c'],
            'basic con clave vacía' => [self::basic('ana', ''), ''],
            'el esquema no distingue mayúsculas' => ['bAsIc ' . base64_encode('x:' . self::TOKEN), self::TOKEN],
            'bearer' => ['Bearer ' . self::TOKEN, self::TOKEN],
            'bearer en minúsculas' => ['bearer ' . self::TOKEN, self::TOKEN],
            'sin cabecera' => [null, null],
            'cabecera vacía' => ['', null],
            'basic sin dos puntos' => ['Basic ' . base64_encode('sinclave'), null],
            'basic con base64 inválido' => ['Basic %%%', null],
            'basic sin credencial' => ['Basic ', null],
            'bearer sin token' => ['Bearer ', null],
            'esquema desconocido' => ['Digest ' . self::TOKEN, null],
            'sin esquema' => [self::TOKEN, null],
        ];
    }

    #[DataProvider('credenciales')]
    public function testCredentialIsReadFromBasicOrBearer(?string $cabecera, ?string $esperada): void
    {
        $this->assertSame($esperada, AccessPolicy::credencial($cabecera));
    }

    public function testAuthorizesTheExactTokenOnly(): void
    {
        $this->assertTrue(AccessPolicy::autoriza(self::TOKEN, self::basic('ana', self::TOKEN)));
        $this->assertTrue(AccessPolicy::autoriza(self::TOKEN, 'Bearer ' . self::TOKEN));

        $this->assertFalse(AccessPolicy::autoriza(self::TOKEN, self::basic('ana', self::TOKEN . 'x')));
        $this->assertFalse(AccessPolicy::autoriza(self::TOKEN, self::basic('ana', substr(self::TOKEN, 1))));
        $this->assertFalse(AccessPolicy::autoriza(self::TOKEN, strtoupper(self::TOKEN)));
        $this->assertFalse(AccessPolicy::autoriza(self::TOKEN, null));
    }

    /** The user name carries no meaning: guessing it gets an attacker nowhere. */
    public function testTheUserNameIsIgnored(): void
    {
        $this->assertTrue(AccessPolicy::autoriza(self::TOKEN, self::basic('cualquiera', self::TOKEN)));
        $this->assertTrue(AccessPolicy::autoriza(self::TOKEN, self::basic('', self::TOKEN)));
    }

    /** Fail closed: matching credentials mean nothing when the configured token is unusable. */
    public function testNothingAuthorizesWithoutAUsableToken(): void
    {
        $this->assertFalse(AccessPolicy::autoriza(null, self::basic('ana', '')));
        $this->assertFalse(AccessPolicy::autoriza('', self::basic('ana', '')));
        $this->assertFalse(AccessPolicy::autoriza('corto', self::basic('ana', 'corto')));
        $this->assertFalse(AccessPolicy::autoriza('corto', 'Bearer corto'));
    }

    public function testNobodyIsTheOwnerByDefault(): void
    {
        $this->assertFalse(AccessPolicy::configurado());
        $this->assertFalse(AccessPolicy::esPropietario());

        $_SERVER['HTTP_AUTHORIZATION'] = self::basic('ana', '');
        $this->assertFalse(AccessPolicy::esPropietario(), 'an empty password must not match an unset token');
    }

    public function testTheOwnerIsRecognisedFromTheRequest(): void
    {
        putenv('OWNER_TOKEN=' . self::TOKEN);
        $this->assertTrue(AccessPolicy::configurado());
        $this->assertFalse(AccessPolicy::esPropietario(), 'no credentials sent');

        $_SERVER['HTTP_AUTHORIZATION'] = self::basic('ana', self::TOKEN);
        $this->assertTrue(AccessPolicy::esPropietario());

        $_SERVER['HTTP_AUTHORIZATION'] = self::basic('ana', 'otra-clave-0123456789ab');
        $this->assertFalse(AccessPolicy::esPropietario());
    }

    public function testTheHeaderIsAlsoFoundWhereAFastCgiSetupRenamesIt(): void
    {
        putenv('OWNER_TOKEN=' . self::TOKEN);

        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . self::TOKEN;

        $this->assertTrue(AccessPolicy::esPropietario());
    }

    public function testTheParsedBasicPasswordIsAcceptedWhenThereIsNoRawHeader(): void
    {
        putenv('OWNER_TOKEN=' . self::TOKEN);

        $_SERVER['PHP_AUTH_PW'] = self::TOKEN;
        $this->assertTrue(AccessPolicy::esPropietario());

        $_SERVER['PHP_AUTH_PW'] = 'otra-clave-0123456789ab';
        $this->assertFalse(AccessPolicy::esPropietario());
    }

    public function testAWrongRawHeaderIsNotRescuedByTheParsedPassword(): void
    {
        putenv('OWNER_TOKEN=' . self::TOKEN);

        $_SERVER['HTTP_AUTHORIZATION'] = self::basic('ana', 'otra-clave-0123456789ab');
        $_SERVER['PHP_AUTH_PW'] = self::TOKEN;

        $this->assertFalse(AccessPolicy::esPropietario());
    }

    public function testTheTokenIsReadThroughConfigSoDotEnvWorksToo(): void
    {
        $envFile = sys_get_temp_dir() . '/access-policy-' . getmypid() . '.env';
        file_put_contents($envFile, 'OWNER_TOKEN="' . self::TOKEN . "\"\n");
        try {
            Config::load([], $envFile);
            $this->assertTrue(AccessPolicy::configurado());
        } finally {
            @unlink($envFile);
        }
    }

    public function testHidingLooksLikeAMissingPage(): void
    {
        $cuerpo = AccessPolicy::ocultar();

        $this->assertSame(404, http_response_code());
        $this->assertSame(AccessPolicy::CUERPO_404, $cuerpo);
    }

    public function testAskingForCredentialsWithATokenIsA401(): void
    {
        putenv('OWNER_TOKEN=' . self::TOKEN);

        $cuerpo = AccessPolicy::pedirCredenciales();

        $this->assertSame(401, http_response_code());
        $this->assertStringContainsString('401', $cuerpo);
        $this->assertStringNotContainsString(self::TOKEN, $cuerpo);
    }

    /** Nobody could answer a challenge without a token, so it must not be offered: it is a plain 404. */
    public function testAskingForCredentialsWithoutATokenIsTheSame404AsHiding(): void
    {
        $cuerpo = AccessPolicy::pedirCredenciales();

        $this->assertSame(404, http_response_code());
        $this->assertSame(AccessPolicy::CUERPO_404, $cuerpo);
    }

    /** The 404 of a hidden page is the one BaseController sends for a missing one, byte for byte. */
    public function testTheHiddenBodyMatchesTheControllersNotFound(): void
    {
        $controlador = new class extends \App\Controllers\BaseController {
            public function noEncontrado(): string
            {
                return $this->handleNotFound();
            }
        };

        $this->assertSame($controlador->noEncontrado(), AccessPolicy::CUERPO_404);
    }

    /** @return array{marca: string, inicio: int, ultima: int} */
    private static function recuerdo(int $inicio, int $ultima, ?string $token = self::TOKEN): array
    {
        return ['marca' => AccessPolicy::marcaDeSesion((string)$token), 'inicio' => $inicio, 'ultima' => $ultima];
    }

    public function testASessionOfTheOwnerIsValidWhileActive(): void
    {
        $ahora = 1_000_000;

        $this->assertTrue(AccessPolicy::sesionValida(['propietario' => self::recuerdo($ahora - 60, $ahora - 10)], self::TOKEN, $ahora));
    }

    public function testASessionExpiresAfterTooLongWithoutRequests(): void
    {
        $ahora = 1_000_000;
        $inicio = $ahora - AccessPolicy::INACTIVIDAD_MAXIMA - 100;

        $this->assertTrue(AccessPolicy::sesionValida(['propietario' => self::recuerdo($inicio, $ahora - AccessPolicy::INACTIVIDAD_MAXIMA)], self::TOKEN, $ahora));
        $this->assertFalse(AccessPolicy::sesionValida(['propietario' => self::recuerdo($inicio, $ahora - AccessPolicy::INACTIVIDAD_MAXIMA - 1)], self::TOKEN, $ahora));
    }

    public function testASessionExpiresAfterTheMaximumDurationEvenIfActive(): void
    {
        $ahora = 1_000_000;

        $this->assertFalse(AccessPolicy::sesionValida(['propietario' => self::recuerdo($ahora - AccessPolicy::DURACION_MAXIMA - 1, $ahora - 5)], self::TOKEN, $ahora));
    }

    public function testASessionOpenedWithAnotherTokenIsNotValid(): void
    {
        $ahora = 1_000_000;

        $this->assertFalse(AccessPolicy::sesionValida(
            ['propietario' => self::recuerdo($ahora - 60, $ahora - 10, 'token-anterior-0123456789')],
            self::TOKEN,
            $ahora
        ));
    }

    /** @return array<string, array{array}> */
    public static function sesionesMalformadas(): array
    {
        $bien = ['marca' => 'x', 'inicio' => 1, 'ultima' => 1];

        return [
            'vacía' => [[]],
            'sin la clave' => [['otra' => $bien]],
            'no es un arreglo' => [['propietario' => true]],
            'sin marca' => [['propietario' => ['inicio' => 1, 'ultima' => 1]]],
            'marca que no es texto' => [['propietario' => ['marca' => 1, 'inicio' => 1, 'ultima' => 1]]],
            'fechas como texto' => [['propietario' => ['marca' => 'x', 'inicio' => '1', 'ultima' => '1']]],
        ];
    }

    #[DataProvider('sesionesMalformadas')]
    public function testMalformedSessionsAreNeverTheOwner(array $sesion): void
    {
        $this->assertFalse(AccessPolicy::sesionValida($sesion, self::TOKEN, 1));
    }

    public function testASessionIsUselessWithoutAUsableToken(): void
    {
        $ahora = 1_000_000;

        $this->assertFalse(AccessPolicy::sesionValida(['propietario' => self::recuerdo($ahora, $ahora, 'corto')], 'corto', $ahora));
        $this->assertFalse(AccessPolicy::sesionValida(['propietario' => self::recuerdo($ahora, $ahora)], null, $ahora));
    }

    public function testTheOwnerIsRecognisedFromAValidSessionToo(): void
    {
        putenv('OWNER_TOKEN=' . self::TOKEN);
        $_SESSION = ['propietario' => self::recuerdo(time() - 60, time() - 10)];

        try {
            $this->assertTrue(AccessPolicy::esPropietario());
        } finally {
            $_SESSION = [];
        }
    }
}
