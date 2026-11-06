<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\TextMarks;
use PHPUnit\Framework\TestCase;

class TextMarksTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    public function testLabelsAnAccusationAndAnInsultInEitherLanguage(): void
    {
        $this->assertSame(['insulto', 'acusación de delito'], TextMarks::of('Ese hombre es un ladrón y una estafa.', self::CONFIG_DIR, ['spa', 'eng'])['marks']);
        $this->assertSame(['insulto', 'accusation of a crime'], TextMarks::of('He is a thief and a fraud.', self::CONFIG_DIR, ['spa', 'eng'])['marks']);
    }

    public function testATextWithNothingToMarkIsApproved(): void
    {
        $this->assertSame(['decision' => 'approve', 'marks' => []], TextMarks::of('Quiero que retiren la foto de mi abuela.', self::CONFIG_DIR, ['spa', 'eng']));
    }

    public function testTheReviewersAreReusedWithoutCarryingOneTextIntoTheNext(): void
    {
        $this->assertNotSame([], TextMarks::of('Es un ladrón.', self::CONFIG_DIR, ['spa'])['marks']);
        $this->assertSame([], TextMarks::of('Hola.', self::CONFIG_DIR, ['spa'])['marks']);
        $this->assertSame(TextMarks::of('Es un ladrón.', self::CONFIG_DIR, ['spa']), TextMarks::of('Es un ladrón.', self::CONFIG_DIR, ['spa']));
    }

    public function testALongTextIsReviewedInPieces(): void
    {
        $largo = str_repeat('Juan nació en Concepción en 1923, hijo de agricultores. ', 80) . "\n\nFue un ladrón.";

        $this->assertContains('insulto', TextMarks::of($largo, self::CONFIG_DIR, ['spa'])['marks']);
    }
}
