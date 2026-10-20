<?php

namespace MarcalibrosApp;

/**
 * Copia de seguridad de los marcalibros creados: un .tar.gz fechado con cada marcalibro completo
 * (sus datos y sus dos imágenes) y nada más. No incluye los registros de intentos, donde vive la
 * clave secreta del límite de intentos: para restaurar no hace falta.
 *
 * Solo se conservan las últimas N copias. Los archivos de la carpeta que no tengan el nombre
 * de una copia nunca se tocan.
 */
final class Backup
{
    private const NAME_PATTERN = '/^marcalibros-\d{8}-\d{6}\.tar\.gz\z/';

    public function __construct(
        private readonly string $sourceDirectory,
        private readonly string $targetDirectory,
        private readonly int $keep,
    ) {
        if ($keep < 1) {
            throw new \InvalidArgumentException('Hay que conservar al menos una copia.');
        }
    }

    /**
     * Crea la copia. Devuelve null si no hay marcalibros que copiar (no se escribe un archivo vacío).
     *
     * @return array{file: string, bookmarks: int, bytes: int}|null
     */
    public function create(int $now): ?array
    {
        foreach (['phar', 'zlib'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new \RuntimeException("Falta la extensión de PHP $extension para crear la copia.");
            }
        }
        $complete = $this->completeBookmarks();
        if ($complete === []) {
            return null;
        }
        if (!is_dir($this->targetDirectory) && !@mkdir($this->targetDirectory, 0700, true) && !is_dir($this->targetDirectory)) {
            throw new \RuntimeException('No se pudo crear la carpeta de copias: ' . $this->targetDirectory);
        }

        $final = $this->targetDirectory . '/marcalibros-' . gmdate('Ymd-His', $now) . '.tar.gz';
        $tar = $this->targetDirectory . '/.tmp-' . bin2hex(random_bytes(6)) . '.tar';
        $gz = $tar . '.gz';
        try {
            $archive = new \PharData($tar);
            $copied = 0;
            foreach ($complete as $id) {
                $present = true;
                foreach ([".json", ".jpg", "@2x.jpg"] as $suffix) {
                    $present = $present && is_file("$this->sourceDirectory/$id$suffix");
                }
                if (!$present) {
                    continue;   // lo borró la limpieza mientras se copiaba
                }
                foreach ([".json", ".jpg", "@2x.jpg"] as $suffix) {
                    $archive->addFile("$this->sourceDirectory/$id$suffix", "marcalibros/$id$suffix");
                }
                $copied++;
            }
            if ($copied === 0) {
                return null;
            }
            $archive->compress(\Phar::GZ);
            unset($archive);
            @chmod($gz, 0600);
            if (!rename($gz, $final)) {
                throw new \RuntimeException('No se pudo guardar la copia: ' . $final);
            }
        } finally {
            unset($archive);
            foreach ([$tar, $gz] as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }
        }
        return ['file' => $final, 'bookmarks' => $copied, 'bytes' => (int) filesize($final)];
    }

    /**
     * Borra las copias más viejas y deja las últimas $keep.
     *
     * @return int Copias borradas.
     */
    public function rotate(): int
    {
        $copies = $this->copies();
        $deleted = 0;
        foreach (array_slice($copies, 0, max(0, count($copies) - $this->keep)) as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Las copias que hay, de la más vieja a la más nueva (el nombre lleva la fecha).
     *
     * @return list<string>
     */
    public function copies(): array
    {
        $names = [];
        foreach (@scandir($this->targetDirectory) ?: [] as $name) {
            if (preg_match(self::NAME_PATTERN, $name) === 1) {
                $names[] = $name;
            }
        }
        sort($names);
        return array_map(fn (string $name): string => "$this->targetDirectory/$name", $names);
    }

    /**
     * Identificadores con sus datos y sus dos imágenes. Una imagen sin datos es un marcalibro a medio
     * crear o ya borrado: no se copia.
     *
     * @return list<string>
     */
    private function completeBookmarks(): array
    {
        $ids = [];
        foreach (glob("$this->sourceDirectory/mp-*.json") ?: [] as $file) {
            $id = basename($file, '.json');
            if (BookmarkStore::isValidId($id) && is_file("$this->sourceDirectory/$id.jpg") && is_file("$this->sourceDirectory/$id@2x.jpg")) {
                $ids[] = $id;
            }
        }
        sort($ids);
        return $ids;
    }
}
