<?php

declare(strict_types=1);

namespace App\Support;

/**
 * docs/RUTAS.md, written from config/routes/*.php: every URL each site
 * answers, so the reference can't drift from what the router does
 * (RutasDocumentadasTest fails when it does). Regenerate it with
 * `php bin/rutas.php > docs/RUTAS.md`.
 */
final class TablaDeRutas
{
    private const SITIOS = ['genealogy' => 'Genealogía', 'pos' => 'POS (Contrastocolor)'];

    public static function markdown(string $dirRutas): string
    {
        $md = "# Rutas\n\n"
            . "<!-- Generado por `php bin/rutas.php > docs/RUTAS.md` desde config/routes/*.php. No editar a mano. -->\n\n"
            . "## Gramática: la forma del primer segmento dice qué es\n\n"
            . "| Forma | Regex | Significa |\n|---|---|---|\n"
            . "| vacío | `^$` | inicio |\n"
            . "| `0` | `^0$` | cuenta |\n"
            . "| 1 letra | `^[a-z]$` | función del sistema (carrito, pago, búsqueda) |\n"
            . "| 2 letras | `^[a-z]{2}$` | país (ISO 3166-1); detrás, región y ciudad de 1-3 letras |\n"
            . "| 3 letras | `^[a-z]{3}$` | idioma (ISO 639-2): va en el subdominio, nunca en la ruta |\n"
            . "| 4+ letras | `^[a-z]{4,}$` | texto libre (p. ej. lo que se busca, detrás de `/b/{tipo}/`) |\n"
            . "| solo dígitos | `^\\d+$` | una entidad: la cantidad de dígitos dice el tipo; 1 dígito detrás de algo es una acción |\n"
            . "| con punto | `\\.` | un archivo para buscadores (`robots.txt`, sitemaps) |\n";

        foreach (self::SITIOS as $sitio => $nombre) {
            $md .= "\n" . self::sitio($nombre, require "{$dirRutas}/{$sitio}.php");
        }

        return $md;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sitio(string $nombre, array $config): string
    {
        $md = "## {$nombre}\n\n### Entidades (solo dígitos)\n\n| Dígitos | Tipo | Acciones |\n|---|---|---|\n";
        $porLargo = $config['by_length'] ?? [];
        krsort($porLargo);
        foreach ($porLargo as $largo => $entrada) {
            $md .= "| {$largo} | {$entrada['type']} | " . self::acciones($entrada['actions'] ?? []) . " |\n";
        }

        if (isset($config['place'])) {
            $md .= "\n### Lugares\n\n`/{país}/[{región}/[{ciudad}/]]` · acciones: "
                . self::acciones($config['place']['actions'] ?? []) . "\n";
        }

        $md .= "\n### Rutas reservadas\n\n| Ruta | Qué es | Acciones |\n|---|---|---|\n";
        foreach ($config['reserved'] ?? [] as $segmento => $entrada) {
            $ruta = $segmento === '' ? '/' : "/{$segmento}/";
            $acciones = isset($entrada['ruta'])
                ? "hasta {$entrada['ruta']} segmentos más"
                : self::acciones($entrada['actions'] ?? []);
            $md .= "| `{$ruta}` | " . self::destino($entrada) . " | {$acciones} |\n";
        }

        if (isset($config['archivos'])) {
            $md .= "\n### Archivos\n\n";
            foreach ($config['archivos'] as $archivo => $entrada) {
                $md .= '- `/' . (str_contains($archivo, '.') ? $archivo : "{$archivo}/…") . '` → ' . self::destino($entrada) . "\n";
            }
        }

        if (isset($config['redirect'])) {
            $md .= "\n### Redirecciones (301)\n\n";
            foreach ($config['redirect'] as $desde => $hacia) {
                $md .= "- `/{$desde}/` → `{$hacia}`\n";
            }
        }

        return $md;
    }

    /**
     * @param array<int, string> $acciones
     */
    private static function acciones(array $acciones): string
    {
        if ($acciones === []) {
            return '—';
        }

        return implode(' · ', array_map(static fn(int $c, string $m): string => "{$c} {$m}", array_keys($acciones), $acciones));
    }

    /**
     * @param array<string, mixed> $entrada
     */
    private static function destino(array $entrada): string
    {
        return '`' . $entrada['controller'] . '::' . ($entrada['method'] ?? 'index') . '`';
    }
}
