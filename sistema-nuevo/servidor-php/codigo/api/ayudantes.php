<?php

declare(strict_types=1);

/** Funciones de apoyo compartidas por los endpoints: errores, deltas, presets de país. */

/**
 * Respuesta de error de la API. `error` es el texto en español, para quien lee la respuesta a mano
 * (datos/ejemplos/peticiones-api.http); `codigo` es un identificador estable que la interfaz traduce
 * al idioma elegido (claves err_<codigo> en interfaz/js/i18n/). $extra suma datos propios del error,
 * p. ej. retry_after. Todo error de la API sale por acá: pruebas/php/api-error-test.php lo exige.
 */
function api_error(int $status, string $codigo, string $mensaje, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(['error' => $mensaje, 'codigo' => $codigo] + $extra, JSON_UNESCAPED_UNICODE);
}

function api_not_found(): void
{
    api_error(404, 'ruta_no_encontrada', 'ruta no encontrada');
}

function api_unauthorized(): void
{
    api_error(401, 'no_autenticado', 'no autenticado');
}

/** @return array{total:int,page:int,per_page:int,total_pages:int} Forma común de paginación de /api/users y /api/timeline. */
function api_pagination_meta(int $total, int $page, int $perPage): array
{
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
    ];
}

function api_delta_pct(int|float $value, int|float|null $average): ?float
{
    if ($average === null || $average <= 0) {
        return null;
    }
    return (($value - $average) / $average) * 100;
}

/** @return string[]|null null = sin filtro de país (todos los países) */
function api_resolve_scope_countries(string $scope, array $groups): ?array
{
    if ($scope === '' || $scope === 'all') {
        return null;
    }
    if (str_starts_with($scope, 'preset:')) {
        $key = substr($scope, strlen('preset:'));
        foreach ($groups['presets'] as $preset) {
            if ($preset['key'] === $key) {
                return $preset['countries'];
            }
        }
        return null;
    }
    if (str_starts_with($scope, 'country:')) {
        return [substr($scope, strlen('country:'))];
    }
    return null;
}

/**
 * Etiqueta legible del universo de comparación, o null si no hay una específica:
 * justo los casos en que api_resolve_scope_countries() no aplica filtro de país
 * ("all", vacío, preset desconocido, formato no reconocido). "Todos los países"
 * es texto de interfaz, no dato: lo pone el frontend en el idioma elegido, así
 * el resumen de filtros no mezcla español con el resto en inglés.
 */
function api_scope_label(string $scope, array $groups): ?string
{
    if (str_starts_with($scope, 'preset:')) {
        $key = substr($scope, strlen('preset:'));
        foreach ($groups['presets'] as $preset) {
            if ($preset['key'] === $key) {
                return $preset['label'];
            }
        }
        // Preset desconocido (ej. borrado después de guardar un filtro con él):
        // mismo criterio que api_resolve_scope_countries, nunca el string crudo.
        return null;
    }
    if (str_starts_with($scope, 'country:')) {
        $code = substr($scope, strlen('country:'));
        return $groups['countries'][$code] ?? $code;
    }
    return null;
}
