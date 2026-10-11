<?php

namespace Tests;

use DefamatoryContentReview\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Líneas reales de chat contra las listas de config/chat-topics/: lo que debe marcarse y lo que no. */
class ChatLineDetectionTest extends TestCase
{
    /** @var array<string,ChatLineReviewer> */
    private static array $reviewers = [];

    private static function review(string $language, string $line): array
    {
        $reviewer = self::$reviewers[$language] ??= ChatLineReviewer::create(__DIR__ . '/../config', $language);
        $result = $reviewer->review($line);
        $types = $result->getContentTypes();
        sort($types);

        return [$result->getDecision(), $types, json_encode($result->toArray(), JSON_UNESCAPED_UNICODE)];
    }

    public static function flaggedLines(): array
    {
        $cases = [];
        foreach (require __DIR__ . '/fixtures/chat-lines.php' as $language => $lines) {
            foreach ($lines['flagged'] as $line => [$decision, $types]) {
                $cases["$language: $line"] = [$language, (string) $line, $decision, $types];
            }
        }

        return $cases;
    }

    public static function cleanLines(): array
    {
        $cases = [];
        foreach (require __DIR__ . '/fixtures/chat-lines.php' as $language => $lines) {
            foreach ($lines['clean'] as $line) {
                $cases["$language: $line"] = [$language, $line];
            }
        }

        return $cases;
    }

    #[DataProvider('flaggedLines')]
    public function testFlaggedLinesGetTheExpectedDecisionAndTypes(string $language, string $line, string $decision, array $types): void
    {
        [$actualDecision, $actualTypes, $detail] = self::review($language, $line);

        $this->assertSame($decision, $actualDecision, $detail);
        sort($types);
        $this->assertSame($types, $actualTypes, $detail);
    }

    #[DataProvider('cleanLines')]
    public function testCleanLinesAreApproved(string $language, string $line): void
    {
        [$decision, , $detail] = self::review($language, $line);

        $this->assertSame('approve', $decision, $detail);
    }

    public function testCommonNamesInAGreetingAreNeverFlagged(): void
    {
        foreach (['spa', 'eng'] as $language) {
            [$firstNames, $lastNames] = (require __DIR__ . '/fixtures/common-names.php')[$language];
            foreach ($firstNames as $i => $first) {
                [$decision, $types, $detail] = self::review($language, "Hola $first {$lastNames[$i % count($lastNames)]}, ¿cómo estás?");

                $this->assertSame([], $types, $detail);
            }
        }
    }
}
