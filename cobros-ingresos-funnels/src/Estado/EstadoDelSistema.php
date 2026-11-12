<?php

declare(strict_types=1);

namespace App\Estado;

use App\Config;
use App\Database;
use App\Migrador;
use App\Repositories\MonedaRepository;
use App\Respaldo\RespaldoDeLaBase;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Lo que alguien que opera el panel quiere saber en una mirada, sin abrir cuatro comandos: si la base responde,
 * si las migraciones estan al dia, si las tasas de cambio son reales y recientes y si hay un respaldo reciente.
 * Es lo que muestra bin/estado.php, y como termina con 1 cuando algo pide atencion, sirve de cron o de
 * monitoreo. Solo lee: no migra, no actualiza tasas ni respalda.
 *
 * Cada comprobacion es una fila (tema, ok, detalle). Si la base no responde se devuelve solo esa fila: sin ella
 * no se puede mirar nada mas, y el resto de las filas sobre una base que no esta serian ruido.
 *
 * No comprueba que el servidor web proteja el panel (eso lo hace bin/comprobar_acceso.php, que necesita la
 * direccion publica y sale a la red), ni que el respaldo se pueda restaurar (lo comprueba respaldar.php al
 * hacerlo), ni copia en otra maquina.
 *
 * @phpstan-type Fila array{tema: string, ok: bool, detalle: string}
 */
final class EstadoDelSistema
{
    /**
     * Cuanto puede tener el ultimo respaldo antes de pedir atencion. El respaldo se hace una vez por dia
     * (cron): 36 horas deja pasar una corrida tardia, pero no un dia entero sin respaldar.
     */
    public const HORAS_MAXIMAS_SIN_RESPALDO = 36;

    public function __construct(
        private readonly Migrador $migrador,
        private readonly RespaldoDeLaBase $respaldos,
    ) {
    }

    /** @return non-empty-list<Fila> siempre trae al menos la fila de la base */
    public function comprobar(?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $base = $this->base();
        if (!$base['ok']) {
            return [$base];
        }

        return [$base, $this->migraciones(), $this->tasas($ahora), $this->respaldo($ahora)];
    }

    /** @param list<Fila> $filas */
    public static function todoEnOrden(array $filas): bool
    {
        foreach ($filas as $fila) {
            if (!$fila['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return Fila */
    private function base(): array
    {
        try {
            $version = Database::connection()->query('SHOW server_version')->fetchColumn();
        } catch (Throwable $e) {
            // El mensaje de PDO trae el servidor y el usuario, pero nunca la clave; en una sola linea, para la columna.
            return ['tema' => 'Base de datos', 'ok' => false, 'detalle' => 'no responde: ' . trim((string) preg_replace('/\s+/', ' ', $e->getMessage()))];
        }

        return ['tema' => 'Base de datos', 'ok' => true, 'detalle' => 'PostgreSQL ' . (string) $version . ', responde'];
    }

    /** @return Fila */
    private function migraciones(): array
    {
        $pendientes = $this->migrador->pendientes();
        if ($pendientes === []) {
            return ['tema' => 'Migraciones', 'ok' => true, 'detalle' => 'al día (' . count($this->migrador->aplicadas()) . ' aplicadas)'];
        }

        return [
            'tema' => 'Migraciones',
            'ok' => false,
            'detalle' => count($pendientes) . ' sin aplicar (' . implode(', ', $pendientes) . '): corra php bin/migrar.php',
        ];
    }

    /** @return Fila */
    private function tasas(DateTimeImmutable $ahora): array
    {
        $estado = MonedaRepository::estadoDeLasTasas();
        if ($estado['ultima'] === null) {
            return [
                'tema' => 'Tasas de cambio',
                'ok' => false,
                'detalle' => 'todas son de ejemplo, no reales: los totales en USD no valen. Corra php bin/actualizar_tasas.php',
            ];
        }

        $cotizada = $estado['ultima']->setTimezone(new DateTimeZone(Config::zonaHoraria()))->format('Y-m-d H:i');
        $problemas = [];
        if ($estado['ultima'] < $ahora->modify('-' . MonedaRepository::DIAS_DE_VIGENCIA . ' days')) {
            $problemas[] = sprintf(
                'la cotización más reciente es del %s (%s): la actualización diaria dejó de correr (php bin/actualizar_tasas.php)',
                $cotizada,
                self::hace($estado['ultima'], $ahora)
            );
        }
        if ($estado['pendientes'] !== []) {
            $problemas[] = 'monedas con movimientos y tasa de ejemplo o de hace más de ' . MonedaRepository::DIAS_DE_VIGENCIA
                . ' días: ' . implode(', ', $estado['pendientes']);
        }
        if ($problemas !== []) {
            return ['tema' => 'Tasas de cambio', 'ok' => false, 'detalle' => implode('; ', $problemas)];
        }

        return [
            'tema' => 'Tasas de cambio',
            'ok' => true,
            'detalle' => 'al día: cotización del ' . $cotizada . ($estado['fuente'] !== null ? ', ' . $estado['fuente'] : ''),
        ];
    }

    /** @return Fila */
    private function respaldo(DateTimeImmutable $ahora): array
    {
        $ultimo = $this->respaldos->masReciente();
        if ($ultimo === null) {
            return ['tema' => 'Respaldos', 'ok' => false, 'detalle' => 'no hay ninguno en la carpeta de respaldos: corra php bin/respaldar.php'];
        }

        $descripcion = sprintf('%s, %s, %s', $ultimo['nombre'], self::hace($ultimo['momento'], $ahora), RespaldoDeLaBase::tamanio($ultimo['bytes']));
        if ($ultimo['momento'] < $ahora->modify('-' . self::HORAS_MAXIMAS_SIN_RESPALDO . ' hours')) {
            return [
                'tema' => 'Respaldos',
                'ok' => false,
                'detalle' => 'el último es de hace más de ' . self::HORAS_MAXIMAS_SIN_RESPALDO . ' horas (' . $descripcion . '): el cron del respaldo no está corriendo',
            ];
        }

        return ['tema' => 'Respaldos', 'ok' => true, 'detalle' => 'el último: ' . $descripcion];
    }

    /** «hace 5 min», «hace 3 h» o «hace 4 días»: lo bastante para ver si algo esta viejo. Una fecha futura es «ahora». */
    public static function hace(DateTimeImmutable $momento, DateTimeImmutable $ahora): string
    {
        $segundos = max(0, $ahora->getTimestamp() - $momento->getTimestamp());

        return match (true) {
            $segundos < 3600 => 'hace ' . intdiv($segundos, 60) . ' min',
            $segundos < 172_800 => 'hace ' . intdiv($segundos, 3600) . ' h',
            default => 'hace ' . intdiv($segundos, 86_400) . ' días',
        };
    }
}
