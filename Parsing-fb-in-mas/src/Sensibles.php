<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Busca en un texto términos de las categorías que la ley chilena de datos (21.719) trata como
 * sensibles: salud, convicciones religiosas, afiliación política o sindical, vida sexual y origen
 * étnico, en español, inglés, francés, portugués, italiano y alemán.
 *
 * Es una de las tres fuentes de {@see Revision}, y la que cubre lo que las otras no ven.
 * `defamatory-content-review/` reconoce estas categorías cuando se habla de OTRA persona («tiene cáncer»,
 * «era judía») en su modo normal, y lo que alguien dice de SÍ MISMO («tengo cáncer», «soy evangélica») en su
 * modo biografía, pero solo en español e inglés y con la forma «soy… / tengo…». Esta lista agrega los otros
 * cuatro idiomas y las biografías sin verbo («Mapuche. Archivera»). `Revision` no repite aquí una categoría
 * que el filtro ya marcó.
 *
 * Es una lista CORTA y de palabras sueltas: que no marque nada no quiere decir que no haya un dato
 * sensible, y marcar no quiere decir que lo haya («Iglesia» en el nombre de un barrio). Solo sirve
 * para que una persona mire el texto con más cuidado. No reemplaza la revisión humana.
 */
final class Sensibles
{
    /**
     * categoría => palabras (minúsculas y sin tildes; se comparan como palabra completa, o como raíz si
     * terminan en «*»). Español, inglés, francés, portugués, italiano y alemán.
     */
    private const TERMINOS = [
        'salud' => [
            // español
            'cancer', 'tumor', 'vih', 'sida', 'diabetes', 'depresion', 'ansiedad', 'bipolar', 'esquizofrenia',
            'autismo', 'epilepsia', 'discapacidad', 'diagnostic*', 'enfermedad', 'quimioterapia',
            // inglés
            'illness', 'disease', 'disorder', 'diagnosed', 'disability', 'therapy', 'chemotherapy', 'hiv', 'aids',
            // francés
            'maladie', 'handicap', 'handicape*', 'autisme', 'epilepsie', 'schizophrenie', 'chimiotherapie', 'diabete',
            // portugués
            'cancro', 'depressao', 'ansiedade', 'deficiencia', 'doenca', 'esquizofrenia', 'quimioterapia',
            // italiano
            'tumore', 'depressione', 'ansia', 'schizofrenia', 'epilessia', 'disabilita', 'malattia', 'chemioterapia', 'diabete',
            // alemán
            'krebs', 'angststorung', 'schizophrenie', 'autismus', 'behinderung', 'krankheit', 'chemotherapie', 'diagnose',
        ],
        'religión' => [
            'catolic*', 'evangelic*', 'musulman*', 'judio*', 'judia*', 'budista', 'ateo', 'atea', 'testigo de jehova',
            'mormon*', 'islam', 'pastor', 'creyente',
            'christian', 'muslim', 'jewish', 'buddhist', 'atheist',
            'catholique', 'protestant*', 'juif', 'juive', 'bouddhiste', 'athee', 'temoin de jehovah', 'croyant',
            'muculmano', 'judeu', 'ateu', 'testemunha de jeova',
            'cattolic*', 'ebreo', 'ebrea', 'buddista', 'testimone di geova',
            'katholisch*', 'evangelisch*', 'muslim*', 'judisch*', 'buddhist*', 'atheist*', 'zeuge jehovas', 'pfarrer',
        ],
        'política o sindical' => [
            'militante', 'sindicato', 'sindical', 'partido comunista', 'partido socialista',
            'derechista', 'izquierdista', 'comunista', 'socialista',
            'union member', 'political party', 'communist',
            'syndicat', 'syndical*', 'communiste', 'socialiste', 'parti communiste', 'parti socialiste',
            'sindacato', 'sindacalista',
            'gewerkschaft', 'kommunist*', 'sozialist*',
        ],
        'vida sexual' => [
            'gay', 'lesbiana', 'bisexual', 'transgenero', 'homosexual', 'orientacion sexual', 'transgender',
            'sexual orientation', 'lgbt*',
            'homosexuel*', 'lesbienne', 'bisexuel', 'transgenre', 'orientation sexuelle',
            'homossexual', 'lesbica', 'bissexual', 'orientacao sexual',
            'omosessuale', 'bisessuale', 'orientamento sessuale',
            'lesbisch*', 'bisexuell*', 'schwul', 'sexuelle orientierung',
        ],
        'origen étnico' => [
            'mapuche', 'indigena', 'afrodescendiente', 'gitano', 'etnia', 'ethnicity', 'indigenous',
            'autochtone', 'indigene', 'tzigane', 'ethnie',
            'cigano', 'afrodescendente', 'quilombola',
            'indigeno', 'zingaro',
            'indigen*', 'sinti',
        ],
    ];

    /**
     * @return list<string> las categorías que aparecen, en el orden de la lista y sin repetir
     */
    public static function buscar(string $texto): array
    {
        $normal = ' ' . self::normalizar($texto) . ' ';
        $categorias = [];
        foreach (self::TERMINOS as $categoria => $terminos) {
            foreach ($terminos as $termino) {
                $raiz = str_ends_with($termino, '*');
                $t = $raiz ? substr($termino, 0, -1) : $termino;
                $patron = '/(?<![a-z0-9])' . preg_quote($t, '/') . ($raiz ? '' : '(?![a-z0-9])') . '/';
                if (preg_match($patron, $normal) === 1) {
                    $categorias[] = $categoria;
                    break;
                }
            }
        }
        return $categorias;
    }

    /** Minúsculas, sin tildes ni signos: lo único que compara `buscar()`. */
    private static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss',
        ]);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $t));
    }
}
