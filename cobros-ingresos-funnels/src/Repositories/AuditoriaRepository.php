<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;

final class AuditoriaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Registra la accion. Punto unico usado por los controllers para no
     * repetir el registrar() en cada uno.
     *
     * Sin login (ver _Garbage/README.md) no existe mas "el usuario de la
     * sesion actual": todo queda atribuido a usuario_id = NULL, que
     * listado() muestra como "Sistema". Las entradas de auditoria de antes
     * de sacar el login, con su usuario_id real, no se tocaron.
     *
     * Exige estar dentro de Database::transaccion(): la entrada tiene que
     * quedar en la misma transaccion que el cambio que describe, o un cambio
     * puede quedar sin auditar si la segunda escritura falla. Asi era en ocho
     * flujos (altas, ediciones, usuarios); con la exigencia, un flujo nuevo
     * que se olvide la transaccion falla en los tests en vez de en silencio.
     */
    public static function auditar(string $accion, string $entidad, int $entidadId, string $detalle): void
    {
        if (!Database::connection()->inTransaction()) {
            throw new LogicException(sprintf(
                'La auditoría de "%s %s #%d" tiene que registrarse dentro de Database::transaccion(), junto con el cambio que describe.',
                $accion,
                $entidad,
                $entidadId
            ));
        }

        (new self())->registrar(null, $accion, $entidad, $entidadId, $detalle);
    }

    public function registrar(?int $usuarioId, string $accion, string $entidad, int $entidadId, string $detalle): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle)
             VALUES (:usuario_id, :accion, :entidad, :entidad_id, :detalle)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':accion' => $accion,
            ':entidad' => $entidad,
            ':entidad_id' => $entidadId,
            ':detalle' => $detalle,
        ]);
    }

    /**
     * Una pagina del historial, de la mas reciente a la mas antigua, pedida por
     * cursor y no con LIMIT/OFFSET: la pagina que sigue es "las que vienen despues
     * de esta fila" (creado_en, id), y con el indice de la migracion 008 cuesta lo
     * mismo con diez filas que con diez millones y a cualquier profundidad. OFFSET
     * lee y descarta todas las filas anteriores, y COUNT(*) las cuenta una por una:
     * las dos crecian en linea recta con la tabla, que solo se inserta y nunca se
     * achica. A cambio no hay "pagina 7 de 40" ni un total: se navega hacia las
     * mas recientes o hacia las mas antiguas.
     *
     * $antes pide las filas mas antiguas que ese cursor; $despues, las mas
     * recientes. Un cursor que no es uno (texto cualquiera) se ignora y se
     * muestra la primera pagina; si vienen los dos manda $antes. Al subir hasta
     * lo mas reciente se devuelve la primera pagina entera, no una cortada.
     *
     * masAntiguas y masRecientes son el cursor para pedir lo que sigue de cada
     * lado, o null si de ese lado no hay mas.
     *
     * @return array{filas: array<int, array<string, mixed>>, masAntiguas: ?string, masRecientes: ?string}
     */
    public function pagina(?string $antes = null, ?string $despues = null): array
    {
        $limite = Paginacion::POR_PAGINA;
        $cursorAntes = self::leerCursor($antes);
        $cursorDespues = $cursorAntes === null ? self::leerCursor($despues) : null;

        if ($cursorDespues !== null) {
            // Hacia arriba: las que vienen despues, de la mas vieja a la mas nueva; se dan vuelta al final.
            $filas = $this->filas('WHERE (a.creado_en, a.id) > (:creado_en::timestamp, :id::bigint)', 'a.creado_en ASC, a.id ASC', $cursorDespues, $limite + 1);
            if (count($filas) <= $limite) {
                return $this->pagina();
            }
            $filas = array_reverse(array_slice($filas, 0, $limite));

            return ['filas' => $filas, 'masAntiguas' => self::cursorDe($filas[$limite - 1]), 'masRecientes' => self::cursorDe($filas[0])];
        }

        if ($cursorAntes !== null) {
            $filas = $this->filas('WHERE (a.creado_en, a.id) < (:creado_en::timestamp, :id::bigint)', 'a.creado_en DESC, a.id DESC', $cursorAntes, $limite + 1);
            if ($filas === []) {
                return $this->pagina();
            }
            $hayMasAntiguas = count($filas) > $limite;
            $filas = array_slice($filas, 0, $limite);

            return [
                'filas' => $filas,
                'masAntiguas' => $hayMasAntiguas ? self::cursorDe($filas[$limite - 1]) : null,
                'masRecientes' => self::cursorDe($filas[0]),
            ];
        }

        $filas = $this->filas('', 'a.creado_en DESC, a.id DESC', null, $limite + 1);
        $hayMasAntiguas = count($filas) > $limite;
        $filas = array_slice($filas, 0, $limite);

        return [
            'filas' => $filas,
            'masAntiguas' => $hayMasAntiguas ? self::cursorDe($filas[$limite - 1]) : null,
            'masRecientes' => null,
        ];
    }

    /**
     * El cursor de una fila, para ponerlo en la URL: su creado_en tal como lo
     * devuelve Postgres (con microsegundos, que es lo que hace unico el orden
     * entre dos filas de la misma transaccion) y su id.
     *
     * @param array<string, mixed> $fila
     */
    public static function cursorDe(array $fila): string
    {
        return $fila['creado_en'] . ',' . $fila['id'];
    }

    /**
     * @param array{creado_en: string, id: int}|null $cursor
     * @param string $donde y $orden son fragmentos fijos del codigo, nunca entrada de usuario
     * @return array<int, array<string, mixed>>
     */
    private function filas(string $donde, string $orden, ?array $cursor, int $limite): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.creado_en, a.accion, a.entidad, a.entidad_id, a.detalle,
                    COALESCE(u.nombre, 'Sistema') AS usuario
             FROM auditoria a
             LEFT JOIN usuarios_sistema u ON u.id = a.usuario_id
             {$donde}
             ORDER BY {$orden}
             LIMIT :limite"
        );
        if ($cursor !== null) {
            $stmt->bindValue(':creado_en', $cursor['creado_en']);
            $stmt->bindValue(':id', $cursor['id'], PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * El cursor que vino por la URL, ya validado: "2026-10-07 00:12:34.567890,123"
     * (creado_en y id). Un texto que no es un cursor, o con una fecha que no
     * existe, da null: nadie tiene que poder romper la pantalla editando la URL.
     *
     * @return array{creado_en: string, id: int}|null
     */
    private static function leerCursor(?string $cursor): ?array
    {
        if ($cursor === null || preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(\.\d{1,6})?,(\d{1,18})$/', $cursor, $m) !== 1) {
            return null;
        }
        // En UTC porque solo se valida el calendario (que el dia exista), no un instante: sin horario de verano que lo mueva.
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $m[1], new DateTimeZone('UTC'));
        if ($fecha === false || $fecha->format('Y-m-d H:i:s') !== $m[1]) {
            return null;
        }

        return ['creado_en' => $m[1] . $m[2], 'id' => (int) $m[3]];
    }
}
