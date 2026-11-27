<?php
declare(strict_types=1);

/*
 * Chat en la terminal con un libro:
 *
 *   php bin/chat.php                          # usa ejemplos/muestra.txt, sin IA
 *   php bin/chat.php ruta/al/libro.txt        # cualquier .txt con el formato del README
 *   php bin/chat.php --ia ruta/al/libro.txt   # además redacta con Claude (ver README: modo con IA)
 *   php bin/chat.php --estilo=original ejemplos/quijote.txt   # libros largos: el texto como lo escribió el autor
 *                                                             # (por defecto «moderno»; en la conversación: /original, /moderno)
 *
 * Escribe una pregunta y Enter; «salir» (o Ctrl+D) termina.
 */

require __DIR__ . '/../src/cargar.php';

use ChatLibros\Conversador;
use ChatLibros\Dialogo;
use ChatLibros\GeneradorClaude;
use ChatLibros\Libro;
use ChatLibros\Parafrasea;
use ChatLibros\Sinonimos;

$usarIa = false;
$estilo = Parafrasea::MODERNO;
$ruta = null;
foreach (array_slice($argv, 1) as $argumento) {
    if ($argumento === '--ia') {
        $usarIa = true;
    } elseif (str_starts_with($argumento, '--estilo=')) {
        $estilo = substr($argumento, strlen('--estilo='));
        if ($estilo !== Parafrasea::MODERNO && $estilo !== Parafrasea::ORIGINAL) {
            fwrite(STDERR, "--estilo debe ser «moderno» o «original».\n");
            exit(1);
        }
    } elseif ($ruta === null) {
        $ruta = $argumento;
    }
}
$ruta ??= __DIR__ . '/../ejemplos/muestra.txt';

if (!is_file($ruta)) {
    fwrite(STDERR, "No encuentro el libro: $ruta\n");
    exit(1);
}
if ($usarIa && ($motivo = GeneradorClaude::motivoNoDisponible()) !== null) {
    fwrite(STDERR, "No se puede usar --ia. $motivo\n");
    exit(1);
}

$libro = Libro::desdeArchivo($ruta);
if ($libro->pasajes === []) {
    fwrite(STDERR, "El archivo no tiene texto que leer: $ruta\n");
    exit(1);
}

// Los sinónimos generales y, si existe, los del libro (<libro>.sinonimos.tsv junto al .txt).
$sinonimos = Sinonimos::desdeArchivos([
    __DIR__ . '/../datos/sinonimos.tsv',
    preg_replace('/\.txt$/i', '', $ruta) . '.sinonimos.tsv',
]);
$conversador = new Conversador($libro, $sinonimos);
$conversador->conEstilo($estilo);
$dialogo = new Dialogo($conversador, $usarIa ? new GeneradorClaude() : null);

echo "«{$libro->titulo}», de {$libro->autor} — " . count($libro->pasajes) . " pasajes.\n";
if ($usarIa) {
    echo 'Modo con IA: a la API de Anthropic se envían tu pregunta, los últimos turnos y los pasajes encontrados, '
        . "no el libro entero.\n";
}
if ($conversador->parafrasea()) {
    echo "Estilo {$conversador->estilo()}: /original muestra el texto como lo escribió el autor y /moderno lo cuenta en español de hoy.\n";
}
echo "Escribe tu pregunta (o «salir»).\n";

$historial = [];
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
    $respuesta = $dialogo->responder($pregunta, $historial);
    if (isset($respuesta['aviso'])) {
        echo '(', $respuesta['aviso'], ")\n";
    }
    echo $respuesta['texto'], "\n";
    if ($respuesta['modo'] !== 'reglas' && $pregunta !== '') {
        $historial[] = ['pregunta' => $pregunta, 'respuesta' => $respuesta['texto']];
        $historial = array_slice($historial, -Dialogo::MAX_HISTORIAL);
    }
}
