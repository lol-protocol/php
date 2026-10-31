<?php

namespace PhoneDirectory\Parser;

/**
 * Runs MultiLanguagePhoneDirectoryParser over a fixed set of hand-checked cases
 * (tests/fixtures/parser-evaluation.json) and reports how close its output is to what a person reads in
 * the directory. bin/evaluate-parser.php prints the report; ParserEvaluationTest keeps it from regressing.
 *
 * Expected values are the truth, not the parser's current output. A case marked 'falla_conocida' is one
 * the parser still gets wrong; the test requires it to keep failing, so a fix has to remove the mark.
 *
 * @phpstan-type Row array{tipo: string, nombre: string, calle: string, telefono: string|null}
 * @phpstan-type EvaluationCase array{id: string, idioma: string|null, idioma_detectado?: string, entrada: string, esperado: list<Row>, falla_conocida?: string, categoria?: string, nota?: string}
 * @phpstan-type CaseResult array{id: string, expected: list<Row>, actual: list<Row>, parseErrors: int, expectedLanguage: string|null, detectedLanguage: string, knownFailure: string|null, category: string|null, passes: bool}
 * @phpstan-type Totals array{cases: int, passing: int, knownFailures: int, unexpected: int, expectedRows: int, producedRows: int, correctRows: int, parseErrors: int}
 */
final class ParserEvaluation
{
    /** @param list<EvaluationCase> $cases */
    private function __construct(private readonly array $cases)
    {
    }

    public static function fromFile(string $path): self
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Cannot read evaluation set: {$path}");
        }

        /** @var array{casos: list<EvaluationCase>} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self($data['casos']);
    }

    /** @return list<EvaluationCase> */
    public function cases(): array
    {
        return $this->cases;
    }

    /**
     * @param EvaluationCase $case
     * @return CaseResult
     */
    public static function evaluate(array $case): array
    {
        $parser = new MultiLanguagePhoneDirectoryParser();
        $actual = [];
        foreach ($parser->parseContent($case['entrada'], $case['idioma']) as $entry) {
            $entity = $entry['entity'];
            $actual[] = [
                'tipo' => $entry['type'],
                'nombre' => $entry['type'] === 'natural' ? $entity->getRawName() : $entity->getBusinessName(),
                'calle' => $entity->getStreet(),
                'telefono' => $entity->getPhoneNumber(),
            ];
        }

        $expectedLanguage = $case['idioma_detectado'] ?? null;
        $detected = $parser->getDetectedLanguage();

        return [
            'id' => $case['id'],
            'expected' => $case['esperado'],
            'actual' => $actual,
            'parseErrors' => count($parser->getErrors()),
            'expectedLanguage' => $expectedLanguage,
            'detectedLanguage' => $detected,
            'knownFailure' => $case['falla_conocida'] ?? null,
            'category' => $case['categoria'] ?? null,
            'passes' => $actual === $case['esperado'] && ($expectedLanguage === null || $expectedLanguage === $detected),
        ];
    }

    /** @return array{results: list<CaseResult>, totals: Totals} */
    public function run(): array
    {
        $results = array_map(self::evaluate(...), $this->cases);
        $totals = ['cases' => 0, 'passing' => 0, 'knownFailures' => 0, 'unexpected' => 0, 'expectedRows' => 0, 'producedRows' => 0, 'correctRows' => 0, 'parseErrors' => 0];

        foreach ($results as $result) {
            $totals['cases']++;
            $totals['expectedRows'] += count($result['expected']);
            $totals['producedRows'] += count($result['actual']);
            $totals['correctRows'] += self::countCorrectRows($result['expected'], $result['actual']);
            $totals['parseErrors'] += $result['parseErrors'];

            if ($result['passes']) {
                $totals['passing']++;
            }
            if ($result['passes'] === ($result['knownFailure'] !== null)) {
                $totals['unexpected']++;
            } elseif ($result['knownFailure'] !== null) {
                $totals['knownFailures']++;
            }
        }

        return ['results' => $results, 'totals' => $totals];
    }

    /**
     * Expected rows that appear, field for field, among the produced ones (each produced row counts once).
     *
     * @param list<Row> $expected
     * @param list<Row> $actual
     */
    private static function countCorrectRows(array $expected, array $actual): int
    {
        $correct = 0;
        foreach ($expected as $row) {
            $index = array_search($row, $actual, true);
            if ($index !== false) {
                $correct++;
                unset($actual[$index]);
            }
        }

        return $correct;
    }
}
