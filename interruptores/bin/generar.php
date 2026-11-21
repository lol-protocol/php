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

$md = generarMarkdown($crudo, $cat->catalogo(), $cat->totalInterruptores());
$destino = $raiz . '/FUNCIONES.md';

if ($comprobar) {
    $actual = is_file($destino) ? (string) file_get_contents($destino) : '';
    if ($actual !== $md) {
        fwrite(STDERR, "FUNCIONES.md no está al día: corre `php interruptores/bin/generar.php`.\n");
        exit(1);
    }
    echo 'FUNCIONES.md al día (' . count($cat->catalogo()) . " funciones, {$cat->totalInterruptores()} interruptores).\n";
    exit(0);
}

file_put_contents($destino, $md);
echo 'Escrito FUNCIONES.md (' . count($cat->catalogo()) . " funciones, {$cat->totalInterruptores()} interruptores).\n";

/**
 * @param array<string, mixed> $crudo contenido de catalogo.json
 * @param array<string, array<string, mixed>> $funciones
 */
function generarMarkdown(array $crudo, array $funciones, int $totalInterruptores): string
{
    $plataformas = $crudo['plataformas'];
    $o = "# Listado de funciones por plataforma\n\n";
    $o .= "> Generado desde [`catalogo.json`](catalogo.json) con `php interruptores/bin/generar.php`. **No lo edites a mano**: cambia el catálogo y vuelve a generar.\n\n";

    $opciones = 0;
    foreach ($funciones as $f) {
        $opciones += count($f['opciones']);
    }
    $supuestas = count(array_filter($funciones, static fn ($f) => $f['supuesto']));
    $o .= '- **' . count($funciones) . ' funciones** (' . count(array_filter($funciones, static fn ($f) => $f['plataforma'] === 'genealogia')) . ' de genealogía y ' . count(array_filter($funciones, static fn ($f) => $f['plataforma'] === 'pos')) . " de POS).\n";
    $o .= "- **$opciones opciones** (sub-interruptores), y **$totalInterruptores interruptores** en total.\n";
    $o .= "- **$supuestas funciones supuestas**, marcadas con *(supuesto)*: no vienen de la lista de la persona, las propuse a partir de sus respuestas. Hay que confirmarlas o quitarlas.\n\n";
    $o .= "Cada función tiene su interruptor. Si tiene opciones, cada una tiene el suyo: una opción está encendida solo si su función también lo está. «Mínimo de opciones» dice cuántas tienen que quedar encendidas mientras la función esté encendida.\n\n";

    $o .= "## Tipos\n\n| Tipo | Significado |\n|---|---|\n";
    foreach ($crudo['tipos'] as $clave => $texto) {
        $o .= "| `$clave` | " . escapar($texto) . " |\n";
    }
    $o .= "\n## Orígenes\n\n| Origen | Significado |\n|---|---|\n";
    foreach ($crudo['origenes'] as $clave => $texto) {
        $o .= "| `$clave` | " . escapar($texto) . " |\n";
    }
    $o .= "\n";

    foreach ($plataformas as $clave => $titulo) {
        $grupo = array_filter($funciones, static fn (array $f): bool => $f['plataforma'] === $clave);
        $o .= "## $titulo\n\n";
        $o .= '| Id | Función | Opciones | Tipo | Origen | Por defecto | Depende de |' . "\n";
        $o .= "|---|---|---|---|---|---|---|\n";
        foreach ($grupo as $id => $f) {
            $o .= sprintf(
                "| `%s` | %s | %s | %s | `%s` | %s | %s |\n",
                $id,
                escapar((string) $f['nombre']) . ($f['supuesto'] ? ' *(supuesto)*' : ''),
                count($f['opciones']) === 0 ? '—' : (string) count($f['opciones']) . ($f['min_opciones'] > 0 ? " (mín. {$f['min_opciones']})" : ''),
                (string) $f['tipo'],
                (string) $f['origen'],
                $f['por_defecto'] ? 'encendida' : 'apagada',
                $f['depende_de'] === [] ? '—' : implode(', ', array_map(static fn (string $d): string => "`$d`", $f['depende_de'])),
            );
        }
        $o .= "\n";

        foreach ($grupo as $id => $f) {
            $o .= "### `$id` · " . escapar((string) $f['nombre']) . "\n\n";
            $o .= escapar((string) $f['descripcion']) . "\n\n";
            if (!empty($f['carpetas'])) {
                $o .= 'Carpetas: ' . implode(', ', array_map(static fn (string $c): string => "`$c/`", $f['carpetas'])) . ".\n\n";
            }
            if ($f['al_apagar'] !== 'ocultar') {
                $o .= 'Al apagar: `' . $f['al_apagar'] . "` (se muestra como no disponible en vez de ocultarse).\n\n";
            }
            if (isset($f['nota'])) {
                $o .= '*Nota:* ' . escapar((string) $f['nota']) . "\n\n";
            }
            if ($f['opciones'] !== []) {
                $o .= "| Opción (ref) | Nombre | Por defecto |\n|---|---|---|\n";
                foreach ($f['opciones'] as $op) {
                    $dep = $op['depende_de'] === [] ? '' : ' (depende de ' . implode(', ', array_map(static fn (string $d): string => "`$d`", $op['depende_de'])) . ')';
                    $o .= sprintf("| `%s:%s` | %s | %s |\n", $id, $op['id'], escapar((string) $op['nombre']) . $dep, $op['por_defecto'] ? 'encendida' : 'apagada');
                }
                $o .= "\n";
            }
        }
    }

    $o .= "## Fuera del interruptor\n\n";
    $o .= "Carpetas del repo que no son una función de ninguna plataforma, o que el interruptor no debe tocar.\n\n";
    $o .= "| Carpeta | Motivo |\n|---|---|\n";
    foreach ($crudo['fuera_del_interruptor'] as $f) {
        $o .= "| `{$f['carpeta']}/` | " . escapar($f['motivo']) . " |\n";
    }
    return $o;
}

function escapar(string $texto): string
{
    return str_replace('|', '\|', $texto);
}
