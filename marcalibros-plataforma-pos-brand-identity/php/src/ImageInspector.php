<?php

namespace Marcalibros;

/**
 * Revisa un archivo subido antes de decodificarlo.
 *
 * El tipo se decide mirando el contenido, nunca la extensión ni el tipo que
 * declara el navegador, porque ambos los controla quien sube el archivo. Se
 * pide que el contenido y la cabecera de imagen digan lo mismo. SVG queda
 * fuera a propósito: puede llevar scripts.
 */
final class ImageInspector
{
    /** Tipos permitidos y el tipo de imagen de PHP que les corresponde. */
    private const TYPES = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    public function __construct(private readonly Limits $limits = new Limits())
    {
    }

    public function inspect(string $path): ImageInfo
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new BookmarkRejectedException(RejectionReason::Unreadable);
        }

        $bytes = filesize($path);
        if ($bytes === false) {
            throw new BookmarkRejectedException(RejectionReason::Unreadable);
        }
        if ($bytes === 0) {
            throw new BookmarkRejectedException(RejectionReason::EmptyFile);
        }
        if ($bytes > $this->limits->maxBytes) {
            throw new BookmarkRejectedException(RejectionReason::TooLarge);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime === false || !isset(self::TYPES[$mime])) {
            throw new BookmarkRejectedException(RejectionReason::UnsupportedType);
        }

        // La cabecera da el tamaño sin decodificar la imagen entera.
        $size = @getimagesize($path);
        if ($size === false) {
            throw new BookmarkRejectedException(RejectionReason::NotAnImage);
        }
        [$width, $height, $type] = $size;
        // Segunda barrera: las dos lecturas parten de los mismos primeros bytes, así que con archivos
        // reales casi nunca difieren y no hay una prueba que lo ejercite. Se deja por si alguna versión
        // de PHP o de libmagic llegara a interpretarlos distinto.
        if ($type !== self::TYPES[$mime]) {
            throw new BookmarkRejectedException(RejectionReason::UnsupportedType);
        }
        if ($width < 1 || $height < 1) {
            throw new BookmarkRejectedException(RejectionReason::NotAnImage);
        }
        if ($width * $height > $this->limits->maxPixels) {
            throw new BookmarkRejectedException(RejectionReason::TooManyPixels);
        }

        return new ImageInfo($path, $mime, $width, $height);
    }
}
