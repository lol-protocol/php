<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PDO;

/**
 * database/actualizar_tasas.php, el comando que corre cron: los argumentos, lo que
 * imprime y con que codigo termina. Se lo ejecuta de verdad en otro proceso con las
 * mismas variables de entorno que los tests. Solo se prueba lo que no escribe en la
 * base (--simular y los errores): que guarda lo cubre ActualizadorDeTasasTest, y un
 * test que escribe de verdad en la base compartida dejaria las tasas cambiadas.
 */
final class ActualizarTasasCliTest extends IntegracionTestCase
{
    /** @var list<string> */
    private array $archivos = [];

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
        parent::tearDown();
    }

    /**
     * @param list<string> $argumentos
     * @param array<string, string> $entorno variables que se suman a las del proceso actual
     * @return array{codigo: int, salida: string, errores: string}
     */
    private function ejecutar(array $argumentos, array $entorno = []): array
    {
        $raiz = dirname(__DIR__, 2);
        $proceso = proc_open(
            [PHP_BINARY, $raiz . '/database/actualizar_tasas.php', ...$argumentos],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            $raiz,
            array_merge(getenv(), $entorno)
        );
        self::assertIsResource($proceso);
        $salida = (string) stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        return ['codigo' => proc_close($proceso), 'salida' => $salida, 'errores' => $errores];
    }

    private function archivo(string $nombre, string $contenido): string
    {
        $ruta = sys_get_temp_dir() . '/' . $nombre;
        file_put_contents($ruta, $contenido);
        $this->archivos[] = $ruta;

        return $ruta;
    }

    /**
     * Una fuente con todo el catalogo a las tasas que ya tiene la base (invertidas, que es como las da una fuente), asi
     * que no hay saltos que avisar sea cual sea el estado de la base; los $cambios son codigo => unidades por USD, y
     * null saca la moneda de la respuesta.
     *
     * @param array<string, mixed> $cambios
     */
    private function fuenteCompleta(string $nombre, array $cambios = []): string
    {
        $rates = ['USD' => 1];
        foreach (Database::connection()->query("SELECT codigo, 1 / tasa_a_usd AS unidades FROM monedas WHERE codigo <> 'USD'")->fetchAll(PDO::FETCH_KEY_PAIR) as $codigo => $unidades) {
            $rates[(string) $codigo] = (float) $unidades;
        }
        foreach ($cambios as $codigo => $valor) {
            if ($valor === null) {
                unset($rates[$codigo]);
            } else {
                $rates[$codigo] = $valor;
            }
        }

        return $this->archivo($nombre, (string) json_encode(['base_code' => 'USD', 'time_last_update_unix' => time() - 60, 'rates' => $rates]));
    }

    private function monedasConFecha(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM monedas WHERE tasa_actualizada_en IS NOT NULL')->fetchColumn();
    }

    public function testAyudaExplicaElUsoYTerminaBien(): void
    {
        $resultado = $this->ejecutar(['--ayuda']);

        self::assertSame(0, $resultado['codigo']);
        self::assertStringContainsString('Uso:', $resultado['salida']);
        self::assertStringContainsString('--simular', $resultado['salida']);
        self::assertStringContainsString('--forzar', $resultado['salida']);
        self::assertStringContainsString('--archivo', $resultado['salida']);
    }

    public function testUnArgumentoDesconocidoTerminaConDosYMuestraElUso(): void
    {
        $resultado = $this->ejecutar(['--simulat']);

        self::assertSame(2, $resultado['codigo']);
        self::assertStringContainsString('Argumento desconocido: --simulat', $resultado['errores']);
        self::assertStringContainsString('Uso:', $resultado['errores']);
    }

    public function testUnArchivoQueNoExisteTerminaConUnoYLoDice(): void
    {
        $resultado = $this->ejecutar(['--archivo=/no/existe/tasas.json']);

        self::assertSame(1, $resultado['codigo']);
        self::assertStringContainsString('No se pudo leer el archivo /no/existe/tasas.json', $resultado['errores']);
        self::assertSame('', $resultado['salida']);
    }

    public function testUnArchivoQueNoEsJsonTerminaConUnoYNoGuardaNada(): void
    {
        $antes = $this->monedasConFecha();
        $archivo = $this->archivo('tasas-roto-' . uniqid() . '.json', '<html>502 Bad Gateway</html>');

        $resultado = $this->ejecutar(['--archivo=' . $archivo]);

        self::assertSame(1, $resultado['codigo']);
        self::assertStringContainsString('no es un JSON válido', $resultado['errores']);
        self::assertSame($antes, $this->monedasConFecha());
    }

    public function testUnaUrlQueNoEsHttpsSeRechazaSinSalirALaRed(): void
    {
        $resultado = $this->ejecutar([], ['TASAS_URL' => 'http://ejemplo.com/tasas']);

        self::assertSame(1, $resultado['codigo']);
        self::assertStringContainsString('tiene que ser https', $resultado['errores']);
    }

    public function testSimularDiceQueHariaYNoGuardaNada(): void
    {
        $antes = $this->monedasConFecha();
        $archivo = $this->fuenteCompleta('tasas-ok-' . uniqid() . '.json', ['EUR' => 0.8, 'CLP' => null, 'ARS' => 0]);

        $resultado = $this->ejecutar(['--simular', '--archivo=' . $archivo]);

        self::assertSame(0, $resultado['codigo']);
        self::assertStringContainsString('SIMULACIÓN: no se guardó nada.', $resultado['salida']);
        self::assertMatchesRegularExpression('/Se actualizarían \d+ monedas con las tasas de tasas-ok-/', $resultado['salida']);
        self::assertStringContainsString('Sin dato en la fuente (quedan como estaban): CLP', $resultado['salida']);
        self::assertStringContainsString('Piden atención:', $resultado['errores'], 'lo que pide atencion sale por la salida de errores, la que cron manda por mail');
        self::assertStringContainsString('ARS: la fuente la trae mal (no es mayor que cero)', $resultado['errores']);
        self::assertSame($antes, $this->monedasConFecha(), 'no se guardo nada');
    }

    public function testElNombreDeLaFuenteSePuedeElegir(): void
    {
        $archivo = $this->fuenteCompleta('tasas-nombre-' . uniqid() . '.json');

        $resultado = $this->ejecutar(['--simular', '--archivo=' . $archivo], ['TASAS_FUENTE' => 'Banco Central']);

        self::assertStringContainsString('con las tasas de Banco Central', $resultado['salida']);
    }

    public function testSinNadaQueDecirNoMandaNadaPorLaSalidaDeErrores(): void
    {
        $archivo = $this->fuenteCompleta('tasas-limpio-' . uniqid() . '.json');

        $resultado = $this->ejecutar(['--simular', '--archivo=' . $archivo]);

        self::assertSame(0, $resultado['codigo']);
        self::assertSame('', $resultado['errores'], 'cron no tiene por que mandar un mail si todo salio bien');
    }
}
