<?php

namespace Marcalibros;

/**
 * Por qué se rechazó una imagen, una etiqueta o una elección del usuario.
 * El valor es estable para usarlo en respuestas de API; userMessage() es el
 * texto para mostrar en la interfaz.
 */
enum RejectionReason: string
{
    case Unreadable = 'unreadable';
    case EmptyFile = 'empty_file';
    case TooLarge = 'too_large';
    case UnsupportedType = 'unsupported_type';
    case NotAnImage = 'not_an_image';
    case TooManyPixels = 'too_many_pixels';
    case TooSmall = 'too_small';
    case Undecodable = 'undecodable';
    case InvalidCrop = 'invalid_crop';
    case InvalidLabel = 'invalid_label';
    case UnknownCatalogId = 'unknown_catalog_id';
    case UnknownLibraryImage = 'unknown_library_image';
    case StorageFailed = 'storage_failed';

    public function userMessage(): string
    {
        return match ($this) {
            self::Unreadable => 'No se pudo leer el archivo.',
            self::EmptyFile => 'El archivo está vacío.',
            self::TooLarge => 'El archivo es demasiado grande.',
            self::UnsupportedType => 'Solo se aceptan imágenes JPG, PNG o WebP.',
            self::NotAnImage => 'El archivo no es una imagen válida.',
            self::TooManyPixels => 'La imagen tiene demasiados píxeles. Usa una más pequeña.',
            self::TooSmall => 'La imagen es demasiado pequeña para un marcalibro.',
            self::Undecodable => 'No se pudo procesar la imagen.',
            self::InvalidCrop => 'El recorte elegido no es válido.',
            self::InvalidLabel => 'El texto del marcalibro no es válido.',
            self::UnknownCatalogId => 'Ese marcalibro no existe en el catálogo.',
            self::UnknownLibraryImage => 'Esa imagen no existe en la biblioteca.',
            self::StorageFailed => 'No se pudo guardar el marcalibro. Inténtalo de nuevo.',
        };
    }
}
