<?php

declare(strict_types=1);

// Genera FUNCIONES.md desde catalogo.json.
//   php bin/generar.php              escribe FUNCIONES.md
//   php bin/generar.php --comprobar  falla (código 1) si FUNCIONES.md no está al día

require_once __DIR__ . '/../src/Interruptores.php';

use LolProtocol\Interruptores\Interruptores;

$raiz = dirname(__DIR__);
$comprobar = in_array('--comprobar', $argv, true);

$cat = new Interruptores($raiz . '/catalogo.json', $raiz . '/var/estado.json');
$crudo = json_decode((string) file_get_contents($raiz . '/catalogo.json'), true, 512, JSON_THROW_ON_ERROR);

$md = generarMarkdown($cat->catalogo(), $cat->plataformas(), $crudo['tipos'], $crudo['fuera_del_interruptor']);
$destino = $raiz . '/FUNCIONES.md';

if ($comprobar) {
    $actual = is_file($destino) ? (string) file_get_contents($destino) : '';
    if ($actual !== $md) {
        fwrite(STDERR, "FUNCIONES.md no está al día: corre `php interruptores/bin/generar.php`.\n");
        exit(1);
    }
    echo "FUNCIONES.md al día (" . count($cat->catalogo()) . " funciones).\n";
    exit(0);
}

file_put_contents($destino, $md);
echo "Escrito FUNCIONES.md (" . count($cat->catalogo()) . " funciones).\n";

/**
 * @param array<string, array<string, mixed>> $funciones
 * @param array<string, string> $plataformas
 * @param array<string, string> $tipos
 * @param list<array{carpeta: string, motivo: string}> $fuera
 */
function generarMarkdown(array $funciones, array $plataformas, array $tipos, array $fuera): string
{
    $o = "# Listado de funciones por plataforma\n\n";
    $o .= "> Generado desde [`catalogo.json`](catalogo.json) con `php interruptores/bin/generar.php`. **No lo edites a mano**: cambia el catálogo y vuelve a generar.\n\n";
    $o .= "Cada fila es un interruptor del panel. «Por defecto» dice si arranca encendida; «Depende de» son las funciones que tienen que estar encendidas para que esta funcione.\n\n";

    $total = count($funciones);
    $o .= "Total: **$total funciones** en " . count($plataformas) . " plataformas.\n\n";

    $o .= "## Tipos\n\n| Tipo | Significado |\n|---|---|\n";
    foreach ($tipos as $clave => $texto) {
        $o .= "| `$clave` | $texto |\n";
    }
    $o .= "\n";

    foreach ($plataformas as $clave => $titulo) {
        $grupo = array_filter($funciones, static fn (array $f): bool => $f['plataforma'] === $clave);
        $o .= "## $titulo\n\n";
        $o .= "| Id | Función | Carpeta | Tipo | Por defecto | Depende de | Qué hace |\n";
        $o .= "|---|---|---|---|---|---|---|\n";
        foreach ($grupo as $id => $f) {
            $dep = $f['depende_de'] === [] ? '—' : implode(', ', array_map(static fn (string $d): string => "`$d`", $f['depende_de']));
            $defecto = $f['por_defecto'] ? 'encendida' : 'apagada';
            $desc = (string) $f['descripcion'];
            if (isset($f['nota'])) {
                $desc .= ' *Nota:* ' . (string) $f['nota'];
            }
            $o .= sprintf(
                "| `%s` | %s | `%s` | %s | %s | %s | %s |\n",
                $id,
                escapar((string) $f['nombre']),
                (string) $f['carpeta'],
                (string) $f['tipo'],
                $defecto,
                $dep,
                escapar($desc),
            );
        }
        $o .= "\n";
    }

    $o .= "## Fuera del interruptor\n\n";
    $o .= "Carpetas del repo que no son una función de ninguna de las dos plataformas, o que el interruptor no debe tocar.\n\n";
    $o .= "| Carpeta | Motivo |\n|---|---|\n";
    foreach ($fuera as $f) {
        $o .= "| `{$f['carpeta']}` | " . escapar($f['motivo']) . " |\n";
    }
    return $o;
}

function escapar(string $texto): string
{
    return str_replace('|', '\|', $texto);
}
