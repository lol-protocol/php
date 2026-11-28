<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Normaliza el texto para buscarlo: minúsculas, sin acentos, sin palabras vacías y con una
 * raíz muy simple (quita «-es» y «-s» del final). La versión en Java
 * (java/src/chatlibros/Normaliza.java) hace exactamente lo mismo; `pruebas/vectores.tsv`
 * comprueba que las dos den los mismos resultados.
 */
final class Normaliza
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /** Palabras vacías en español e inglés. Ya sin acentos. */
    private const VACIAS = [
        'a', 'al', 'como', 'con', 'cual', 'cuales', 'cuando', 'de', 'del', 'donde', 'e', 'el',
        'ella', 'ellas', 'ellos', 'en', 'entre', 'era', 'es', 'esa', 'ese', 'eso', 'esta', 'este',
        'esto', 'estaba', 'estaban', 'fue', 'ha', 'habia', 'han', 'hay', 'la', 'las', 'le', 'les',
        'lo', 'los', 'mas', 'me', 'mi', 'muy', 'ni', 'no', 'o', 'para', 'pero', 'por', 'que', 'quien',
        'se', 'ser', 'si', 'sin', 'tenia', 'tiene', 'tienen', 'dice', 'decia', 'dijo', 'hacia',
        // El molde de las preguntas («¿qué pasó con…?», «¿qué hizo…?»): no dicen de qué se pregunta.
        'paso', 'pasa', 'pasan', 'sucedio', 'sucede', 'ocurrio', 'ocurre', 'hizo', 'hace', 'hacen',
        'sobre', 'son', 'su', 'sus', 'te', 'tu', 'u', 'un', 'una', 'unas', 'unos', 'y', 'ya',
        'and', 'an', 'as', 'at', 'be', 'by', 'for', 'in', 'is', 'it', 'of', 'on', 'the', 'this',
        'that', 'to', 'with',
    ];

    /** @var array<string,true>|null */
    private static ?array $vacias = null;

    /** Minúsculas y sin acentos (ñ pasa a n). */
    public static function texto(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), self::ACENTOS);
    }

    /**
     * Palabras de un texto tal como se leen (sin acentos, en minúsculas), en orden de
     * aparición, sin palabras vacías.
     *
     * @return list<string>
     */
    public static function palabras(string $s): array
    {
        self::$vacias ??= array_fill_keys(self::VACIAS, true);

        preg_match_all('/[a-z0-9]+/', self::texto($s), $coincidencias);
        $palabras = [];
        foreach ($coincidencias[0] as $palabra) {
            if (strlen($palabra) < 2 || isset(self::$vacias[$palabra])) {
                continue;
            }
            $palabras[] = $palabra;
        }
        return $palabras;
    }

    /**
     * Términos de búsqueda de un texto: las palabras de palabras() con la raíz aplicada.
     *
     * @return list<string>
     */
    public static function terminos(string $s): array
    {
        return array_map([self::class, 'raiz'], self::palabras($s));
    }

    /**
     * Terminaciones que se quitan después del plural, de la más larga a la más corta. Solo si
     * queda una raíz de 4 letras o más: «esperaban» → «esper», pero «mar» y «mujer» no cambian.
     */
    private const SUFIJOS = ['mente', 'aban', 'iendo', 'ando', 'aba', 'ado', 'ido', 'ar', 'er', 'ir'];

    /**
     * Raíz muy simple: quita «-es» (más de 4 letras) o «-s» (más de 3) del plural y, después,
     * una terminación de SUFIJOS (adverbios en «-mente», gerundios, participios, imperfecto
     * e infinitivos). No es un lematizador: junta formas comunes, no todas.
     */
    public static function raiz(string $palabra): string
    {
        $p = $palabra;
        $largo = strlen($p);
        if ($largo > 4 && str_ends_with($p, 'es')) {
            $p = substr($p, 0, -2);
        } elseif ($largo > 3 && str_ends_with($p, 's')) {
            $p = substr($p, 0, -1);
        }
        foreach (self::SUFIJOS as $sufijo) {
            if (str_ends_with($p, $sufijo) && strlen($p) - strlen($sufijo) >= 4) {
                return substr($p, 0, -strlen($sufijo));
            }
        }
        return $p;
    }
}
