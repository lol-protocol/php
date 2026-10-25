<?php

declare(strict_types=1);

/**
 * Valida revisiones.csv, el registro de quién ha revisado qué (mismo formato y reglas que el de
 * Listado-colores/, con las citas en lugar de los colores).
 *
 *   php citas-pos/bin/comprobar-revisiones.php            # valida citas-pos/revisiones.csv
 *   php citas-pos/bin/comprobar-revisiones.php otro.csv   # valida otro archivo (para probar)
 *
 * Columnas: idioma, alcance, elemento, veredicto, revisor, fecha, nota. Ver REVISIONES.md.
 */

const COLUMNAS = ['idioma', 'alcance', 'elemento', 'veredicto', 'revisor', 'fecha', 'nota'];
const ALCANCES = ['cita', 'traduccion', 'etiquetas'];
const VEREDICTOS = ['correcto', 'corregir', 'corregido', 'dudoso'];

/** @return list<string> ids de las frases de datos/ejemplos.json y de las citas de datos/citas.json, si existe */
function idsConocidos(string $base, string $alcance): array
{
    $ids = [];
    // Los ids de las citas (aún no existen) y de las frases de prueba (solo se pueden revisar sus etiquetas).
    if (is_file($base . 'citas.json')) {
        $citas = json_decode((string) file_get_contents($base . 'citas.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($citas['citas'] ?? [] as $c) {
            $ids[] = (string) $c['id'];
        }
    }
    if ($alcance === 'etiquetas') {
        $ej = json_decode((string) file_get_contents($base . 'ejemplos.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($ej['ejemplos'] as $e) {
            foreach ($e['frases'] as $f) {
                $ids[] = (string) $f['id'];
            }
        }
    }
    return $ids;
}

function main(array $argv): int
{
    $base = __DIR__ . '/../datos/';
    $ruta = $argv[1] ?? __DIR__ . '/../revisiones.csv';
    $idiomas = array_column(json_decode((string) file_get_contents($base . 'idiomas.json'), true, 512, JSON_THROW_ON_ERROR)['idiomas'], 'codigo');

    $fh = fopen($ruta, 'rb');
    if ($fh === false) {
        fwrite(STDERR, "No se pudo abrir $ruta\n");
        return 1;
    }
    $cabecera = fgetcsv($fh, 0, ',', '"', '');
    if ($cabecera !== COLUMNAS) {
        fwrite(STDERR, 'Cabecera incorrecta; debe ser: ' . implode(',', COLUMNAS) . "\n");
        return 1;
    }

    $errores = [];
    $vistos = [];
    $n = 1;
    while (($fila = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        $n++;
        if ($fila === [null]) {
            continue;
        }
        if (count($fila) !== count(COLUMNAS)) {
            $errores[] = "fila $n: tiene " . count($fila) . ' columnas, deben ser ' . count(COLUMNAS) . '.';
            continue;
        }
        [$idioma, $alcance, $elemento, $veredicto, $revisor, $fecha, $nota] = $fila;
        if (!in_array($idioma, $idiomas, true)) {
            $errores[] = "fila $n: idioma desconocido «{$idioma}» (código de 3 letras, ver datos/idiomas.json).";
        }
        if (!in_array($alcance, ALCANCES, true)) {
            $errores[] = "fila $n: alcance desconocido «{$alcance}» (debe ser " . implode(', ', ALCANCES) . ').';
        } elseif (!in_array($elemento, idsConocidos($base, $alcance), true)) {
            $errores[] = "fila $n: el elemento «{$elemento}» no existe para el alcance $alcance.";
        } elseif (!str_starts_with($elemento, $idioma . '-')) {
            $errores[] = "fila $n: el elemento «{$elemento}» no es del idioma $idioma (los ids empiezan por el código del idioma).";
        }
        if (!in_array($veredicto, VEREDICTOS, true)) {
            $errores[] = "fila $n: veredicto desconocido «{$veredicto}» (debe ser " . implode(', ', VEREDICTOS) . ').';
        }
        if (trim($revisor) === '') {
            $errores[] = "fila $n: falta el revisor.";
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($d === false || $d->format('Y-m-d') !== $fecha) {
            $errores[] = "fila $n: la fecha «{$fecha}» no es AAAA-MM-DD.";
        }
        if (in_array($veredicto, ['corregir', 'dudoso'], true) && trim($nota) === '') {
            $errores[] = "fila $n: con «{$veredicto}» la nota es obligatoria.";
        }
        $clave = implode('|', [$idioma, $alcance, $elemento, $revisor]);
        if (isset($vistos[$clave])) {
            $errores[] = "fila $n: ya hay una fila de $revisor para $idioma/$alcance/$elemento (fila {$vistos[$clave]}); edita esa en lugar de repetirla.";
        }
        $vistos[$clave] = $n;
    }
    fclose($fh);

    foreach ($errores as $e) {
        echo "ERROR  $e\n";
    }
    $filas = $n - 1;
    echo $errores === [] ? "Registro correcto ($filas fila(s) de revisión).\n" : count($errores) . " error(es).\n";
    return $errores === [] ? 0 : 1;
}

exit(main($argv));
