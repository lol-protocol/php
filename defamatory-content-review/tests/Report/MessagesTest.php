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
        preg_match_all('/%(?:\d+\$)?(?:\.\d+)?[sdfF]/', $text, $m);
        sort($m[0]);

        return $m[0];
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheSameKeysAndPlaceholdersAsSpanish(string $locale): void
    {
        $table = Messages::table($locale);
        $expected = Messages::requiredKeys();

        $this->assertSame([], array_values(array_diff($expected, array_keys($table))), "$locale: faltan claves");
        $this->assertSame([], array_values(array_diff(array_keys($table), $expected, Messages::OPTIONAL_KEYS)), "$locale: sobran claves");
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

    public function testBrokenTemplateFallsBackToSpanishInsteadOfThrowing(): void
    {
        $dir = sys_get_temp_dir() . '/msgs-' . bin2hex(random_bytes(4));
        mkdir($dir . '/messages', 0777, true);
        copy(self::CONFIG . '/messages/spa.php', $dir . '/messages/spa.php');
        file_put_contents($dir . '/messages/eng.php', "<?php return ['error.cuerpo_demasiado_grande' => 'Too big: %s and %s bytes.'];");
        $messages = $dir . '/messages';

        $this->assertSame('El cuerpo supera 10 bytes.', Messages::get('error.cuerpo_demasiado_grande', 'eng', [10], $messages));
        $this->assertSame('no.existe', Messages::get('no.existe', 'eng', [10], $messages));
    }

    public function testSpanishCategoriesComeFromRiskCategories(): void
    {
        $this->assertSame('Animal', Messages::find('category.animal.name', 'spa'));
        $this->assertArrayNotHasKey('category.animal.name', require self::CONFIG . '/messages/spa.php');
    }
}
