<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\{FormSeverity, TopicInflector};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** El plural y el femenino no ofenden lo mismo que la forma base: `by` le da a cada forma su severidad. */
class AsymmetricFormsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../../config';
    private const TAGS = [
        'sg', 'pl', 'm', 'f', 'm.sg', 'm.pl', 'f.sg', 'f.pl', 'verb', 'infl',
        'dim', 'dim.sg', 'dim.pl', 'dim.m', 'dim.f', 'dim.m.sg', 'dim.m.pl', 'dim.f.sg', 'dim.f.pl',
    ];

    public function testTheMostSpecificKeyWins(): void
    {
        $by = ['f' => 'high', 'pl' => 'low', 'f.pl' => 'medium'];

        $this->assertSame('high', FormSeverity::of('f.sg', $by, 'medium'));
        $this->assertSame('medium', FormSeverity::of('f.pl', $by, 'medium'));
        $this->assertSame('low', FormSeverity::of('m.pl', $by, 'medium'));
        $this->assertSame('medium', FormSeverity::of('m.sg', $by, 'medium'));
    }

    public function testAnUnreviewedGeneratedFormNeverBlocksOnItsOwn(): void
    {
        $this->assertSame('medium', FormSeverity::of('pl', [], 'high', true));
        $this->assertSame('high', FormSeverity::of('pl', ['pl' => 'high'], 'high', true), 'declarado, sí');
        $this->assertSame('low', FormSeverity::of('pl', [], 'low', true));
    }

    public function testExpandsWithTheSeverityOfEachFormAndWrittenEntriesWin(): void
    {
        $out = TopicInflector::expand(['animal' => [
            ['word' => 'cerda', 'riskType' => 'animal', 'severity' => 'medium', 'nameCollision' => true],
            ['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'medium', 'nameCollision' => true, 'forms' => 'adj', 'by' => ['f' => 'high', 'm.pl' => 'none']],
        ]], 'spa', true);
        $byWord = array_column($out['animal'], null, 'word');

        $this->assertSame(['cerda', 'cerdo', 'cerdas'], array_keys($byWord), '«cerda» ya está escrita; «cerdos» es none');
        $this->assertSame('medium', $byWord['cerda']['severity'], 'lo escrito manda');
        $this->assertSame('high', $byWord['cerdas']['severity']);
        $this->assertFalse($byWord['cerdas']['nameCollision'], 'la marca de apellido protege sólo a la palabra escrita');
        $this->assertTrue($byWord['cerdo']['nameCollision']);
    }

    /** @return array<string,array{string,string}> */
    public static function spanishLines(): array
    {
        return [
            'cerdo' => ['eres un cerdo', 'review'],
            'cerdos' => ['sois unos cerdos', 'review'],
            'cerdas: el femenino es el insulto sexualizado' => ['sois unas cerdas', 'reject'],
            'putas' => ['sois unas putas', 'reject'],
            'zorras' => ['malditas zorras', 'reject'],
            'guarras' => ['sois unas guarras', 'reject'],
            'el animal sigue sin ser insulto' => ['comí cerdo', 'approve'],
        ];
    }

    #[DataProvider('spanishLines')]
    public function testSpanishGenderAndNumber(string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line)->getDecision(), $line);
    }

    public function testFormsAndByAreWellFormedInEveryConfigFile(): void
    {
        foreach ([...glob(self::CONFIG_DIR . '/languages/*.php'), ...glob(self::CONFIG_DIR . '/chat-topics/*.php')] as $file) {
            $data = require $file;
            foreach ($data['words'] ?? [] as $entries) {
                foreach ($entries as $entry) {
                    $where = basename(dirname($file)) . '/' . basename($file) . " «{$entry['word']}»";
                    if (isset($entry['by'])) {
                        $this->assertArrayHasKey('forms', $entry, "{$where}: `by` sin `forms` no hace nada");
                        $this->assertSame([], array_diff(array_keys($entry['by']), self::TAGS), "{$where}: rasgo desconocido en `by`");
                        $this->assertSame([], array_diff($entry['by'], ['low', 'medium', 'high', 'none']), "{$where}: severidad desconocida en `by`");
                        $used = array_merge(...array_map(fn(string $t): array => [$t, ...explode('.', $t), ...(str_starts_with($t, 'dim.') ? ['dim.' . explode('.', $t)[1], 'dim.' . substr($t, strrpos($t, '.') + 1)] : [])], array_values(TopicInflector::tagged(basename($file, '.php'), $entry['word'], $entry['forms'], true))));
                        $this->assertSame([], array_diff(array_keys($entry['by']), $used), "{$where}: ninguna forma generada tiene ese rasgo");
                    }
                    $this->assertContains($entry['forms'] ?? 'noun', ['noun', 'adj', 'verb'], $where);
                }
            }
        }
    }
}
