<?php

namespace Marcalibros;

/**
 * Las imágenes de paisaje de la marca que el usuario puede elegir como base.
 *
 * Solo se acepta un nombre que esté en la lista que lee la carpeta. Un nombre
 * con rutas ("../", barras) o con otra mayúscula no coincide con ninguno y
 * se rechaza, sin tocar el disco.
 */
final class LandscapeLibrary
{
    /** @var list<string> */
    private array $names = [];

    public function __construct(private readonly string $directory)
    {
        $found = glob(rtrim($directory, '/') . '/paisaje-*.jpg');
        foreach ($found === false ? [] : $found as $file) {
            $name = basename($file);
            if (preg_match('/^paisaje-[a-z]+-\d+x\d+\.jpg\z/', $name) === 1) {
                $this->names[] = $name;
            }
        }
        sort($this->names);
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }

    /** @throws BookmarkRejectedException si el nombre no está en la biblioteca */
    public function path(string $name): string
    {
        if (!in_array($name, $this->names, true)) {
            throw new BookmarkRejectedException(RejectionReason::UnknownLibraryImage);
        }
        return rtrim($this->directory, '/') . '/' . $name;
    }
}
