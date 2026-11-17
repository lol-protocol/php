<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/** Procesar muchas páginas ya guardadas: carpetas enteras y sin repetir la misma URL. */
final class Lote
{
    /** Formato JSON de todo lo que escriben los programas de `bin/`. */
    public const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** Si el argumento es una dirección (`https://…`): los programas de `bin/` no descargan nada. */
    public static function esUrl(string $texto): bool
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $texto) === 1;
    }

    /**
     * Expande carpetas (de forma recursiva) a sus archivos `.html` y `.htm`, en orden.
     *
     * @param list<string> $rutas archivos o carpetas
     * @return list<string>
     */
    public static function archivos(array $rutas): array
    {
        $salida = [];
        foreach ($rutas as $ruta) {
            if (!is_dir($ruta)) {
                $salida[] = $ruta;
                continue;
            }
            $encontrados = [];
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $archivo) {
                if ($archivo instanceof \SplFileInfo && $archivo->isFile()
                    && in_array(strtolower($archivo->getExtension()), ['html', 'htm'], true)) {
                    $encontrados[] = $archivo->getPathname();
                }
            }
            sort($encontrados, SORT_STRING);
            array_push($salida, ...$encontrados);
        }
        return $salida;
    }

    /** Parámetros de la dirección que solo siguen el rastro de quién hizo clic o en qué idioma: no cambian la página. */
    private const PARAMETROS_DE_RASTREO = ['fbclid', 'igshid', 'gclid', 'mibextid', 'ref', 'ref_src', 'ref_url', 'si', 's', 't', 'hl'];

    /**
     * Una clave tal que dos direcciones de la MISMA página den lo mismo: sin mayúsculas, sin `http(s)://`, sin
     * `www.`, `m.`, `mobile.`, `web.` ni `touch.`, sin fragmento (`#about`), sin la barra final y sin los
     * parámetros que solo rastrean el clic (`utm_*`, `fbclid`, `ref`…). Los demás parámetros se conservan, en
     * orden: `profile.php?id=1` y `profile.php?id=2` son personas distintas.
     */
    public static function claveUrl(string $url): string
    {
        $url = (string) preg_replace('/#.*$/s', '', trim($url));
        $url = (string) preg_replace('#^https?://(?:(?:www|m|mobile|web|touch)\.)?#i', '', $url);
        $partes = explode('?', $url, 2);
        $base = $partes[0];
        $consulta = $partes[1] ?? '';
        $parametros = [];
        foreach (explode('&', strtolower($consulta)) as $parametro) {
            $nombre = explode('=', $parametro, 2)[0];
            if ($parametro !== '' && !str_starts_with($nombre, 'utm_') && !in_array($nombre, self::PARAMETROS_DE_RASTREO, true)) {
                $parametros[] = $parametro;
            }
        }
        sort($parametros);
        return rtrim(strtolower($base), '/') . ($parametros === [] ? '' : '?' . implode('&', $parametros));
    }

    /**
     * Si una URL ya se vio (según {@see claveUrl()}); si no, la anota en `$vistos`. Una URL nula nunca cuenta
     * como repetida: no hay con qué compararla.
     *
     * @param array<string, true> $vistos
     */
    public static function repetida(?string $url, array &$vistos): bool
    {
        if ($url === null) {
            return false;
        }
        $clave = self::claveUrl($url);
        if (isset($vistos[$clave])) {
            return true;
        }
        $vistos[$clave] = true;
        return false;
    }

    /**
     * Deja el primer perfil de cada URL (según {@see claveUrl()}). Los que no tienen URL se conservan todos:
     * no hay con qué compararlos.
     *
     * @param list<array<string, mixed>> $perfiles
     * @return array{perfiles: list<array<string, mixed>>, repetidos: int}
     */
    public static function sinRepetidos(array $perfiles): array
    {
        $vistos = [];
        $salida = [];
        $repetidos = 0;
        foreach ($perfiles as $perfil) {
            $url = $perfil['url'] ?? null;
            if (self::repetida(is_string($url) ? $url : null, $vistos)) {
                $repetidos++;
                continue;
            }
            $salida[] = $perfil;
        }
        return ['perfiles' => $salida, 'repetidos' => $repetidos];
    }
}
