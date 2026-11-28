<?php
declare(strict_types=1);

/*
 * Arma ejemplos/quijote.txt (el formato de este proyecto) a partir del texto de Project Gutenberg
 * (eBook #2000, «Don Quijote», de Miguel de Cervantes Saavedra, dominio público):
 *
 *   php bin/preparar-quijote.php pg2000.txt                  # escribe ejemplos/quijote.txt
 *   php bin/preparar-quijote.php pg2000.txt otra/ruta.txt    # lo escribe en otra parte
 *   php bin/preparar-quijote.php --comprobar pg2000.txt      # sale con 1 si ejemplos/quijote.txt no coincide
 *
 * El texto de Gutenberg se baja de https://www.gutenberg.org/ebooks/2000 (la versión «Plain Text
 * UTF-8»). Se queda con los 126 capítulos (52 de la primera parte y 74 de la segunda), con el
 * nombre «Parte I, cap. VIII»: las dos partes numeran sus capítulos desde el I. Quita el
 * encabezado y la licencia de Gutenberg y los preliminares (tasa, aprobaciones, prólogos y versos).
 */

$fuenteSha256 = '392270e534c66988d6e949ba0f9a64117969fde0f2aebbc6efef3314e23e4385';
// Ojo: Gutenberg corrige erratas de vez en cuando; si cambia, esto avisa y sigue.

$comprobar = false;
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--comprobar') {
        $comprobar = true;
    } else {
        $args[] = $a;
    }
}
[$entrada, $salida] = [$args[0] ?? null, $args[1] ?? __DIR__ . '/../ejemplos/quijote.txt'];
if ($entrada === null || !is_file($entrada)) {
    fwrite(STDERR, "uso: php bin/preparar-quijote.php [--comprobar] pg2000.txt [salida.txt]\n");
    exit(2);
}

$bruto = (string) file_get_contents($entrada);
if (hash('sha256', $bruto) !== $fuenteSha256) {
    fwrite(STDERR, "aviso: $entrada no es el archivo con el que se armó ejemplos/quijote.txt (sha256 distinto); revisa la diferencia.\n");
}

$texto = str_replace("\r\n", "\n", $bruto);
if (str_starts_with($texto, "\xEF\xBB\xBF")) {
    $texto = substr($texto, 3);
}
$ini = strpos($texto, '*** START OF THE PROJECT GUTENBERG EBOOK');
$fin = strpos($texto, '*** END OF THE PROJECT GUTENBERG EBOOK');
if ($ini === false || $fin === false) {
    fwrite(STDERR, "no encuentro las marcas de Project Gutenberg en $entrada\n");
    exit(1);
}
$desde = (int) strpos($texto, "\n", $ini) + 1;
$lineas = explode("\n", substr($texto, $desde, $fin - $desde));

function romano(int $n): string
{
    $r = '';
    foreach ([1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $v => $s) {
        while ($n >= $v) {
            $r .= $s;
            $n -= $v;
        }
    }
    return $r;
}

// El índice del principio repite los títulos de las partes: el cuerpo de la primera parte empieza en
// su segunda aparición de «Primera parte del ingenioso hidalgo…» y el de la segunda, en la segunda
// de «Segunda parte del ingenioso caballero…». Las «Segunda/Tercera/Cuarta parte del ingenioso
// hidalgo…» de dentro de la primera son sus divisiones de 1605 y no cambian la numeración.
$parte = 0;
$caballero = 0;
$dentro = false;
$numero = 0;
$capitulos = 0;
$salidaLineas = ['Título: El ingenioso hidalgo don Quijote de la Mancha', 'Autor: Miguel de Cervantes Saavedra', ''];

for ($i = 0, $n = count($lineas); $i < $n; $i++) {
    $t = trim($lineas[$i]);
    if (str_starts_with($t, 'Primera parte del ingenioso hidalgo')) {
        [$parte, $dentro, $numero] = [1, false, 0];
        continue;
    }
    if (str_starts_with($t, 'Segunda parte del ingenioso caballero')) {
        if (++$caballero === 2) {
            [$parte, $dentro, $numero] = [2, false, 0];
        }
        continue;
    }
    if ($parte === 0 || ($parte === 1 && preg_match('/^(Segunda|Tercera|Cuarta) parte del ingenioso hidalgo/u', $t))) {
        continue;
    }
    if (preg_match('/^Cap\x{00ED}tulo\s+\S+?\.\s*(.*)$/u', $t, $m)) {
        // El título del capítulo sigue en las líneas siguientes hasta la línea en blanco.
        $titulo = $m[1];
        while ($i + 1 < $n && trim($lineas[$i + 1]) !== '') {
            $titulo .= ' ' . trim($lineas[++$i]);
        }
        $numero++;
        $capitulos++;
        $salidaLineas[] = '';
        $salidaLineas[] = '# Parte ' . ($parte === 1 ? 'I' : 'II') . ', cap. ' . romano($numero) . ': ' . trim($titulo);
        $salidaLineas[] = '';
        $dentro = true;
        continue;
    }
    if (!$dentro) {
        continue;
    }
    // Una línea del cuerpo que Libro::desdeTexto tomaría por un encabezado o un metadato.
    $plano = strtr(mb_strtolower($t), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    if (str_starts_with($t, '#') || str_starts_with($plano, 'capitulo ') || str_starts_with($plano, 'titulo:') || str_starts_with($plano, 'autor:')) {
        fwrite(STDERR, 'aviso: la línea ' . ($i + 1) . " parece un encabezado: $t\n");
    }
    $salidaLineas[] = $t;
}

if ($capitulos !== 126) {
    fwrite(STDERR, "esperaba 126 capítulos y encontré $capitulos\n");
    exit(1);
}
// El texto termina con «Fin» y el pie de Gutenberg ya quedó fuera.
$resultado = rtrim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $salidaLineas))) . "\n";

if ($comprobar) {
    $actual = is_file($salida) ? (string) file_get_contents($salida) : '';
    if ($actual !== $resultado) {
        fwrite(STDERR, "$salida no coincide con lo que sale de $entrada: corre «php bin/preparar-quijote.php {$entrada}» y revisa el cambio.\n");
        exit(1);
    }
    echo "$salida al día (126 capítulos).\n";
    exit(0);
}
file_put_contents($salida, $resultado);
echo "Escribí $salida: 126 capítulos, " . number_format(strlen($resultado), 0, ',', '.') . " bytes.\n";
