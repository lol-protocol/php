<?php

namespace PhoneDirectory\Parser;

/**
 * The inputs on which the PHP parser and its Python and Java ports must give the same result: a hand-written
 * list (tests/fixtures/parser-paridad-entradas.json, invented OCR-style and multilingual lines) plus a fixed
 * number of random mixes of the pieces a directory is made of, drawn from a seed so the list is always the same.
 *
 * bin/generar-corpus-paridad.php writes tests/fixtures/parser-paridad.json from them with what the PHP parser
 * gives today. The ports run that file (test_evaluation_set.py, EvaluationSetCheck), so a change in one parser
 * that the others do not follow shows up as a red test there, instead of as a silent drift. This is not a
 * statement that the output is right (the evaluation set is that), only that the three agree.
 *
 * @phpstan-type Row array{tipo: string, nombre: string, calle: string, telefono: string|null}
 * @phpstan-type ParityCase array{id: string, idioma: string, entrada: string, esperado: list<Row>}
 */
final class ParityCorpus
{
    public const SEED = 11;
    public const RANDOM_CASES = 600;
    public const LANGUAGES = ['en', 'es', 'fr', 'de', 'it', 'pt'];

    /** Pieces that make a directory line, and others that confuse one (typographic quotes, CJK, abbreviations). */
    private const PIECES = [
        'A', 'b', 'Ñ', 'é', 'ß', '1', '2', '0', 'l', 'O', ' ', ' ', ' ', "\n", "\n", ',', '.', '-', '–', '(', ')', '/',
        "\t", '’', '“', '日', 'Calle ', 'Street ', 'rue ', 'Via ', 'Str. ', '555-123-4567', '91 234 56 78', '030 123456',
        'Apt ', 'bis', 'St.', 'Dr.', 'SMITH', 'John', 'García', 'Juan', 'Rua ', 'Av. ',
    ];

    /** @return list<array{0: string, 1: string}> [language, input] */
    public static function inputs(): array
    {
        $json = file_get_contents(__DIR__ . '/../../../tests/fixtures/parser-paridad-entradas.json');
        if ($json === false) {
            throw new \RuntimeException('Cannot read tests/fixtures/parser-paridad-entradas.json');
        }

        /** @var list<array{0: string, 1: string}> $inputs */
        $inputs = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        mt_srand(self::SEED);
        $last = count(self::PIECES) - 1;
        for ($i = 0; $i < self::RANDOM_CASES; $i++) {
            $text = '';
            for ($j = mt_rand(3, 40); $j > 0; $j--) {
                $text .= self::PIECES[mt_rand(0, $last)];
            }
            $inputs[] = [self::LANGUAGES[mt_rand(0, count(self::LANGUAGES) - 1)], $text];
        }

        return $inputs;
    }

    /**
     * The corpus with what the PHP parser gives for each input.
     *
     * @return list<ParityCase>
     */
    public static function build(): array
    {
        $cases = [];
        foreach (self::inputs() as $i => [$language, $input]) {
            $result = ParserEvaluation::evaluate(['id' => "p{$i}", 'idioma' => $language, 'entrada' => $input, 'esperado' => []]);
            $cases[] = ['id' => "p{$i}", 'idioma' => $language, 'entrada' => $input, 'esperado' => $result['actual']];
        }

        return $cases;
    }

    /** @param list<ParityCase> $cases */
    public static function encode(array $cases): string
    {
        $json = json_encode(['descripcion' => 'Generado por bin/generar-corpus-paridad.php: lo que da el parser de PHP; los puertos de Python y Java deben dar lo mismo (ver ParityCorpus). No se edita a mano.', 'casos' => $cases], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return preg_replace('/\{"id":/', "\n{\"id\":", $json) . "\n";
    }
}
