<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\ParserEvaluation;

/**
 * Keeps the parser's results on tests/fixtures/parser-evaluation.json from changing silently: every case
 * without 'falla_conocida' must come out exactly as written, and every case with it must still fail, so a
 * fix that makes one pass has to remove its mark (and bin/evaluate-parser.php shows the gain).
 */
class ParserEvaluationTest extends TestCase
{
    private const SET = __DIR__ . '/../fixtures/parser-evaluation.json';

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function cases(): iterable
    {
        foreach (ParserEvaluation::fromFile(self::SET)->cases() as $case) {
            yield $case['id'] => [$case];
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function testCaseBehavesAsTheSetSays(array $case): void
    {
        $result = ParserEvaluation::evaluate($case);

        if ($result['knownFailure'] === null) {
            $this->assertSame($result['expected'], $result['actual'], "{$result['id']}: el parser no da lo esperado");
            if ($result['expectedLanguage'] !== null) {
                $this->assertSame($result['expectedLanguage'], $result['detectedLanguage'], "{$result['id']}: idioma detectado");
            }
            return;
        }

        $this->assertFalse(
            $result['passes'],
            "{$result['id']} ya sale bien: quítale 'falla_conocida' en tests/fixtures/parser-evaluation.json"
        );
    }

    public function testEveryKnownFailureSaysWhyAndHasACategory(): void
    {
        foreach (ParserEvaluation::fromFile(self::SET)->cases() as $case) {
            if (isset($case['falla_conocida'])) {
                $this->assertNotSame('', trim($case['falla_conocida']), $case['id']);
                $this->assertNotEmpty($case['categoria'] ?? null, "{$case['id']}: falta 'categoria'");
            }
        }
    }

    public function testCaseIdsAreUnique(): void
    {
        $ids = array_column(ParserEvaluation::fromFile(self::SET)->cases(), 'id');

        $this->assertSame(array_values(array_unique($ids)), $ids);
    }
}
