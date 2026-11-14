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
 * It also knows the most common surnames of each language (SURNAMES), so a business named only after a surname
 * ("Panadería Pérez") is hidden too, and it skips a name that follows a saint's title ("Farmacia San José",
 * "Hotel Santa María"): that business is named after a saint, not after a person. Both are heuristics.
 *
 * What it still misses: a surname or a given name that is not on the lists. The surname lists leave out the
 * ones that are also everyday words ("Flores", "Torres", "Costa", "Miller", "Baker"), because hiding "Hotel
 * Torres del Paine" for them would hide too much; so "Panadería Torres" is shown. What it still hides too much:
 * a business whose name holds a word that is also a given name without a saint's title ("Florería Rosa").
 * Hiding too much is the side to err on, as everywhere in Privacy. The saint's title is the one place where it
 * hides less: a person's business named "Ferretería San José" because the owner is José is shown (issue #233).
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

    /**
     * Common surnames per language, folded like NAMES. Only the ones that are not also everyday words: a list of
     * surnames that hid every "Flores", "Costa" or "Torres" would hide shops that are named after a flower, a coast
     * or a tower.
     */
    private const SURNAMES = [
        'es' => [
            'garcia', 'rodriguez', 'martinez', 'lopez', 'gonzalez', 'hernandez', 'perez', 'sanchez', 'ramirez', 'fernandez',
            'gomez', 'diaz', 'alvarez', 'jimenez', 'munoz', 'gutierrez', 'ruiz', 'navarro', 'dominguez', 'mendoza',
            'guerrero', 'vargas', 'aguilar', 'molina', 'rojas',
        ],
        'en' => [
            'smith', 'johnson', 'williams', 'jones', 'davis', 'wilson', 'anderson', 'taylor', 'moore', 'jackson',
            'thompson', 'harris', 'clark', 'lewis', 'walker', 'robinson', 'edwards', 'campbell', 'mitchell', 'turner',
        ],
        'fr' => [
            'martin', 'bernard', 'dubois', 'durand', 'moreau', 'lefebvre', 'bertrand', 'girard', 'dupont', 'lambert',
            'rousseau',
        ],
        'pt' => [
            'silva', 'oliveira', 'souza', 'pereira', 'rodrigues', 'ferreira', 'alves', 'ribeiro', 'carvalho', 'gomes',
            'almeida', 'nascimento', 'barbosa', 'cardoso', 'teixeira',
        ],
        'de' => [
            'muller', 'mueller', 'schmidt', 'schneider', 'fischer', 'weber', 'meyer', 'meier', 'maier', 'wagner',
            'becker', 'schulz', 'hoffmann', 'hofmann', 'schafer', 'wolf', 'schroder', 'neumann', 'kruger', 'hartmann',
            'schmitt', 'krause', 'lehmann', 'herrmann',
        ],
        'it' => [
            'rossi', 'russo', 'ferrari', 'esposito', 'bianchi', 'romano', 'colombo', 'ricci', 'marino', 'giordano',
            'mancini', 'rizzo', 'lombardi', 'moretti', 'barbieri', 'santoro', 'mariani', 'rinaldi', 'caruso', 'ferraro',
            'martini', 'longo', 'martinelli', 'lombardo', 'coppola',
        ],
    ];

    /**
     * The titles of a saint, folded: the name right after one belongs to the saint ("San José", "Saint Joseph",
     * "St. Mary"), not to a person who may be alive.
     */
    private const SAINT_TITLES = ['san', 'santa', 'santo', 'sao', 'saint', 'sainte', 'st', 'sankt'];

    /** @var array<string, true>|null every given name and surname of every list, as keys */
    private static ?array $all = null;

    /**
     * Whether $text holds a given name or a common surname as a whole word, ignoring case and accents, unless
     * that word comes right after a saint's title.
     */
    public static function appearsIn(string $text): bool
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        $names = self::all();
        $previous = null;
        foreach (preg_split('/[^\p{L}]+/u', TextFolding::fold($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (isset($names[$word]) && !in_array($previous, self::SAINT_TITLES, true)) {
                return true;
            }
            $previous = $word;
        }

        return false;
    }

    /** @return array<string, true> */
    private static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach ([self::NAMES, self::SURNAMES] as $lists) {
                foreach ($lists as $names) {
                    self::$all += array_fill_keys($names, true);
                }
            }
        }

        return self::$all;
    }
}
