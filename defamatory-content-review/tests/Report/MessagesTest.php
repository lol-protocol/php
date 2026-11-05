<?php

namespace Tests\Report;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Report\Messages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessagesTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../../config';

    /** @return array<string,array{string}> */
    public static function locales(): array
    {
        $others = array_diff(Messages::locales(), ['spa']);

        return array_combine($others, array_map(static fn(string $l): array => [$l], $others));
    }

    /** @return array<int,string> los marcadores de sprintf de un texto, ordenados */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%(?:\d+\$)?(?:\.\d+)?[sdf]/', $text, $m);
        sort($m[0]);

        return $m[0];
    }

    /** @return array<int,string> */
    private static function expectedKeys(): array
    {
        $keys = array_keys(Messages::table('spa'));
        foreach (array_keys(require self::CONFIG . '/risk-categories.php') as $id) {
            array_push($keys, "category.$id.name", "category.$id.description");
        }

        return $keys;
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheSameKeysAndPlaceholdersAsSpanish(string $locale): void
    {
        $table = Messages::table($locale);
        $expected = self::expectedKeys();

        $this->assertSame([], array_values(array_diff($expected, array_keys($table))), "$locale: faltan claves");
        $this->assertSame([], array_values(array_diff(array_keys($table), $expected)), "$locale: sobran claves");
        foreach ($table as $key => $text) {
            $this->assertNotSame('', trim($text), "$locale $key vacío");
            if (isset(Messages::table('spa')[$key])) {
                $this->assertSame(self::placeholders(Messages::table('spa')[$key]), self::placeholders($text), "$locale $key");
            }
        }
    }

    public function testExplanationsAndReportSpeakTheRequestedLanguage(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG, 'spa');
        $result = $reviewer->validateFullName('Elba', 'Gina');

        $this->assertSame($result->getExplanations(), $result->getExplanations('spa'));
        [$english] = $result->getExplanations('eng');
        $this->assertStringContainsString('“Elba Gina” read together sounds like “vagina”', $english);
        $this->assertStringContainsString('dictionary spa', $english);

        $report = $reviewer->getDetailedReport($result, 'deu');
        $this->assertStringStartsWith('Menschliche Prüfung', $report['recommendation']);
        $this->assertSame($result->getExplanations('deu'), $report['explanations']);
        $this->assertSame('Vor- und Nachname', substr($report['riskAnalysis']['fonetico']['description'], 0, 17));
        $this->assertSame($reviewer->getDetailedReport($result), $reviewer->getDetailedReport($result, 'spa'));
    }

    public function testUnknownOrUnsafeLocaleFallsBackToSpanish(): void
    {
        $result = DefamatoryContentReviewer::create(self::CONFIG, 'spa')->validateFullName('Elba', 'Gina');

        foreach (['xxx', '../spa', ''] as $locale) {
            $this->assertSame($result->getExplanations(), $result->getExplanations($locale));
        }
    }
}
