<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use DefamatoryContentReview\Http\TextMarks;

/**
 * Qué se le marca a un texto (la biografía o el titular de un perfil) para que una persona lo lea con
 * cuidado: lo que encuentra el filtro de `../defamatory-content-review/` (datos personales como el RUT
 * o un teléfono, insultos, acusaciones; igual que `sistema-nuevo/`) más las categorías de {@see Sensibles}.
 * Nunca borra ni cambia el texto: solo devuelve etiquetas.
 */
final class Revision
{
    private const IDIOMAS = ['spa', 'eng'];

    /** La carpeta de configuración del filtro (o la de FILTRO_DIFAMACION_DIR, como en `sistema-nuevo/`). */
    public static function carpetaConfig(): string
    {
        $dir = getenv('FILTRO_DIFAMACION_DIR');
        return ($dir !== false && $dir !== '' ? $dir : __DIR__ . '/../../defamatory-content-review') . '/config';
    }

    /** Si está el filtro de difamación donde se lo busca; sin él, solo se marcan las categorías de `Sensibles`. */
    public static function hayFiltro(): bool
    {
        return is_dir(self::carpetaConfig());
    }

    /**
     * @return list<string> etiquetas sin repetir: «dato personal (RUT)», «acusación de delito», «insulto»,
     *         «posible dato sensible: salud»…
     */
    public static function marcas(string $texto): array
    {
        $marcas = [];
        if (self::hayFiltro()) {
            $marcas = TextMarks::of($texto, self::carpetaConfig(), self::IDIOMAS)['marks'];
        }
        foreach (Sensibles::buscar($texto) as $categoria) {
            $marcas[] = "posible dato sensible: $categoria";
        }
        return array_values(array_unique($marcas));
    }
}
