<?php
declare(strict_types=1);

/*
 * Pruebas del núcleo en PHP, sin dependencias:  php pruebas/ejecutar.php
 * Salen con 1 si alguna falla. La versión en Java (java/pruebas/) hace las mismas comprobaciones.
 */

require __DIR__ . '/../src/cargar.php';

use ChatLibros\Conversador;
use ChatLibros\Indice;
use ChatLibros\Libro;
use ChatLibros\Normaliza;

$raiz = dirname(__DIR__);
$fallos = 0;
$total = 0;

function comprobar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallos, $total;
    $total++;
    if ($ok) {
        echo "  ok    $nombre\n";
        return;
    }
    $fallos++;
    echo "  FALLA $nombre" . ($detalle !== '' ? " — $detalle" : '') . "\n";
}

$libro = Libro::desdeArchivo("$raiz/ejemplos/muestra.txt");
$conversador = new Conversador($libro);

echo "Normaliza\n";
comprobar('texto quita acentos y pasa a minúsculas', Normaliza::texto('Él dijo: ¡Mañana, en la CAÑADA!') === 'el dijo: ¡manana, en la canada!');
comprobar('palabras descarta vacías y cortas', Normaliza::palabras('Los sueños de la casa, y a') === ['suenos', 'casa']);
comprobar('raiz quita -es y -s', Normaliza::raiz('reyes') === 'rey' && Normaliza::raiz('relojeros') === 'relojero');
comprobar('raiz deja las palabras cortas', Normaliza::raiz('mes') === 'mes' && Normaliza::raiz('casa') === 'casa');
comprobar('terminos aplica raíz', Normaliza::terminos('Los sueños de los relojeros') === ['sueno', 'relojero']);

echo "Libro\n";
comprobar('lee título y autor', $libro->titulo === 'Las horas de Tomás' && str_starts_with($libro->autor, 'Elena Varela'));
comprobar('cuenta los 10 pasajes', count($libro->pasajes) === 10, (string) count($libro->pasajes));
comprobar('asigna el capítulo a cada pasaje', $libro->pasajes[0]['capitulo'] === 'Capítulo 1: El relojero'
    && $libro->pasajes[5]['capitulo'] === 'Capítulo 2: La carta'
    && $libro->pasajes[9]['capitulo'] === 'Capítulo 3: El puerto');
comprobar('no mete los títulos ni los encabezados como pasajes', !str_contains(implode('|', array_column($libro->pasajes, 'texto')), 'Autor:'));
$sin = Libro::desdeTexto("Un párrafo suelto.\n\nOtro.");
comprobar('sin título ni autor usa valores por defecto', $sin->titulo === Libro::TITULO_POR_DEFECTO && $sin->autor === Libro::AUTOR_POR_DEFECTO);
comprobar('un párrafo de varias líneas se une', Libro::desdeTexto("Línea uno\nlínea dos\n\nFin")->pasajes[0]['texto'] === 'Línea uno línea dos');
comprobar('quita el BOM de UTF-8', Libro::desdeTexto("\xEF\xBB\xBFTítulo: X\n\nHola")->titulo === 'X');

echo "Indice\n";
$terminos = [['gato', 'perro'], ['perro', 'perro', 'casa'], ['mar', 'sal']];
$indice = new Indice($terminos);
$r = $indice->buscar(['perro'], 3);
comprobar('el pasaje con más repeticiones gana', $r[0]['pasaje'] === 1, json_encode($r));
comprobar('un término ausente da puntaje 0', $indice->buscar(['dragon'], 1)[0]['puntaje'] === 0.0);
comprobar('los empates los decide el número de pasaje', (new Indice([['x'], ['x']]))->buscar(['x'], 2)[0]['pasaje'] === 0);
comprobar('puntaje coseno máximo 1 para el mismo pasaje', abs($indice->buscar(['mar', 'sal'], 1)[0]['puntaje'] - 1.0) < 1e-9);

echo "Conversador\n";
comprobar('saluda con el título', str_contains($conversador->responder('hola'), 'Las horas de Tomás'));
comprobar('dice quién lo escribió', str_contains($conversador->responder('¿quién escribió esto?'), 'lo escribió Elena Varela'));
comprobar('dice cómo se llama', $conversador->responder('¿cómo se llama?') === "El libro se llama «Las horas de Tomás».");
comprobar('resume con temas y el inicio', str_contains($conversador->responder('¿de qué trata?'), 'trata sobre: ') && str_contains($conversador->responder('¿de qué trata?'), 'Empieza así'));
comprobar('un tema del libro cita pasajes con su capítulo', str_contains($conversador->responder('¿qué pasa con la carta de la hermana?'), '[Capítulo 2: La carta]'));
comprobar('cita el pasaje sobre la llave', str_contains($conversador->responder('la llave de la torre'), 'llave de la torre'));
comprobar('no inventa lo que no está', str_starts_with($conversador->responder('¿hay dragones?'), 'No encuentro eso'));
comprobar('pregunta vacía', $conversador->responder('   ') === 'Escribe una pregunta sobre el libro.');
comprobar('"qué dice el autor sobre X" busca, no da el autor', str_contains($conversador->responder('qué dice el autor sobre la lluvia'), 'Esto dice el libro'));
// 50 caracteres caen en mitad de la séptima palabra: se corta en el espacio anterior.
comprobar('extracto corta en un espacio y agrega «…»', Conversador::extracto(str_repeat('palabra ', 100), 50)
    === 'palabra palabra palabra palabra palabra palabra…');
comprobar('extracto no toca un texto corto', Conversador::extracto('corto', 50) === 'corto');

echo "Vectores (pruebas/vectores.tsv)\n";
$tsv = file("$raiz/pruebas/vectores.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$indiceLibro = new Indice(array_map(static fn (array $p): array => Normaliza::terminos($p['texto']), $libro->pasajes));
$filas = 0;
foreach ($tsv as $linea) {
    if (str_starts_with($linea, '#')) {
        continue;
    }
    [$consulta, $pasaje, $puntaje] = explode("\t", $linea);
    $mejor = $indiceLibro->buscar(Normaliza::terminos($consulta), 1)[0];
    $filas++;
    comprobar(
        "«{$consulta}» → pasaje {$pasaje}, puntaje {$puntaje}",
        (int) $pasaje === $mejor['pasaje'] && sprintf('%.6f', $mejor['puntaje']) === $puntaje,
        sprintf('obtuve pasaje %d, puntaje %.6f', $mejor['pasaje'], $mejor['puntaje']),
    );
}
comprobar('el archivo tiene consultas', $filas > 0);

echo "\n$total pruebas, $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
