<?php

namespace PhoneDirectory;

/**
 * Common given names in the six languages the parser reads, to tell a business named after a person
 * ("Panadería Juan Pérez", "Chez Marie", "Hans Müller GmbH") from one that is not ("Restaurante Siglo 21",
 * "ACME Corp"). Privacy hides the first kind like a person who may be alive (see Privacy::mayNameALivingPerson).
 *
 * The lists hold the names most common among adults in 1930-1975, the years of the catalog's directories,
 * folded as TextFolding::fold() does (lowercase, no accents). They are checked all together, whatever the
 * business's country: a directory lists immigrants too, and a name from another list showing up in a business
 * name is still a person's name.
 *
 * What it misses, accepted when issue #233 was decided: a business named only after a surname
 * ("Panadería Pérez") and a given name that is not on the lists. What it hides too much: a business whose
 * name holds a word that is also a given name ("Farmacia San José", "Florería Rosa"). Hiding too much is the
 * side to err on, as everywhere in Privacy.
 */
final class GivenNames
{
    private const NAMES = [
        'es' => [
            'juan', 'jose', 'antonio', 'manuel', 'francisco', 'luis', 'carlos', 'jesus', 'miguel', 'pedro',
            'angel', 'rafael', 'fernando', 'jorge', 'alberto', 'ramon', 'enrique', 'andres', 'pablo', 'javier',
            'ricardo', 'eduardo', 'sergio', 'raul', 'alfonso', 'julio', 'mario', 'roberto', 'victor', 'hector',
            'arturo', 'ernesto', 'guillermo', 'gonzalo', 'ignacio', 'joaquin', 'agustin', 'tomas', 'santiago', 'diego',
            'maria', 'carmen', 'josefa', 'isabel', 'ana', 'dolores', 'pilar', 'teresa', 'rosa', 'mercedes',
            'concepcion', 'francisca', 'antonia', 'lucia', 'elena', 'cristina', 'marta', 'laura', 'beatriz', 'julia',
            'rosario', 'luisa', 'margarita', 'gloria', 'silvia', 'patricia', 'raquel', 'veronica', 'monica', 'susana',
            'ines', 'alicia', 'victoria', 'consuelo', 'amparo', 'esperanza', 'soledad', 'luz', 'paz', 'eva',
        ],
        'en' => [
            'john', 'william', 'james', 'george', 'charles', 'robert', 'joseph', 'frank', 'edward', 'thomas',
            'henry', 'walter', 'harry', 'arthur', 'albert', 'fred', 'frederick', 'richard', 'david', 'paul',
            'donald', 'kenneth', 'ronald', 'raymond', 'harold', 'jack', 'peter', 'michael', 'samuel', 'benjamin',
            'mary', 'margaret', 'helen', 'elizabeth', 'dorothy', 'ruth', 'anna', 'mildred', 'alice', 'florence',
            'ethel', 'lillian', 'edna', 'gladys', 'frances', 'betty', 'jean', 'doris', 'shirley', 'barbara',
            'patricia', 'joan', 'nancy', 'joyce', 'irene', 'grace', 'sarah', 'emma', 'lucy', 'annie',
        ],
        'fr' => [
            'jean', 'pierre', 'jacques', 'michel', 'andre', 'louis', 'rene', 'marcel', 'henri', 'paul',
            'georges', 'roger', 'robert', 'joseph', 'claude', 'bernard', 'francois', 'raymond', 'maurice', 'lucien',
            'marie', 'jeanne', 'marguerite', 'germaine', 'louise', 'suzanne', 'madeleine', 'yvonne', 'helene', 'simone',
            'denise', 'odette', 'paulette', 'jacqueline', 'monique', 'francoise', 'nicole', 'colette', 'josette', 'genevieve',
        ],
        'pt' => [
            'joao', 'jose', 'antonio', 'manuel', 'francisco', 'joaquim', 'luis', 'carlos', 'paulo', 'pedro',
            'fernando', 'jorge', 'mario', 'armando', 'alberto', 'artur', 'sebastiao', 'raimundo', 'sergio', 'rui',
            'maria', 'ana', 'rosa', 'francisca', 'antonia', 'teresa', 'isabel', 'conceicao', 'fatima', 'lucia',
            'helena', 'margarida', 'gloria', 'luisa', 'aparecida', 'rita', 'graca', 'celeste', 'amelia', 'olga',
        ],
        'de' => [
            'hans', 'karl', 'heinz', 'peter', 'walter', 'werner', 'gerhard', 'helmut', 'kurt', 'otto',
            'wilhelm', 'friedrich', 'herbert', 'horst', 'ernst', 'heinrich', 'paul', 'fritz', 'josef', 'franz',
            'gunter', 'klaus', 'manfred', 'rolf', 'dieter', 'jurgen', 'wolfgang', 'erich', 'hermann', 'ludwig',
            'maria', 'anna', 'gertrud', 'elisabeth', 'ursula', 'erika', 'hildegard', 'ilse', 'irmgard', 'helga',
            'margarete', 'ingrid', 'gisela', 'liselotte', 'ruth', 'charlotte', 'renate', 'hannelore', 'martha', 'frieda',
        ],
        'it' => [
            'giuseppe', 'giovanni', 'antonio', 'mario', 'luigi', 'francesco', 'angelo', 'vincenzo', 'pietro', 'salvatore',
            'carlo', 'franco', 'domenico', 'bruno', 'paolo', 'michele', 'giorgio', 'aldo', 'sergio', 'luciano',
            'giuliano', 'renato', 'umberto', 'enzo', 'alberto', 'roberto', 'giancarlo', 'gino', 'nicola', 'raffaele',
            'maria', 'anna', 'giuseppina', 'rosa', 'angela', 'giovanna', 'teresa', 'lucia', 'carmela', 'caterina',
            'francesca', 'antonietta', 'rita', 'franca', 'giulia', 'elena', 'luisa', 'concetta', 'assunta', 'carla',
        ],
    ];

    /** @var array<string, true>|null every name of every list, as keys */
    private static ?array $all = null;

    /** Whether $text holds one of the given names as a whole word, ignoring case and accents. */
    public static function appearsIn(string $text): bool
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        $names = self::all();
        foreach (preg_split('/[^\p{L}]+/u', TextFolding::fold($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (isset($names[$word])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    private static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (self::NAMES as $names) {
                self::$all += array_fill_keys($names, true);
            }
        }

        return self::$all;
    }
}
