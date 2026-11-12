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

    /** La URL sin mayúsculas, sin `http(s)://`, sin `www.` y sin la barra final: dos páginas iguales dan la misma clave. */
    public static function claveUrl(string $url): string
    {
        return rtrim(strtolower((string) preg_replace('#^https?://(www\.)?#i', '', trim($url))), '/');
    }

    /**
     * Deja el primer perfil de cada URL (sin contar mayúsculas, `www.` ni la barra final).
     * Los que no tienen URL se conservan todos: no hay con qué compararlos.
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
            if (is_string($url)) {
                $clave = self::claveUrl($url);
                if (isset($vistos[$clave])) {
                    $repetidos++;
                    continue;
                }
                $vistos[$clave] = true;
            }
            $salida[] = $perfil;
        }
        return ['perfiles' => $salida, 'repetidos' => $repetidos];
    }
}
