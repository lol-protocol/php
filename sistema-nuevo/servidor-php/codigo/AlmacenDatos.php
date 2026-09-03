<?php

declare(strict_types=1);

require_once __DIR__ . '/ConexionBd.php';

/**
 * Acceso de solo lectura a usuarios/países/grupos — PostgreSQL, no JSON (los
 * JSON en datos/ quedan como artefacto legible + insumo del CSV para Java).
 * Las acciones viven en AlmacenAcciones.php (son la tabla grande, paginada).
 */
final class AlmacenDatos
{
    private const CAMPOS_USUARIO =
        'u.id, u.nombre AS name, u.pais_codigo AS country, u.edad AS age, u.genero AS gender,
         p.nombre AS country_name';

    /** @return array{items: array, total: int} */
    public static function usersPage(int $pagina, int $porPagina, string $busqueda): array
    {
        $pdo = ConexionBd::obtener();
        $patron = '%' . $busqueda . '%';

        $stmtTotal = $pdo->prepare(
            'SELECT COUNT(*) FROM usuarios u JOIN paises p ON p.codigo = u.pais_codigo
             WHERE u.nombre ILIKE :patron OR p.nombre ILIKE :patron'
        );
        $stmtTotal->execute(['patron' => $patron]);

        $stmt = $pdo->prepare(
            'SELECT ' . self::CAMPOS_USUARIO . '
             FROM usuarios u JOIN paises p ON p.codigo = u.pais_codigo
             WHERE u.nombre ILIKE :patron OR p.nombre ILIKE :patron
             ORDER BY u.nombre
             LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue('patron', $patron);
        $stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => (int) $stmtTotal->fetchColumn()];
    }

    public static function userById(string $id): ?array
    {
        $stmt = ConexionBd::obtener()->prepare(
            'SELECT ' . self::CAMPOS_USUARIO . '
             FROM usuarios u JOIN paises p ON p.codigo = u.pais_codigo WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    public static function groups(): array
    {
        $pdo = ConexionBd::obtener();

        $grupos = $pdo->query('SELECT clave, etiqueta FROM grupos_paises ORDER BY etiqueta')->fetchAll();
        $miembros = $pdo->query('SELECT grupo_clave, pais_codigo FROM grupo_pais ORDER BY pais_codigo')->fetchAll();

        $paisesPorGrupo = [];
        foreach ($miembros as $m) {
            $paisesPorGrupo[$m['grupo_clave']][] = $m['pais_codigo'];
        }

        $presets = array_map(fn ($g) => [
            'key' => $g['clave'],
            'label' => $g['etiqueta'],
            'countries' => $paisesPorGrupo[$g['clave']] ?? [],
        ], $grupos);

        $countryRows = $pdo->query('SELECT codigo, nombre FROM paises')->fetchAll();

        return ['presets' => $presets, 'countries' => array_column($countryRows, 'nombre', 'codigo')];
    }
}
