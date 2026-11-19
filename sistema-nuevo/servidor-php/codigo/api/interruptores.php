<?php

declare(strict_types=1);

use LolProtocol\Interruptores\Interruptores;

/**
 * Qué partes de la API puede apagar el equipo con los interruptores del repositorio (`interruptores/`).
 *
 * Es opcional. Sin la variable de entorno BACKOFFICE_INTERRUPTORES (la ruta de la carpeta `interruptores/`) no se lee
 * nada y la API es la de siempre. Con ella, antes de atender una ruta con sesión se mira si su función está apagada:
 * todo cuelga de `gen.backoffice`, y cada función de la API tiene además su propio interruptor (API_INTERRUPTORES, en
 * rutas.php). Login, logout y session no se apagan: así se puede entrar y salir aunque el backoffice esté apagado.
 *
 * Una función apagada responde según su política (`al_apagar` en el catálogo de interruptores): `ocultar` es un 404
 * como si no existiera, y `no_disponible` es un 503 que lo dice. Si los interruptores están configurados pero no se
 * pueden leer, responde 503: mejor cortar que atender algo que quizá está apagado.
 */

/** El interruptor de todo el backoffice: apagado, ninguna ruta con sesión responde. */
const API_INTERRUPTOR_BACKOFFICE = 'gen.backoffice';

/**
 * Los interruptores configurados, o null si BACKOFFICE_INTERRUPTORES no está.
 *
 * @throws Throwable si están configurados pero no se pueden leer (falta el código, el catálogo o el estado está dañado)
 */
function api_interruptores(?string $carpeta = null): ?Interruptores
{
    $carpeta ??= getenv('BACKOFFICE_INTERRUPTORES') ?: '';
    if ($carpeta === '') {
        return null;
    }
    $carpeta = rtrim($carpeta, '/');
    if (!class_exists(Interruptores::class, false)) {
        $fuente = "$carpeta/src/Interruptores.php";
        if (!is_file($fuente)) {
            throw new RuntimeException("no hay código de interruptores en $carpeta (BACKOFFICE_INTERRUPTORES)");
        }
        require_once $fuente;
    }
    return new Interruptores("$carpeta/catalogo.json", "$carpeta/var/estado.json");
}

/** El primer interruptor apagado que afecta a esa función de la API (el del backoffice, o el suyo), o null. */
function api_interruptor_apagado(Interruptores $interruptores, string $handler): ?string
{
    $refs = [API_INTERRUPTOR_BACKOFFICE];
    if (isset(API_INTERRUPTORES[$handler])) {
        $refs[] = API_INTERRUPTORES[$handler];
    }
    foreach ($refs as $ref) {
        if (!$interruptores->activo($ref)) {
            return $ref;
        }
    }
    return null;
}

/**
 * Exige que la función de esta ruta no esté apagada. Si lo está, responde (404 `funcion_apagada` o 503
 * `funcion_no_disponible`, según su política) y devuelve false: hay que cortar. Sin interruptores configurados, siempre true.
 */
function api_exigir_funcion(string $handler, ?string $carpeta = null): bool
{
    $interruptores = null;
    $apagado = null;
    try {
        $interruptores = api_interruptores($carpeta);
        $apagado = $interruptores === null ? null : api_interruptor_apagado($interruptores, $handler);
    } catch (Throwable $e) {
        error_log('interruptores: ' . $e->getMessage());
        api_error(503, 'interruptores_ilegibles', 'no se pudo leer el estado de los interruptores');
        return false;
    }
    if ($interruptores === null || $apagado === null) {
        return true;
    }
    if ($interruptores->alApagar($apagado) === 'no_disponible') {
        api_error(503, 'funcion_no_disponible', 'función no disponible por ahora');
    } else {
        api_error(404, 'funcion_apagada', 'función apagada');
    }
    return false;
}
