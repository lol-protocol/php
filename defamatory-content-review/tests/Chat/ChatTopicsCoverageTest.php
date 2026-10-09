<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los 33 idiomas tienen lista de temas de chat (config/chat-topics/) y cada
 * una detecta una línea sexual, una amenaza, y deja pasar una línea cotidiana.
 */
class ChatTopicsCoverageTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,array<int,string>}> */
    public static function lines(): array
    {
        $cases = [];
        foreach (require __DIR__ . '/../fixtures/chat-lines-per-language.php' as $lang => $set) {
            $cases[$lang] = [$lang, $set];
        }

        return $cases;
    }

    public function testEveryLanguageHasATopicsFile(): void
    {
        $languages = array_keys(require self::CONFIG_DIR . '/supported-languages.php');
        $withTopics = array_map(fn(string $f): string => basename($f, '.php'), glob(self::CONFIG_DIR . '/chat-topics/*.php'));

        $this->assertSame([], array_values(array_diff($languages, $withTopics)), 'Idiomas sin config/chat-topics/.');
    }

    /** @param array<int,string> $set */
    #[DataProvider('lines')]
    public function testSexualThreatAndEverydayLines(string $lang, array $set): void
    {
        [$sexual, $threat, $everyday] = $set;
        $reviewer = ChatLineReviewer::create(self::CONFIG_DIR, $lang);

        $this->assertNotSame('approve', $reviewer->review($sexual)->getDecision(), "«{$sexual}» ({$lang}) debería censurarse.");
        $this->assertSame('reject', $reviewer->review($threat)->getDecision(), "«{$threat}» ({$lang}) debería bloquearse.");
        $this->assertSame('approve', $reviewer->review($everyday)->getDecision(), "«{$everyday}» ({$lang}) es una línea normal.");
    }
}
