<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use DefamatoryContentReview\Http\TextMarks;

/**
 * Qué se le marca a un texto (la biografía o el titular de un perfil) para que una persona lo lea con
 * cuidado. Tres fuentes, que se complementan:
 *
 * 1. El filtro de `../defamatory-content-review/` en su modo normal (datos personales como el RUT o un
 *    teléfono, insultos, acusaciones y datos sensibles dichos de OTRA persona; igual que `sistema-nuevo/`).
 * 2. El mismo filtro en su modo biografía (`bio-topics`): datos sensibles que alguien dice de SÍ MISMO
 *    («tengo cáncer», «soy evangélica»), en español e inglés.
 * 3. {@see Sensibles}: lo que lo anterior no ve, a saber los otros cuatro idiomas (francés, portugués,
 *    italiano, alemán) y las biografías sin verbo («Mapuche. Archivera»). Si el filtro ya marcó una categoría,
 *    la de `Sensibles` para esa misma categoría no se repite.
 *
 * Nunca borra ni cambia el texto: solo devuelve etiquetas.
 */
final class Revision
{
    private const IDIOMAS = ['spa', 'eng'];

    /**
     * Las etiquetas con que el filtro marca cada categoría de {@see Sensibles} (en español y en inglés).
     *
     * @var array<string, list<string>>
     */
    private const ETIQUETAS_DEL_FILTRO = [
        'salud' => ['dato sensible (salud)', 'sensitive data (health)'],
        'religión' => ['dato sensible (religión)', 'sensitive data (religion)'],
        'política o sindical' => ['dato sensible (opinión política)', 'sensitive data (political opinion)'],
        'vida sexual' => ['dato sensible (orientación sexual)', 'sensitive data (sexual orientation)'],
        'origen étnico' => ['dato sensible (origen étnico)', 'sensitive data (ethnic origin)'],
    ];

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
     *         «dato sensible (salud)», «posible dato sensible: salud»…
     */
    public static function marcas(string $texto): array
    {
        $marcas = [];
        if (self::hayFiltro()) {
            // Un checkout sin config/bio-topics/ (otra carpeta en FILTRO_DIFAMACION_DIR) simplemente no marca nada en ese modo.
            $marcas = [
                ...TextMarks::of($texto, self::carpetaConfig(), self::IDIOMAS)['marks'],
                ...TextMarks::of($texto, self::carpetaConfig(), self::IDIOMAS, 'bio-topics')['marks'],
            ];
        }
        foreach (Sensibles::buscar($texto) as $categoria) {
            if (array_intersect(self::ETIQUETAS_DEL_FILTRO[$categoria] ?? [], $marcas) === []) {
                $marcas[] = "posible dato sensible: $categoria";
            }
        }
        return array_values(array_unique($marcas));
    }
}
