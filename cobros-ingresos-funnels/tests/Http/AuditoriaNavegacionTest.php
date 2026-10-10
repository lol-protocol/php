<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;

/**
 * Auditoria se pagina por cursor: el cursor viaja en la URL de los links "Más
 * antiguas" y "Más recientes". Aca se la recorre como un navegador, siguiendo los
 * links que la pantalla misma arma. La logica del cursor la prueba
 * AuditoriaRepositoryTest; esto prueba el cableado (controller, vista y URLs).
 *
 * Las filas de prueba van con fechas dentro de 30 dias para quedar por encima de
 * cualquier otra entrada, y se borran al terminar (el servidor lee la base con su
 * propia conexion, asi que tienen que estar commiteadas).
 */
final class AuditoriaNavegacionTest extends HttpTestCase
{
    private const ENTIDAD = 'prueba_cursor_http';

    protected function setUp(): void
    {
        parent::setUp();
        $db = Database::connection();
        $db->prepare('DELETE FROM auditoria WHERE entidad = :e')->execute([':e' => self::ENTIDAD]);
        $this->alTerminar(static function (): void {
            Database::connection()->prepare('DELETE FROM auditoria WHERE entidad = :e')->execute([':e' => self::ENTIDAD]);
        });

        // Fila n = la n-esima mas nueva de las de prueba: la 60 es la de arriba de todo.
        $insertar = $db->prepare(
            "INSERT INTO auditoria (accion, entidad, entidad_id, detalle, creado_en)
             VALUES ('crear', :e, :n, :detalle, now() + interval '30 days' + :minutos * interval '1 minute')"
        );
        for ($n = 1; $n <= 60; $n++) {
            $insertar->execute([':e' => self::ENTIDAD, ':n' => $n, ':detalle' => "Fila de navegacion {$n}", ':minutos' => $n]);
        }
    }

    /** La query del link con ese texto, lista para pedirla (sin el '?' ni el escapado de HTML). */
    private static function linkDe(string $texto, string $cuerpo): string
    {
        if (preg_match('/<a href="\?([^"]+)">' . preg_quote($texto, '/') . '/u', $cuerpo, $m) !== 1) {
            self::fail("La pantalla no tiene el link «{$texto}».");
        }

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    public function testSePuedeIrHaciaLasMasAntiguasYVolver(): void
    {
        $primera = $this->get('page=auditoria');
        $this->assertStatus(200, $primera);
        self::assertStringContainsString('Fila de navegacion 60', $primera['cuerpo']);
        self::assertStringContainsString('Fila de navegacion 36', $primera['cuerpo'], 'la pagina trae 25 filas: de la 60 a la 36');
        self::assertStringNotContainsString('Fila de navegacion 35', $primera['cuerpo']);
        self::assertStringContainsString('Estás viendo lo más reciente', $primera['cuerpo']);
        self::assertStringNotContainsString('Ir a lo más reciente', $primera['cuerpo']);

        $segunda = $this->get(self::linkDe('Más antiguas', $primera['cuerpo']));
        $this->assertStatus(200, $segunda);
        self::assertStringContainsString('Fila de navegacion 35', $segunda['cuerpo']);
        self::assertStringContainsString('Fila de navegacion 11', $segunda['cuerpo']);
        self::assertStringNotContainsString('Fila de navegacion 36', $segunda['cuerpo'], 'no repite la pagina anterior');
        self::assertStringNotContainsString('Fila de navegacion 10', $segunda['cuerpo']);
        self::assertStringContainsString('Ir a lo más reciente', $segunda['cuerpo']);

        $tercera = $this->get(self::linkDe('Más antiguas', $segunda['cuerpo']));
        $this->assertStatus(200, $tercera);
        self::assertStringContainsString('Fila de navegacion 10', $tercera['cuerpo']);
        self::assertStringContainsString('Fila de navegacion 1<', $tercera['cuerpo']);

        // Volver: de la segunda a la primera, y de la tercera a la segunda.
        $deVueltaALaPrimera = $this->get(self::linkDe('&larr; Más recientes', $segunda['cuerpo']));
        self::assertStringContainsString('Fila de navegacion 60', $deVueltaALaPrimera['cuerpo']);
        self::assertStringContainsString('Fila de navegacion 36', $deVueltaALaPrimera['cuerpo']);

        $deVueltaALaSegunda = $this->get(self::linkDe('&larr; Más recientes', $tercera['cuerpo']));
        self::assertStringContainsString('Fila de navegacion 35', $deVueltaALaSegunda['cuerpo']);
        self::assertStringContainsString('Fila de navegacion 11', $deVueltaALaSegunda['cuerpo']);
        self::assertStringNotContainsString('Fila de navegacion 36', $deVueltaALaSegunda['cuerpo']);

        // Y de cualquier pagina, al principio de todo.
        $alPrincipio = $this->get(self::linkDe('Ir a lo más reciente', $tercera['cuerpo']));
        self::assertStringContainsString('Fila de navegacion 60', $alPrincipio['cuerpo']);
        self::assertStringContainsString('Estás viendo lo más reciente', $alPrincipio['cuerpo']);
    }

    /**
     * Regresion del tipo "la entidad nueva sale con el nombre crudo de la tabla": la actualizacion de
     * las tasas de cambio se audita como la entidad "monedas", y la pantalla la tiene que traducir.
     */
    public function testLaAuditoriaTraduceLaActualizacionDeTasas(): void
    {
        $detalle = 'Tasas de cambio actualizadas desde prueba-http ' . uniqid();
        $db = Database::connection();
        $db->prepare("INSERT INTO auditoria (accion, entidad, entidad_id, detalle, creado_en) VALUES ('editar', 'monedas', 0, :d, now() + interval '31 days')")->execute([':d' => $detalle]);
        $this->alTerminar(static function () use ($detalle): void {
            Database::connection()->prepare("DELETE FROM auditoria WHERE entidad = 'monedas' AND detalle = :d")->execute([':d' => $detalle]);
        });

        $cuerpo = $this->get('page=auditoria')['cuerpo'];

        self::assertStringContainsString($detalle, $cuerpo);
        self::assertStringContainsString('Editó Tasas de cambio', preg_replace('/\s+/', ' ', $cuerpo) ?? '');
        self::assertStringNotContainsString('Editó monedas', $cuerpo);
    }

    /** Los links de antes de la paginacion por cursor (?pagina=3) y los cursores rotos no dan error: muestran lo mas reciente. */
    public function testUnaUrlViejaOUnCursorRotoMuestranLoMasReciente(): void
    {
        foreach ([
            'page=auditoria&pagina=3',
            'page=auditoria&antes=basura',
            'page=auditoria&despues=2026-13-45%2099:99:99,1',
            'page=auditoria&antes[]=x',
            'page=auditoria&antes=2026-10-07%2000:00:00,99999999999999999999',
        ] as $query) {
            $respuesta = $this->get($query);
            $this->assertStatus(200, $respuesta, $query);
            self::assertStringContainsString('Fila de navegacion 60', $respuesta['cuerpo'], $query);
        }
    }
}
