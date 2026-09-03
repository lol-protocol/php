<?php

declare(strict_types=1);

/** Funciones de apoyo compartidas por los endpoints: errores, deltas, presets de país. */

function api_not_found(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'ruta no encontrada']);
}

function api_unauthorized(): void
{
    http_response_code(401);
    echo json_encode(['error' => 'no autenticado']);
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

function api_scope_label(string $scope, array $groups): string
{
    if ($scope === '' || $scope === 'all') {
        return 'Todos los países';
    }
    if (str_starts_with($scope, 'preset:')) {
        $key = substr($scope, strlen('preset:'));
        foreach ($groups['presets'] as $preset) {
            if ($preset['key'] === $key) {
                return $preset['label'];
            }
        }
    }
    if (str_starts_with($scope, 'country:')) {
        $code = substr($scope, strlen('country:'));
        return $groups['countries'][$code] ?? $code;
    }
    return $scope;
}
