<?php

namespace MarcalibrosApp;

use Marcalibros\Bookmark;

/**
 * Guarda los datos de cada marcalibro creado, sin base de datos: un archivo
 * JSON junto a sus dos imágenes (mp-XXXXXXXXXXXXXXXX.json, .jpg y @2x.jpg).
 *
 * El identificador de una petición solo se usa para armar una ruta después de
 * comprobar que tiene exactamente la forma que genera el módulo.
 */
final class BookmarkStore
{
    private const ID_PATTERN = '/^mp-[0-9a-f]{16}\z/';

    public function __construct(private readonly string $directory)
    {
    }

    public static function isValidId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    /** Escribe los datos del marcalibro. Si no se puede, borra sus imágenes y falla. */
    public function save(Bookmark $bookmark, int $createdAt): void
    {
        $json = json_encode($bookmark->toArray() + ['created' => $createdAt], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temp = tempnam($this->directory, '.tmp-');
        if ($temp === false
            || file_put_contents($temp, $json) === false
            || !chmod($temp, 0644)
            || !rename($temp, $this->metaPath($bookmark->id))
        ) {
            if ($temp !== false) {
                @unlink($temp);
            }
            $this->removeFiles($bookmark->id);
            throw new \RuntimeException('No se pudo guardar el marcalibro.');
        }
    }

    /**
     * @return array{id: string, source: string, label: ?string, files: array{'1x': string, '2x': string}, width: int, height: int, created: int}|null
     */
    public function find(string $id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $json = @file_get_contents($this->metaPath($id));
        $meta = $json === false ? null : json_decode($json, true);
        if (!is_array($meta) || ($meta['id'] ?? null) !== $id || !is_int($meta['created'] ?? null)) {
            return null;
        }
        $files = $meta['files'] ?? null;
        if (!is_array($files) || ($files['1x'] ?? null) !== "$id.jpg" || ($files['2x'] ?? null) !== "$id@2x.jpg") {
            return null;
        }
        if (!is_file("$this->directory/$id.jpg") || !is_file("$this->directory/$id@2x.jpg")) {
            return null;
        }
        return [
            'id' => $id,
            'source' => is_string($meta['source'] ?? null) ? $meta['source'] : 'upload',
            'label' => is_string($meta['label'] ?? null) ? $meta['label'] : null,
            'files' => ['1x' => $files['1x'], '2x' => $files['2x']],
            'width' => is_int($meta['width'] ?? null) ? $meta['width'] : 1400,
            'height' => is_int($meta['height'] ?? null) ? $meta['height'] : 200,
            'created' => $meta['created'],
        ];
    }

    /** Ruta de la imagen en disco; solo para un marcalibro que exista. */
    public function imagePath(string $id, string $size): ?string
    {
        $bookmark = $this->find($id);
        if ($bookmark === null || !isset($bookmark['files'][$size])) {
            return null;
        }
        return "$this->directory/{$bookmark['files'][$size]}";
    }

    public function delete(string $id): bool
    {
        if ($this->find($id) === null) {
            return false;
        }
        $this->removeFiles($id);
        return true;
    }

    public function count(): int
    {
        return count(glob("$this->directory/mp-*.json") ?: []);
    }

    /**
     * Borra los marcalibros creados hace más de $seconds segundos, y también los
     * archivos que quedaron sueltos (imágenes sin datos o temporales de más de una hora).
     *
     * @return int Marcalibros borrados.
     */
    public function purgeOlderThan(int $seconds, int $now): int
    {
        $deleted = 0;
        foreach (glob("$this->directory/mp-*.json") ?: [] as $file) {
            $id = basename($file, '.json');
            $json = @file_get_contents($file);
            $meta = $json === false ? null : json_decode($json, true);
            $created = is_array($meta) && is_int($meta['created'] ?? null) ? $meta['created'] : 0;
            if (self::isValidId($id) && $created <= $now - $seconds) {
                $this->removeFiles($id);
                $deleted++;
            }
        }
        foreach (glob("$this->directory/mp-*.jpg") ?: [] as $file) {
            $id = explode('@', basename($file, '.jpg'))[0];
            if (self::isValidId($id) && !is_file($this->metaPath($id)) && (int) @filemtime($file) < $now - 3600) {
                @unlink($file);
            }
        }
        foreach (glob("$this->directory/.tmp-*") ?: [] as $file) {
            if ((int) @filemtime($file) < $now - 3600) {
                @unlink($file);
            }
        }
        return $deleted;
    }

    private function metaPath(string $id): string
    {
        return "$this->directory/$id.json";
    }

    private function removeFiles(string $id): void
    {
        foreach (["$id.jpg", "$id@2x.jpg", "$id.json"] as $name) {
            @unlink("$this->directory/$name");
        }
    }
}
