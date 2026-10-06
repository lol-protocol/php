<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\EnvioUnico;

/**
 * Corre contra la base configurada por las env vars DB_*. La operacion de
 * prueba escribe una fila de auditoria con un detalle unico, para poder
 * contar cuantas veces se aplico de verdad.
 */
final class EnvioUnicoTest extends IntegracionTestCase
{
    private static function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** @return callable(): string una operacion que escribe una fila marcada con $marca y redirige a $url */
    private static function operacion(string $marca, string $url): callable
    {
        return static function () use ($marca, $url): string {
            Database::connection()
                ->prepare("INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle) VALUES (NULL, 'crear', 'prueba_envio', 1, :marca)")
                ->execute([':marca' => $marca]);

            return $url;
        };
    }

    private static function vecesAplicada(string $marca): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM auditoria WHERE detalle = :marca');
        $stmt->execute([':marca' => $marca]);

        return (int) $stmt->fetchColumn();
    }

    public function testElPrimerEnvioAplicaLaOperacionYRecuerdaASuRedireccion(): void
    {
        $token = self::token();
        $marca = 'envio ' . uniqid();

        self::assertNull(EnvioUnico::redireccionPrevia($token));
        self::assertSame('?page=pagos&creado=1', EnvioUnico::ejecutar($token, self::operacion($marca, '?page=pagos&creado=1')));

        self::assertSame(1, self::vecesAplicada($marca));
        self::assertSame('?page=pagos&creado=1', EnvioUnico::redireccionPrevia($token));
    }

    /**
     * Si el segundo envio llega a ejecutar() -los dos pasaron la consulta
     * previa casi a la vez-, choca contra el token ya registrado: lo que
     * alcanzo a escribir se deshace y recibe la redireccion del primero.
     */
    public function testUnSegundoEnvioConElMismoTokenSeDeshaceYRecibeLaMismaRedireccion(): void
    {
        $token = self::token();
        $marca = 'envio ' . uniqid();

        EnvioUnico::ejecutar($token, self::operacion($marca, '?page=pagos&creado=1'));
        $segunda = EnvioUnico::ejecutar($token, self::operacion($marca, '?page=pagos&creado=2'));

        self::assertSame('?page=pagos&creado=1', $segunda, 'el reenvio va a donde fue el primero');
        self::assertSame(1, self::vecesAplicada($marca), 'la escritura del reenvio se deshizo');
    }

    /**
     * Los dos envios en paralelo: mientras este corre su operacion, otro
     * proceso (otra conexion) commitea el mismo token. El INSERT del token
     * de este choca contra la clave primaria y su transaccion entera se
     * deshace, en vez de dejar un segundo pago.
     */
    public function testSiOtroProcesoCommiteaElMismoTokenPrimeroSeDeshaceLoPropio(): void
    {
        $token = self::token();
        $marca = 'envio ' . uniqid();
        $otroProceso = Database::conectar();

        try {
            $redireccion = EnvioUnico::ejecutar($token, static function () use ($marca, $token, $otroProceso): string {
                self::operacion($marca, '?page=pagos&creado=2')();
                $otroProceso
                    ->prepare("INSERT INTO envios_formulario (token, redireccion) VALUES (:token, '?page=pagos&creado=1')")
                    ->execute([':token' => $token]);

                return '?page=pagos&creado=2';
            });

            self::assertSame('?page=pagos&creado=1', $redireccion, 'gana el que commiteo primero');
            self::assertSame(0, self::vecesAplicada($marca), 'la escritura del que perdio no quedo');
        } finally {
            $otroProceso->prepare('DELETE FROM envios_formulario WHERE token = :token')->execute([':token' => $token]);
        }
    }
}
