<?php
declare(strict_types=1);

/*
 * Chat en la terminal con un libro:
 *
 *   php bin/chat.php                       # usa ejemplos/muestra.txt
 *   php bin/chat.php ruta/al/libro.txt     # cualquier .txt con el formato del README
 *
 * Escribe una pregunta y Enter; «salir» (o Ctrl+D) termina.
 */

require __DIR__ . '/../src/cargar.php';

use ChatLibros\Conversador;
use ChatLibros\Libro;

$ruta = $argv[1] ?? __DIR__ . '/../ejemplos/muestra.txt';
if (!is_file($ruta)) {
    fwrite(STDERR, "No encuentro el libro: $ruta\n");
    exit(1);
}

$libro = Libro::desdeArchivo($ruta);
if ($libro->pasajes === []) {
    fwrite(STDERR, "El archivo no tiene texto que leer: $ruta\n");
    exit(1);
}
$conversador = new Conversador($libro);

echo "«{$libro->titulo}», de {$libro->autor} — " . count($libro->pasajes) . " pasajes.\n";
echo "Escribe tu pregunta (o «salir»).\n";

while (true) {
    echo "\n> ";
    $linea = fgets(STDIN);
    if ($linea === false) {
        echo "\n";
        break;
    }
    $pregunta = trim($linea);
    if (in_array(mb_strtolower($pregunta), ['salir', 'exit', 'chao'], true)) {
        break;
    }
    echo $conversador->responder($pregunta), "\n";
}
