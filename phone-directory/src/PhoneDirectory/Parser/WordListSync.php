<?php

namespace PhoneDirectory\Parser;

/**
 * Keeps the word lists of the three parsers (PHP, Python, Java) equal by writing them all from one file,
 * data/palabras.json: the street words, the German street suffixes, the abbreviations that open a name, the words
 * that detect a language and the words that name a business.
 *
 * Each parser has regions between marker lines,
 *
 *     BEGIN generated: palabras/<region> ...
 *     END generated: palabras/<region>
 *
 * and everything between them is written here. Nothing else in those files is touched. bin/generar-palabras.php
 * writes them; with --comprobar (and in WordListSyncTest, which CI runs) it only says which are out of date, as
 * `Listado-colores/` and `Listado-paises/` do with their generated files.
 *
 * @phpstan-type Lists array{
 *     idiomas: list<string>,
 *     numero_al_final: list<string>,
 *     calle: array<string, list<string>>,
 *     sufijos_de_calle: array<string, list<string>>,
 *     abreviaturas_de_nombre: array<string, list<string>>,
 *     deteccion: array<string, list<string>>,
 *     empresa: array<string, list<string>>
 * }
 */
final class WordListSync
{
    /** File => the regions it has, as [region => renderer]. Paths are relative to the project folder. */
    private const TARGETS = [
        'src/PhoneDirectory/Parser/StreetRules.php' => ['calles' => 'phpStreets'],
        'src/PhoneDirectory/Parser/MultiLanguagePhoneDirectoryParser.php' => ['deteccion' => 'phpDetection', 'empresas' => 'phpEntities'],
        'ports/python/phone_directory_parser.py' => ['calles' => 'pyStreets', 'deteccion' => 'pyDetection', 'empresas' => 'pyEntities'],
        'ports/java/PhoneDirectoryParser.java' => ['calles' => 'javaStreets', 'deteccion' => 'javaDetection', 'empresas' => 'javaEntities'],
    ];

    /** @var Lists */
    private array $lists;

    /** @param string $root The project folder (the one with data/ and src/) */
    public function __construct(private readonly string $root)
    {
        $this->lists = self::load($root . '/data/palabras.json');
    }

    /**
     * Rewrites the generated regions that are out of date.
     *
     * @return list<string> The files that were (or, with $write false, would be) changed
     */
    public function sync(bool $write): array
    {
        $changed = [];
        foreach (self::TARGETS as $file => $regions) {
            $path = $this->root . '/' . $file;
            $text = file_get_contents($path);
            if ($text === false) {
                throw new \RuntimeException("Cannot read {$path}");
            }

            $updated = $text;
            foreach ($regions as $region => $renderer) {
                $updated = self::replaceRegion($updated, $region, $this->{$renderer}(), $file);
            }

            if ($updated !== $text) {
                $changed[] = $file;
                if ($write) {
                    file_put_contents($path, $updated);
                }
            }
        }

        return $changed;
    }

    /** @return Lists */
    private static function load(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Cannot read {$path}");
        }

        /** @var Lists $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    private static function replaceRegion(string $text, string $region, string $body, string $file): string
    {
        $pattern = '/^([ \t]*(?:\/\/|#) BEGIN generated: palabras\/' . preg_quote($region, '/') . '\b[^\n]*\n)(.*?)(^[ \t]*(?:\/\/|#) END generated: palabras\/' . preg_quote($region, '/') . '\n)/ms';
        $count = 0;
        $result = preg_replace_callback($pattern, fn(array $m) => $m[1] . $body . $m[3], $text, 1, $count);
        if ($result === null || $count !== 1) {
            throw new \RuntimeException("{$file} has no region 'palabras/{$region}' between its BEGIN and END markers");
        }

        return $result;
    }

    // ---- PHP ----

    /** @param list<string> $words */
    private static function php(array $words): string
    {
        return '[' . implode(', ', array_map(fn(string $w) => "'" . addcslashes($w, "'\\") . "'", $words)) . ']';
    }

    /** @param array<string, list<string>> $byLanguage */
    private static function phpMap(array $byLanguage, string $note = ''): string
    {
        $rows = '';
        foreach ($byLanguage as $language => $words) {
            $rows .= "        '{$language}' => " . self::php($words) . ',' . ($words === [] && $note !== '' ? " // {$note}" : '') . "\n";
        }

        return $rows;
    }

    private function phpStreets(): string
    {
        $l = $this->lists;

        return '    public const SUPPORTED_LANGUAGES = ' . self::php($l['idiomas']) . ";\n\n"
            . "    // Languages whose addresses put the house number after the street name (\"Calle Mayor 12\", \"Via Roma 10\").\n"
            . '    public const NUMBER_LAST_LANGUAGES = ' . self::php($l['numero_al_final']) . ";\n\n"
            . "    private const MARKERS = [\n" . self::phpMap($l['calle']) . "    ];\n\n"
            . "    // German compounds glue the street type onto the name (\"Hauptstraße\"), so these match as word endings.\n"
            . "    public const SUFFIXES = [\n" . self::phpMap($l['sufijos_de_calle']) . "    ];\n\n"
            . "    // Street words that are also the usual abbreviation opening a name (see the class comment).\n"
            . "    private const NAME_ABBREVIATIONS = [\n" . self::phpMap($l['abreviaturas_de_nombre']) . "    ];\n";
    }

