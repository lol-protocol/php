<?php
declare(strict_types=1);

/*
 * Genera pruebas/vectores.tsv: para cada consulta de pruebas/consultas.txt, el pasaje que
 * queda primero y su puntaje coseno con seis decimales. La versión en Java lee el mismo
 * archivo y debe dar lo mismo.
 *
 *   php bin/vectores.php > pruebas/vectores.tsv     # regenera
 *   php bin/vectores.php --comprobar                # sale con 1 si el archivo no coincide
 */

require __DIR__ . '/../src/cargar.php';

use ChatLibros\Indice;
use ChatLibros\Libro;
use ChatLibros\Normaliza;

$raiz = __DIR__ . '/..';
$libro = Libro::desdeArchivo("$raiz/ejemplos/muestra.txt");
$terminos = [];
foreach ($libro->pasajes as $pasaje) {
    $terminos[] = Normaliza::terminos($pasaje['texto']);
}
$indice = new Indice($terminos);

$lineas = ["# consulta\tpasaje_primero\tpuntaje"];
$consultas = file("$raiz/pruebas/consultas.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($consultas ?: [] as $consulta) {
    $mejor = $indice->buscar(Normaliza::terminos($consulta), 1)[0];
    $lineas[] = sprintf("%s\t%d\t%.6f", $consulta, $mejor['pasaje'], $mejor['puntaje']);
}
$salida = implode("\n", $lineas) . "\n";

if (in_array('--comprobar', $argv, true)) {
    $actual = @file_get_contents("$raiz/pruebas/vectores.tsv");
    if ($actual !== $salida) {
        fwrite(STDERR, "pruebas/vectores.tsv no está al día: php bin/vectores.php > pruebas/vectores.tsv\n");
        exit(1);
    }
    echo "pruebas/vectores.tsv al día\n";
    exit(0);
}

echo $salida;
