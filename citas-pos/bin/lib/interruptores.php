<?php

declare(strict_types=1);

/**
 * Qué canales de citas se generan según los interruptores del repositorio (`interruptores/`, función `pos.citas`).
 *
 * Solo lo usa `render.php --interruptores=<carpeta> --salida=<carpeta>`: sin esas opciones, citas-pos no lee nada de fuera.
 * Un canal se genera si la función `pos.citas` y su opción (`sitio`, `correo` o `juego`) están encendidas.
 */

/** Los canales de `pos.citas`, que son también las opciones de la función. */
const CANALES_DE_CITAS = ['sitio', 'correo', 'juego'];

/**
 * @return list<string> los canales encendidos
 * @throws RuntimeException si la carpeta no tiene el código de interruptores
 * @throws Throwable si el catálogo o el estado no se pueden leer (es un error, no un «todo apagado»)
 */
function canalesActivosDeInterruptores(string $carpeta): array
{
    $carpeta = rtrim($carpeta, '/');
    $fuente = "$carpeta/src/Interruptores.php";
    if (!is_file($fuente)) {
        throw new RuntimeException("no hay código de interruptores en «{$carpeta}» (falta src/Interruptores.php)");
    }
    // El código se carga una sola vez: la clase no depende de su carpeta, solo recibe las rutas del catálogo y del estado.
    if (!class_exists(\LolProtocol\Interruptores\Interruptores::class, false)) {
        require_once $fuente;
    }
    $cat = new \LolProtocol\Interruptores\Interruptores("$carpeta/catalogo.json", "$carpeta/var/estado.json");

    $activos = [];
    foreach (CANALES_DE_CITAS as $canal) {
        if ($cat->activo('pos.citas') && $cat->activo("pos.citas:{$canal}")) {
            $activos[] = $canal;
        }
    }
    return $activos;
}

/**
 * Deja solo los archivos de los canales encendidos. La hoja del sitio (`citas-pos.css`) la usan el sitio y el juego.
 *
 * @param array<string, string> $archivos ruta relativa a salida/ => contenido
 * @param list<string> $canales los canales encendidos
 * @return array<string, string>
 * @throws RuntimeException si un archivo no es de ningún canal conocido: mejor parar que publicar algo sin saber de quién es
 */
function filtrarPorCanales(array $archivos, array $canales): array
{
    $salida = [];
    foreach ($archivos as $ruta => $contenido) {
        if (str_ends_with($ruta, '.correo.html')) {
            $necesita = ['correo'];
        } elseif (str_ends_with($ruta, '.sitio.html')) {
            $necesita = ['sitio'];
        } elseif (str_ends_with($ruta, '.juego.html') || in_array($ruta, ['citas-pos-juego.css', 'citas-pos-juego.js', 'demo-juego.html'], true)) {
            $necesita = ['juego'];
        } elseif ($ruta === 'citas-pos.css') {
            $necesita = ['sitio', 'juego'];
        } else {
            throw new RuntimeException("«{$ruta}» no es de ningún canal conocido (sitio, correo o juego): agrégalo a filtrarPorCanales().");
        }
        if (array_intersect($necesita, $canales) !== []) {
            $salida[$ruta] = $contenido;
        }
    }
    return $salida;
}
