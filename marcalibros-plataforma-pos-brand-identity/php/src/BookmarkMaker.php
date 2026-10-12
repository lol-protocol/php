<?php

namespace Marcalibros;

/**
 * Crea marcalibros propios a partir de una imagen subida o de una imagen de
 * la biblioteca de la marca, y guarda los dos tamaños en una carpeta.
 *
 * No depende de ningún framework: recibe la ruta del archivo (por ejemplo,
 * $_FILES['imagen']['tmp_name'], después de comprobar is_uploaded_file) y
 * devuelve un Bookmark. Cualquier cosa que el usuario no pueda arreglar por
 * su cuenta se lanza como BookmarkRejectedException; los fallos del servidor
 * (permisos, disco) también salen como StorageFailed.
 */
final class BookmarkMaker
{
    public function __construct(
        private readonly string $storageDirectory,
        private readonly ?LandscapeLibrary $library = null,
        private readonly Limits $limits = new Limits(),
    ) {
    }

    public function fromUpload(string $path, CropSettings $crop = new CropSettings(), ?string $label = null): Bookmark
    {
        $label = Label::clean($label);
        $info = (new ImageInspector($this->limits))->inspect($path);

        return $this->make($info, $crop, $label, Bookmark::SOURCE_UPLOAD);
    }

    public function fromLibraryImage(string $name, CropSettings $crop = new CropSettings(), ?string $label = null): Bookmark
    {
        if ($this->library === null) {
            throw new \LogicException('BookmarkMaker se creó sin biblioteca de paisajes.');
        }

        $label = Label::clean($label);
        $info = (new ImageInspector($this->limits))->inspect($this->library->path($name));

        return $this->make($info, $crop, $label, Bookmark::SOURCE_LIBRARY);
    }

    private function make(ImageInfo $info, CropSettings $crop, ?string $label, string $source): Bookmark
    {
        $bytes = (new ImageNormalizer($this->limits))->render($info, $crop);
        [$id, $files] = $this->store($bytes);

        return new Bookmark(
            $id,
            $source,
            $label,
            $files,
            ImageNormalizer::SIZES['1x'][0],
            ImageNormalizer::SIZES['1x'][1],
        );
    }

    /**
     * Guarda cada tamaño en un archivo temporal y lo renombra al final, así
     * nadie lee un archivo a medio escribir. Si algo falla, borra lo que ya
     * había escrito.
     *
     * @param array<string, string> $bytes
     * @return array{string, array<string, string>}
     */
    private function store(array $bytes): array
    {
        $directory = rtrim($this->storageDirectory, '/');
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new BookmarkRejectedException(RejectionReason::StorageFailed);
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $id = 'mp-' . bin2hex(random_bytes(8));
            $files = ['1x' => "$id.jpg", '2x' => "$id@2x.jpg"];
            if (file_exists("$directory/{$files['1x']}")) {
                continue;
            }

            $written = [];
            try {
                foreach ($files as $size => $name) {
                    $temp = tempnam($directory, '.tmp-');
                    if ($temp === false) {
                        throw new BookmarkRejectedException(RejectionReason::StorageFailed);
                    }
                    if (file_put_contents($temp, $bytes[$size]) === false
                        || !chmod($temp, 0644)
                        || !rename($temp, "$directory/$name")
                    ) {
                        @unlink($temp);
                        throw new BookmarkRejectedException(RejectionReason::StorageFailed);
                    }
                    $written[] = "$directory/$name";
                }
            } catch (BookmarkRejectedException $e) {
                foreach ($written as $file) {
                    @unlink($file);
                }
                throw $e;
            }

            return [$id, $files];
        }

        throw new BookmarkRejectedException(RejectionReason::StorageFailed);
    }
}
