<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Animales, discapacidad y las entradas `'targeted' => true` sólo cuentan
 * dirigidos a alguien: «eres un cerdo» sí, «comí cerdo» no (TargetContext).
 */
class TargetContextTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, decisión */
    public static function targetedLines(): array
    {
        return [
            'eres un cerdo' => ['spa', 'eres un cerdo', 'review'],
            'tercera persona' => ['spa', 'Juan es un cerdo', 'review'],
            'demostrativo' => ['spa', 'esa rata me robó', 'review'],
            'después: de mierda' => ['spa', 'eres un cojo de mierda', 'reject'],
            'sólo el término' => ['spa', '¡Cerdo!', 'review'],
            'con otro insulto' => ['spa', 'cállate, idiota, cerdo', 'review'],
            'bruja dirigida' => ['spa', 'eres una bruja', 'reject'],
            'apellido dirigido (whenTargeted)' => ['spa', 'eres una cerda', 'reject'],
            'apellido dirigido, con otro insulto' => ['spa', 'idiota, eres una cerda', 'reject'],
            'you pig' => ['eng', "you're such a pig", 'review'],
            'seu porco' => ['por', 'seu porco', 'review'],
            'sale porc' => ['fra', 'sale porc', 'review'],
            'du Schwein' => ['deu', 'du Schwein', 'review'],
            'ты свинья' => ['rus', 'ты свинья', 'review'],
            'dasar babi' => ['ind', 'dasar babi', 'review'],
            'aso ka' => ['tgl', 'aso ka', 'review'],
            'يا حمار' => ['ara', 'يا حمار', 'review'],
            'तू सूअर' => ['hin', 'तू सूअर', 'reject'],
        ];
    }

    /** @return array<string,array{string,string}> */
    public static function untargetedLines(): array
    {
        return [
            'comida' => ['spa', 'Comí cerdo asado'],
            'animal en casa' => ['spa', 'vi una rata en la cocina'],
            'discapacidad descrita' => ['spa', 'mi abuelo es cojo desde la guerra'],
            'cuento' => ['spa', 'la bruja del cuento'],
            'apellido' => ['spa', 'hablé con María Cerda ayer'],
            'barn' => ['eng', 'the cow is in the barn'],
            'porc' => ['fra', 'le porc est bon'],
            'Stall' => ['deu', 'das Schwein im Stall'],
            'ферма' => ['rus', 'свинья на ферме'],
            'hutan' => ['ind', 'babi hutan di hutan'],
            'aso namin' => ['tgl', 'may aso kami'],
            'identidad' => ['deu', 'Mein Großvater war Jude'],
            'palabrota suave' => ['rus', 'Чёрт, опять дождь'],
        ];
    }

    #[DataProvider('targetedLines')]
    public function testTermAimedAtSomeoneCounts(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }

    #[DataProvider('untargetedLines')]
    public function testTermNotAimedAtAnyoneIsOnlyReported(string $language, string $line): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertSame('approve', $result->getDecision(), $line);
        $this->assertContains('untargeted', array_column($result->getMatches(), 'context'), $line);
    }

    public function testNamesStillFlagTheTermWithoutContext(): void
    {
        $this->assertFalse(DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa')->validateName('Bruja')->isValid());
    }

    /** @return array<string,array{string}> */
    public static function punctuatedInsults(): array
    {
        return ['exclamación' => ['puta!'], 'interrogación' => ['idiota?'], 'signos de apertura' => ['¡idiota!'],
            'paréntesis' => ['(imbécil)'], 'comillas' => ['«imbécil»'], 'varios' => ['hijo de puta!!!']];
    }

    #[DataProvider('punctuatedInsults')]
    public function testPunctuationAroundAWordDoesNotHideIt(string $line): void
    {
        $this->assertNotSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line)->getDecision(), $line);
    }
}
