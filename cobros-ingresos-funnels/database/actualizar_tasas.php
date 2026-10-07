<?php

declare(strict_types=1);

/**
 * Actualiza monedas.tasa_a_usd con tasas de cambio reales, para que los totales en
 * USD (Dashboard, Cobros, Pagos, Cohortes) dejen de usar las de ejemplo de la
 * migracion 005.
 *
 * Uso:
 *   php database/actualizar_tasas.php                   baja las tasas de TASAS_URL y las guarda
 *   php database/actualizar_tasas.php --simular         hace las mismas cuentas y dice que cambiaria, sin guardar nada
 *   php database/actualizar_tasas.php --forzar          acepta tambien las tasas que saltan mas de 50% de una corrida a otra
 *   php database/actualizar_tasas.php --archivo=R.json  lee las tasas de un archivo (el mismo formato) en vez de la red
 *   php database/actualizar_tasas.php --ayuda
 *
 * Variables (ademas de las DB_* de la app):
 *   TASAS_URL     de donde bajar las tasas, en https; por defecto open.er-api.com (ExchangeRate-API:
 *                 gratis, sin clave, cerca de 160 monedas, una vez por dia). Sirve cualquier servicio
 *                 que conteste {"base": "USD" o "base_code": "USD", "rates": {"EUR": 0.86, ...}}.
 *   TASAS_FUENTE  como se llama la fuente en la pantalla; por defecto, el host de TASAS_URL.
 *
 * Pensado para correr por cron una vez por dia. Lo normal sale por la salida estandar;
 * lo que pide atencion (una tasa que salto, una que la fuente trae mal, o un error) sale
 * por la de errores, que es la que cron manda por mail. Termina con 1 si no actualizo nada
 * (no se escribe nada) y con 2 si los argumentos estan mal.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Tasas\ActualizadorDeTasas;
use App\Tasas\DescargaDeTasas;
use App\Tasas\RespuestaDeTasas;
use App\Tasas\TasasInvalidas;

const URL_POR_DEFECTO = 'https://open.er-api.com/v6/latest/USD';

const USO = <<<'TEXTO'
Uso:
  php database/actualizar_tasas.php                   baja las tasas de TASAS_URL y las guarda
  php database/actualizar_tasas.php --simular         hace las mismas cuentas y dice que cambiaria, sin guardar nada
  php database/actualizar_tasas.php --forzar          acepta tambien las tasas que saltan mas de 50% de una corrida a otra
  php database/actualizar_tasas.php --archivo=R.json  lee las tasas de un archivo (el mismo formato) en vez de la red
  php database/actualizar_tasas.php --ayuda

TEXTO;

$opciones = getopt('', ['archivo:', 'simular', 'forzar', 'ayuda']);
$conocidas = ['--archivo', '--simular', '--forzar', '--ayuda'];
foreach (array_slice($argv, 1) as $argumento) {
    if (!in_array(explode('=', $argumento, 2)[0], $conocidas, true)) {
        fwrite(STDERR, "Argumento desconocido: {$argumento}\n\n" . USO);
        exit(2);
    }
}
if (isset($opciones['ayuda'])) {
    echo USO;
    exit(0);
}
$simular = isset($opciones['simular']);
$forzar = isset($opciones['forzar']);
$archivo = $opciones['archivo'] ?? null;
if ($archivo !== null && !is_string($archivo)) {
    fwrite(STDERR, "--archivo se pasa una sola vez.\n\n" . USO);
    exit(2);
}

try {
    if ($archivo !== null) {
        $json = @file_get_contents($archivo);
        if ($json === false) {
            throw new TasasInvalidas("No se pudo leer el archivo {$archivo}.");
        }
        $fuente = Config::variable('TASAS_FUENTE', basename($archivo));
    } else {
        $url = Config::variable('TASAS_URL', URL_POR_DEFECTO);
        $json = DescargaDeTasas::leer($url);
        $fuente = Config::variable('TASAS_FUENTE', (string) parse_url($url, PHP_URL_HOST));
    }

    $informe = (new ActualizadorDeTasas())->aplicar(RespuestaDeTasas::desdeJson($json), $fuente, $simular, $forzar);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

$cuando = $informe['cuando']->format('Y-m-d H:i') . ' UTC';
if ($simular) {
    echo "SIMULACIÓN: no se guardó nada.\n";
}
printf(
    "%s %d monedas con las tasas de %s (cotización del %s).\n",
    $simular ? 'Se actualizarían' : 'Actualizadas',
    count($informe['actualizadas']),
    $fuente,
    $cuando
);
if ($informe['sinDato'] !== []) {
    printf("Sin dato en la fuente (quedan como estaban): %s\n", implode(', ', $informe['sinDato']));
}

$atencion = [];
foreach ($informe['rechazadas'] as $codigo => $motivo) {
    $atencion[] = "  {$codigo}: la fuente la trae mal ({$motivo}); queda como estaba.";
}
foreach ($informe['sospechosas'] as $codigo => $tasa) {
    $atencion[] = "  {$codigo}: pasaría de {$tasa['anterior']} a {$tasa['propuesta']} USD, un salto de más de "
        . (int) (ActualizadorDeTasas::CAMBIO_MAXIMO * 100) . '%; queda como estaba (con --forzar se acepta).';
}
if ($atencion !== []) {
    fwrite(STDERR, "Piden atención:\n" . implode("\n", $atencion) . "\n");
}
