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

    /** Parámetros que solo siguen el rastro de quién hizo clic, en cualquier sitio: no cambian la página. */
    private const RASTREO_GENERAL = ['fbclid', 'igshid', 'gclid', 'mibextid', 'ref_src', 'ref_url'];

    /**
     * Parámetros de rastreo que lo son solo en ese sitio: en otro, `s`, `t` o `ref` pueden elegir la página.
     *
     * @var array<string, list<string>>
     */
    private const RASTREO_POR_SITIO = [
        'facebook.com' => ['ref'],
        'twitter.com' => ['s', 't'],
        'x.com' => ['s', 't'],
        'youtube.com' => ['si'],
        'youtu.be' => ['si'],
    ];

    /**
     * Una clave tal que dos direcciones de la MISMA página den lo mismo: sin `http(s)://`, sin `www.`, `m.`,
     * `mobile.`, `web.` ni `touch.`, sin fragmento (`#about`; el de los enlaces antiguos `#!/usuario` sí cuenta),
     * sin la barra final y sin los parámetros que solo rastrean el clic (`utm_*`, `fbclid`, y `ref` en Facebook,
     * `s` y `t` en X…). Los demás parámetros se conservan, en orden: `profile.php?id=1` y `?id=2` son personas distintas.
     *
     * Por defecto todo va en minúsculas: para una lista de exclusión es el error seguro (excluir de más antes que de
     * menos). Con `$distinguirMayusculas` solo el dominio y el primer tramo de la ruta (el usuario, que no distingue
     * mayúsculas) van en minúsculas, y se respetan los identificadores que sí las distinguen (`watch?v=AbC`,
     * `/p/AbC`): es lo que usa {@see repetida()}, donde confundir dos páginas pierde un perfil en silencio.
     */
    public static function claveUrl(string $url, bool $distinguirMayusculas = false): string
    {
        $url = (string) preg_replace('/#(?![!\/]).*$/s', '', trim($url));
        $url = (string) preg_replace('#^https?://(?:(?:www|m|mobile|web|touch)\.)?#i', '', $url);
        $partes = explode('?', $url, 2);
        $segmentos = explode('/', rtrim($partes[0], '/'));
        $descartables = [...self::RASTREO_GENERAL, ...(self::RASTREO_POR_SITIO[strtolower($segmentos[0])] ?? [])];

        $parametros = [];
        foreach (explode('&', $partes[1] ?? '') as $parametro) {
            $nombre = strtolower(explode('=', $parametro, 2)[0]);
            if ($parametro === '' || str_starts_with($nombre, 'utm_') || in_array($nombre, $descartables, true)) {
                continue;
            }
            $valor = str_contains($parametro, '=') ? '=' . explode('=', $parametro, 2)[1] : '';
            $parametros[] = $distinguirMayusculas ? $nombre . $valor : strtolower($nombre . $valor);
        }
        sort($parametros);

        foreach ($distinguirMayusculas ? [0, 1] : array_keys($segmentos) as $posicion) {
            if (isset($segmentos[$posicion])) {
                $segmentos[$posicion] = strtolower($segmentos[$posicion]);
            }
        }
        return implode('/', $segmentos) . ($parametros === [] ? '' : '?' . implode('&', $parametros));
    }

    /**
     * Si una URL ya se vio (según {@see claveUrl()}, distinguiendo mayúsculas en los identificadores); si no, la anota en `$vistos`. Una URL nula nunca cuenta
     * como repetida: no hay con qué compararla.
     *
     * @param array<string, true> $vistos
     */
    public static function repetida(?string $url, array &$vistos): bool
    {
        if ($url === null) {
            return false;
        }
        $clave = self::claveUrl($url, true);
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
