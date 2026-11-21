<?php

declare(strict_types=1);

// Vuelca el estado efectivo de todos los interruptores en JSON, para que una aplicación en cualquier lenguaje lo lea
// sin reimplementar las reglas (ver CONTRATO.md).
//   php bin/estado.php                      lo imprime
//   php bin/estado.php --salida=ruta.json   lo escribe de forma atómica (p. ej. desde un cron o tras cada cambio)

require_once __DIR__ . '/../src/Interruptores.php';

use LolProtocol\Interruptores\Interruptores;

$raiz = dirname(__DIR__);
$var = rtrim((string) (getenv('INTERRUPTORES_VAR') ?: $raiz . '/var'), '/');
$salida = null;
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--salida=')) {
        $salida = substr($argumento, strlen('--salida='));
    } else {
        fwrite(STDERR, "Uso: php bin/estado.php [--salida=ruta.json]\n");
        exit(2);
    }
}

try {
    $cat = new Interruptores($raiz . '/catalogo.json', $var . '/estado.json');
    $cat->congelar();
    $refs = [];
    foreach (array_keys($cat->interruptores()) as $ref) {
        $refs[$ref] = ['activo' => $cat->activo($ref), 'al_apagar' => $cat->alApagar($ref)];
    }
    $json = json_encode(['version' => 1, 'generado' => gmdate('Y-m-d\TH:i:s\Z'), 'interruptores' => $refs],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($salida === null) {
    echo $json;
    exit(0);
}
$temp = $salida . '.' . bin2hex(random_bytes(4)) . '.tmp';
if (file_put_contents($temp, $json) === false || !rename($temp, $salida)) {
    @unlink($temp);
    fwrite(STDERR, "Error: no se pudo escribir $salida\n");
    exit(1);
}
echo "Escrito $salida (" . count($refs) . " interruptores).\n";
