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
 * La fecha no puede ser posterior a «hoy»: CITAS_POS_HOY=AAAA-MM-DD lo fija.
 */

require_once __DIR__ . '/lib/comun.php';

const COLUMNAS = ['idioma', 'alcance', 'elemento', 'veredicto', 'revisor', 'fecha', 'nota'];
const ALCANCES = ['cita', 'traduccion', 'etiquetas'];
const VEREDICTOS = ['correcto', 'corregir', 'corregido', 'dudoso'];

/**
 * Los elementos que se pueden revisar con cada alcance: los que existen y ya tienen lo que ese alcance revisa.
 * «cita» y «traduccion» piden que la cita tenga texto; «etiquetas», que tenga etiquetas (las frases de prueba de
 * datos/ejemplos.json también se pueden revisar, solo en este alcance).
 *
 * @return array{existen: array<string, array<string, true>>, revisables: array<string, array<string, true>>}
 */
function elementos(): array
{
    $citas = is_file(DATOS . '/citas.json') ? (leerJson(DATOS . '/citas.json')['citas'] ?? []) : [];
    $existenCitas = $conTexto = $conEtiquetas = [];
    foreach ($citas as $c) {
        $id = (string) $c['id'];
        $existenCitas[$id] = true;
        if (textoNoVacio($c['texto'] ?? null)) {
            $conTexto[$id] = true;
        }
        if (is_array($c['etiquetas'] ?? null)) {
            $conEtiquetas[$id] = true;
        }
    }
    $frases = [];
    foreach (leerJson(DATOS . '/ejemplos.json')['ejemplos'] as $e) {
        foreach ($e['frases'] as $f) {
            $frases[(string) $f['id']] = true;
        }
    }
    return [
        'existen' => ['cita' => $existenCitas, 'traduccion' => $existenCitas, 'etiquetas' => $existenCitas + $frases],
        'revisables' => ['cita' => $conTexto, 'traduccion' => $conTexto, 'etiquetas' => $conEtiquetas + $frases],
    ];
}

function main(array $argv): int
{
    try {
        hoy(); // una CITAS_POS_HOY inválida se avisa aunque no haya ninguna fecha que comprobar
        $idiomas = array_column(leerJson(DATOS . '/idiomas.json')['idiomas'], 'codigo');
        $elementos = elementos();
        $registro = leerRevisiones($argv[1] ?? __DIR__ . '/../revisiones.csv');
    } catch (RuntimeException $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        return 1;
    }
    if ($registro['cabecera'] !== COLUMNAS) {
        fwrite(STDERR, 'Cabecera incorrecta; debe ser: ' . implode(',', COLUMNAS) . "\n");
        return 1;
    }

    $errores = [];
    $vistos = [];
    foreach ($registro['filas'] as [$n, $fila]) {
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
        } elseif (!isset($elementos['existen'][$alcance][$elemento])) {
            $errores[] = "fila $n: el elemento «{$elemento}» no existe para el alcance $alcance.";
        } elseif (!isset($elementos['revisables'][$alcance][$elemento])) {
            $errores[] = "fila $n: el elemento «{$elemento}» aún no tiene " . ($alcance === 'etiquetas' ? 'etiquetas' : 'texto') . " que revisar con el alcance $alcance.";
        } elseif (!str_starts_with($elemento, $idioma . '-')) {
            $errores[] = "fila $n: el elemento «{$elemento}» no es del idioma $idioma (los ids empiezan por el código del idioma).";
        }
        if (!in_array($veredicto, VEREDICTOS, true)) {
            $errores[] = "fila $n: veredicto desconocido «{$veredicto}» (debe ser " . implode(', ', VEREDICTOS) . ').';
        }
        if (trim($revisor) === '') {
            $errores[] = "fila $n: falta el revisor.";
        }
        if (!fechaValida($fecha)) {
            $errores[] = "fila $n: la fecha «{$fecha}» no es AAAA-MM-DD.";
        } elseif (!fechaNoFutura($fecha)) {
            $errores[] = "fila $n: la fecha «{$fecha}» es posterior a hoy.";
        }
        if (in_array($veredicto, VEREDICTOS_QUE_BLOQUEAN, true) && trim($nota) === '') {
            $errores[] = "fila $n: con «{$veredicto}» la nota es obligatoria.";
        }
        $clave = implode('|', [$idioma, $alcance, $elemento, $revisor]);
        if (isset($vistos[$clave])) {
            $errores[] = "fila $n: ya hay una fila de $revisor para $idioma/$alcance/$elemento (fila {$vistos[$clave]}); edita esa en lugar de repetirla.";
        }
        $vistos[$clave] = $n;
    }

    foreach ($errores as $e) {
        echo "ERROR  $e\n";
    }
    $filas = count($registro['filas']);
    echo $errores === [] ? "Registro correcto ($filas fila(s) de revisión).\n" : count($errores) . " error(es).\n";
    return $errores === [] ? 0 : 1;
}

exit(main($argv));
