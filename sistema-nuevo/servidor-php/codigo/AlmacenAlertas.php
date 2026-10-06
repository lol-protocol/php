<?php

declare(strict_types=1);

/**
 * Las dos reglas de alerta, en un solo lugar: el panel de alertas arma el "top" de cada una y el KPI del
 * dashboard (AlmacenKpis) solo cuenta usuarios afectados, ambos con las mismas definiciones de acá.
 */
final class AlmacenAlertas
{
    private const LIMITE_USUARIOS = 15;

    /** La IP de la acción es de un país distinto al que declaró el usuario (alias: a = acciones, u = usuarios). */
    private const IP_FUERA_DEL_PAIS = 'a.ip_pais_codigo IS NOT NULL AND a.ip_pais_codigo <> u.pais_codigo';

    /** Cada acción con país de IP, junto a la anterior del mismo usuario (LAG): de ahí salen los cambios de país. */
    private const CAMBIOS_CTE = <<<'SQL'
        WITH cambios AS (
            SELECT a.usuario_id, u.nombre, LAG(a.ip_pais_codigo) OVER ventana AS pais_anterior, a.ip_pais_codigo AS pais_actual,
                   LAG(a.marca_temporal) OVER ventana AS tiempo_anterior, a.marca_temporal AS tiempo_actual
            FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.ip_pais_codigo IS NOT NULL
            WINDOW ventana AS (PARTITION BY a.usuario_id ORDER BY a.marca_temporal)
        )
        SQL;

    /** Cambio de país "imposible": otro país que la acción anterior, en menos horas que la ventana (:horas). */
    private const CAMBIOS_CONDICION = <<<'SQL'
        pais_anterior IS NOT NULL AND pais_anterior <> pais_actual AND (EXTRACT(EPOCH FROM (tiempo_actual - tiempo_anterior)) / 3600) < :horas
        SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** La misma regla que IP_FUERA_DEL_PAIS, para una acción ya leída (la tarjeta del timeline marca ip_mismatch). */
    public static function esIpFueraDelPais(?string $paisDeLaIp, string $paisDelUsuario): bool
    {
        return $paisDeLaIp !== null && $paisDeLaIp !== $paisDelUsuario;
    }

    public function ipMismatches(): array
    {
        $resumen = $this->resumenIpFueraDelPais();

        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.nombre, u.pais_codigo, COUNT(*) AS cantidad, MAX(a.marca_temporal) AS last_seen
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             WHERE ' . self::IP_FUERA_DEL_PAIS . '
             GROUP BY u.id, u.nombre, u.pais_codigo
             ORDER BY cantidad DESC, u.id
             LIMIT :limite'
        );
        $stmt->bindValue('limite', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_mismatches' => $resumen['total'],
            'total_users_affected' => $resumen['usuarios'],
            'top' => array_map(fn ($f) => self::conUsuario($f) + [
                'country' => $f['pais_codigo'], 'mismatch_count' => (int) $f['cantidad'], 'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }

    /** @param int $umbral Sensibilidad 0-100: más alto = ventana de tiempo más amplia cuenta como "cambio imposible". */
    public function cambiosPaisImposibles(int $umbral = 50): array
    {
        $resumen = $this->resumenCambiosDePais($umbral);

        // LIMITE_USUARIOS es sobre usuarios distintos, no filas: sin el DISTINCT ON,
        // un usuario con varios pares de país "imposibles" ocuparía varios lugares
        // del LIMIT -- se queda solo con su par más frecuente (más reciente si empata).
        $stmt = $this->pdo->prepare(
            self::CAMBIOS_CTE . ',
             por_usuario AS (
                 SELECT DISTINCT ON (usuario_id) usuario_id, nombre, pais_anterior, pais_actual, COUNT(*) AS cantidad, MAX(tiempo_actual) AS last_seen
                 FROM cambios
                 WHERE ' . self::CAMBIOS_CONDICION . '
                 GROUP BY usuario_id, nombre, pais_anterior, pais_actual
                 ORDER BY usuario_id, COUNT(*) DESC, MAX(tiempo_actual) DESC, pais_actual
             )
             SELECT usuario_id AS id, nombre, pais_anterior, pais_actual, cantidad, last_seen
             FROM por_usuario
             ORDER BY cantidad DESC, usuario_id
             LIMIT :limite'
        );
        $stmt->bindValue('horas', self::ventanaHoras($umbral));
        $stmt->bindValue('limite', self::LIMITE_USUARIOS, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total_changes' => $resumen['total'],
            'total_users_affected' => $resumen['usuarios'],
            'top' => array_map(fn ($f) => self::conUsuario($f) + [
                'pais_anterior' => $f['pais_anterior'], 'pais_actual' => $f['pais_actual'], 'cambio_count' => (int) $f['cantidad'], 'last_seen' => $f['last_seen'],
            ], $stmt->fetchAll()),
        ];
    }

    /** Usuarios distintos con alguna IP fuera de su país, sin armar el top (lo usa el KPI del dashboard). */
    public function usuariosConIpFueraDelPais(): int
    {
        return $this->resumenIpFueraDelPais()['usuarios'];
    }

    /** Usuarios distintos con algún cambio de país imposible con esa sensibilidad, sin armar el top (KPI). */
    public function usuariosConCambioPaisImposible(int $umbral): int
    {
        return $this->resumenCambiosDePais($umbral)['usuarios'];
    }

    /** @return array{total:int, usuarios:int} Acciones con la IP fuera del país, y de cuántos usuarios distintos. */
    private function resumenIpFueraDelPais(): array
    {
        $fila = $this->pdo->query(
            'SELECT COUNT(*) AS total, COUNT(DISTINCT a.usuario_id) AS usuarios
             FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
             WHERE ' . self::IP_FUERA_DEL_PAIS
        )->fetch();

        return ['total' => (int) $fila['total'], 'usuarios' => (int) $fila['usuarios']];
    }

    /** @return array{total:int, usuarios:int} Cambios de país imposibles con esa sensibilidad, y de cuántos usuarios distintos. */
    private function resumenCambiosDePais(int $umbral): array
    {
        $stmt = $this->pdo->prepare(
            self::CAMBIOS_CTE . ' SELECT COUNT(*) AS total, COUNT(DISTINCT usuario_id) AS usuarios FROM cambios WHERE ' . self::CAMBIOS_CONDICION
        );
        $stmt->bindValue('horas', self::ventanaHoras($umbral));
        $stmt->execute();
        $fila = $stmt->fetch();

        return ['total' => (int) $fila['total'], 'usuarios' => (int) $fila['usuarios']];
    }

    /** Horas por debajo de las cuales un cambio de país es "imposible": 0.5 h con umbral 0, hasta 4 h con umbral 100. */
    private static function ventanaHoras(int $umbral): float
    {
        return 0.5 + (max(0, min(100, $umbral)) / 100) * 3.5;
    }

    private static function conUsuario(array $fila): array
    {
        return ['user_id' => $fila['id'], 'user_name' => $fila['nombre']];
    }
}
