<?php

declare(strict_types=1);

/**
 * Pruebas de marketing/render.php con los interruptores (gen.marketing). Sin dependencias: php marketing/pruebas/ejecutar.php
 *
 * Corren render.php como proceso aparte. Los interruptores se copian a una carpeta temporal con el estado que pida cada
 * prueba, así no dependen del var/ del repositorio.
 */

const RAIZ = __DIR__ . '/..';
$fallos = 0;
$pasadas = 0;

function comprobar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallos, $pasadas;
    if ($ok) {
        $pasadas++;
        return;
    }
    $fallos++;
    echo "FALLA: $nombre" . ($detalle !== '' ? " ($detalle)" : '') . "\n";
}

function borrar(string $ruta): void
{
    if (is_file($ruta) || is_link($ruta)) {
        unlink($ruta);
        return;
    }
    foreach (scandir($ruta) ?: [] as $e) {
        if ($e !== '.' && $e !== '..') {
            borrar("$ruta/$e");
        }
    }
    rmdir($ruta);
}

function temporal(): string
{
    $dir = sys_get_temp_dir() . '/marketing-pruebas-' . bin2hex(random_bytes(5));
    mkdir($dir, 0775, true);
    register_shutdown_function(static fn () => is_dir($dir) && borrar($dir));
    return $dir;
}

/** @param array<string, bool> $estado */
function interruptores(array $estado): string
{
    $dir = temporal();
    $origen = RAIZ . '/../interruptores';
    mkdir("$dir/src", 0775, true);
    mkdir("$dir/var", 0775, true);
    copy("$origen/catalogo.json", "$dir/catalogo.json");
    foreach (['Interruptores.php', 'Excepciones.php', 'RegistroJsonl.php'] as $archivo) {
        copy("$origen/src/$archivo", "$dir/src/$archivo");
    }
    file_put_contents("$dir/var/estado.json", json_encode($estado));
    return $dir;
}

/** @param list<string> $argumentos @return array{int, string} */
function correr(string $script, array $argumentos): array
{
    $orden = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($argumentos as $a) {
        $orden .= ' ' . escapeshellarg($a);
    }
    exec($orden . ' 2>&1', $lineas, $codigo);
    return [$codigo, implode("\n", $lineas)];
}

function secciones(string $archivo): int
{
    return preg_match_all('/^## /m', (string) file_get_contents($archivo));
}

/** @return list<string> rutas relativas de los .md de una carpeta de salida */
function archivos(string $dir): array
{
    $r = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $r[] = substr($f->getPathname(), strlen($dir) + 1);
    }
    sort($r);
    return $r;
}

$render = RAIZ . '/render.php';

// Sin la opción, todo sale como siempre.
$base = temporal();
[$codigo] = correr($render, ["--out=$base"]);
comprobar('sin interruptores, render.php escribe los 16 archivos', $codigo === 0 && count(archivos($base)) === 16);

// Todo encendido: igual que sin la opción.
$todo = temporal();
[$codigo] = correr($render, ["--out=$todo", '--interruptores=' . interruptores([])]);
$igual = $codigo === 0;
foreach (archivos($base) as $a) {
    $igual = $igual && is_file("$todo/$a") && file_get_contents("$todo/$a") === file_get_contents("$base/$a");
}
comprobar('con el estado de fábrica, la salida es idéntica a la de sin interruptores', $igual);

// Redes y correo apagados: dos secciones menos en cada idioma.
$sinRedes = temporal();
[$codigo, $texto] = correr($render, ["--out=$sinRedes", '--interruptores=' . interruptores(['gen.marketing:redes' => false, 'gen.marketing:correo' => false])]);
$dosMenos = $codigo === 0;
foreach (archivos($base) as $a) {
    $dosMenos = $dosMenos && secciones("$sinRedes/$a") === secciones("$base/$a") - 2;
}
comprobar('redes y correo apagados: cada archivo de los 16 pierde exactamente 2 secciones', $dosMenos, $texto);
comprobar('y el español ya no trae los hashtags ni los asuntos del correo',
    !str_contains((string) file_get_contents("$sinRedes/frases.md"), '#MiHistoriaFamiliar') && !str_contains((string) file_get_contents("$sinRedes/frases.md"), '### Asuntos'));
comprobar('pero conserva los lemas, el manifiesto y la nota inicial',
    str_contains((string) file_get_contents("$sinRedes/frases.md"), '## Lemas principales') && str_contains((string) file_get_contents("$sinRedes/frases.md"), '## Antes de usar'));

// Lemas y eslóganes apagados: las dos primeras secciones de contenido, con el desfase del español.
$sinLemas = temporal();
[$codigo] = correr($render, ["--out=$sinLemas", '--interruptores=' . interruptores(['gen.marketing:lemas' => false, 'gen.marketing:esloganes' => false])]);
$es = (string) file_get_contents("$sinLemas/frases.md");
$en = (string) file_get_contents("$sinLemas/idiomas/frases.en.md");
comprobar('lemas y eslóganes apagados: salen de español e inglés, que tienen títulos distintos',
    $codigo === 0 && !str_contains($es, '## Lemas principales') && !str_contains($es, '## Eslóganes para el logo') && !str_contains($en, '## Main taglines') && !str_contains($en, '## Logo slogans'));
comprobar('y se quedan el manifiesto y las redes', str_contains($es, '## Texto largo (manifiesto)') && str_contains($en, '## Social media'));

// Función apagada: no se escribe nada.
$nada = temporal() . '/salida';
[$codigo, $texto] = correr($render, ["--out=$nada", '--interruptores=' . interruptores(['gen.marketing' => false])]);
comprobar('con la función apagada no se escribe ningún texto', $codigo === 0 && !is_dir($nada) && str_contains($texto, 'apagado'));

// Interruptores ilegibles: error, y sin escribir nada.
$sinEscribir = temporal() . '/salida';
[$codigo, $texto] = correr($render, ["--out=$sinEscribir", '--interruptores=' . temporal() . '/no-existe']);
comprobar('interruptores ilegibles: código 1 y nada escrito', $codigo === 1 && !is_dir($sinEscribir) && str_contains($texto, 'interruptores'), $texto);

// --check con interruptores no escribe.
$check = temporal() . '/salida';
[$codigo] = correr($render, ['--check', "--out=$check", '--interruptores=' . interruptores([])]);
comprobar('--check con interruptores valida y no escribe', $codigo === 0 && !is_dir($check));

// Un archivo con otra estructura no se adivina.
$copia = temporal() . '/marketing';
mkdir("$copia/idiomas", 0775, true);
copy(RAIZ . '/render.php', "$copia/render.php");
copy(RAIZ . '/config.json', "$copia/config.json");
copy(RAIZ . '/frases.md', "$copia/frases.md");
foreach (glob(RAIZ . '/idiomas/frases.*.md') ?: [] as $f) {
    copy($f, "$copia/idiomas/" . basename($f));
}
file_put_contents("$copia/idiomas/frases.en.md", "# Roto\n\n## Una\n\n## Dos\n");
[$codigo, $texto] = correr("$copia/render.php", ["--out=$copia/dist", '--interruptores=' . interruptores(['gen.marketing:redes' => false])]);
comprobar('un archivo con otra estructura es un error claro, no una suposición', $codigo === 1 && str_contains($texto, 'frases.en.md') && str_contains($texto, 'no se sabe qué quitar'), $texto);

echo "$pasadas pruebas pasaron, $fallos fallaron.\n";
exit($fallos === 0 ? 0 : 1);