    private function phpDetection(): string
    {
        return "    private const LANGUAGE_DETECTION_MARKERS = [\n"
            . self::phpMap($this->lists['deteccion'], 'no words of its own: detected via StreetRules::SUFFIXES only; its marker words are too ambiguous')
            . "    ];\n";
    }

    private function phpEntities(): string
    {
        return "    private const ENTITY_TYPE_MARKERS = [\n" . self::phpMap($this->lists['empresa']) . "    ];\n";
    }

    // ---- Python ----

    /** @param list<string> $words */
    private static function py(array $words): string
    {
        return '[' . implode(', ', array_map(fn(string $w) => "'" . addcslashes($w, "'\\") . "'", $words)) . ']';
    }

    /** @param array<string, list<string>> $byLanguage */
    private static function pyMap(string $name, array $byLanguage, string $note = ''): string
    {
        $rows = "    {$name} = {\n";
        foreach ($byLanguage as $language => $words) {
            $rows .= "        '{$language}': " . self::py($words) . ',' . ($words === [] && $note !== '' ? "  # {$note}" : '') . "\n";
        }

        return $rows . "    }\n";
    }

    private function pyStreets(): string
    {
        $l = $this->lists;

        return '    SUPPORTED_LANGUAGES = ' . self::py($l['idiomas']) . "\n"
            . "    # Languages whose addresses put the house number after the street name (\"Calle Mayor 12\", \"Via Roma 10\").\n"
            . '    NUMBER_LAST_LANGUAGES = (' . implode(', ', array_map(fn(string $w) => "'{$w}'", $l['numero_al_final'])) . ")\n\n"
            . self::pyMap('MARKERS', $l['calle'])
            . "    # German compounds glue the street type onto the name (\"Hauptstraße\"), so these match as word endings.\n"
            . self::pyMap('SUFFIXES', $l['sufijos_de_calle'])
            . "    # Street words that are also the usual abbreviation opening a name (\"Dr.\" doctor, \"St.\" saint): they only\n"
            . "    # make a street when the line has a digit or they are not its first word.\n"
            . self::pyMap('NAME_ABBREVIATIONS', $l['abreviaturas_de_nombre']);
    }

    private function pyDetection(): string
    {
        return self::pyMap('DETECTION_MARKERS', $this->lists['deteccion'], 'no words of its own: detected by the street suffixes only');
    }

    private function pyEntities(): string
    {
        return self::pyMap('ENTITY_TYPE_MARKERS', $this->lists['empresa']);
    }

    // ---- Java ----

    /** @param list<string> $words */
    private static function java(array $words): string
    {
        return 'List.of(' . implode(', ', array_map(fn(string $w) => '"' . addcslashes($w, '"\\') . '"', $words)) . ')';
    }

    /** @param array<string, list<string>> $byLanguage */
    private static function javaMap(string $declaration, array $byLanguage, string $note = ''): string
    {
        $rows = [];
        $last = array_key_last($byLanguage);
        foreach ($byLanguage as $language => $words) {
            $rows[] = "        \"{$language}\", " . self::java($words) . ($language === $last ? '' : ',')
                . ($words === [] && $note !== '' ? " // {$note}" : '');
        }

        return "    {$declaration} = Map.of(\n" . implode("\n", $rows) . "\n    );\n";
    }

    private function javaStreets(): string
    {
        $l = $this->lists;
        $set = 'Set.of(' . implode(', ', array_map(fn(string $w) => "\"{$w}\"", $l['numero_al_final'])) . ')';

        return '    static final List<String> SUPPORTED_LANGUAGES = ' . self::java($l['idiomas']) . ";\n"
            . "    // Languages that write the house number after the street (\"Calle Mayor 12\", \"Via Roma 10\").\n"
            . "    static final Set<String> NUMBER_LAST_LANGUAGES = {$set};\n\n"
            . self::javaMap('private static final Map<String, List<String>> MARKERS', $l['calle'])
            . "    // German compounds glue the street type onto the name (\"Hauptstraße\"), so these match as word endings.\n"
            . self::javaMap('static final Map<String, List<String>> SUFFIXES', $l['sufijos_de_calle'])
            . "    // Street words that are also the usual abbreviation opening a name (\"Dr.\" doctor, \"St.\" saint): they only\n"
            . "    // make a street when the line has a digit or they are not its first word.\n"
            . self::javaMap('private static final Map<String, List<String>> NAME_ABBREVIATIONS', $l['abreviaturas_de_nombre']);
    }

    private function javaDetection(): string
    {
        return self::javaMap('private static final Map<String, List<String>> DETECTION_MARKERS', $this->lists['deteccion'], 'no words of its own: detected by the street suffixes only');
    }

    private function javaEntities(): string
    {
        return self::javaMap('private static final Map<String, List<String>> ENTITY_TYPE_MARKERS', $this->lists['empresa']);
    }
}
