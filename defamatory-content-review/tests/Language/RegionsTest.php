<?php

namespace Tests\Language;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Language\Regions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Variantes de un idioma por país: ver Regions y la clave `regions` de config/languages/spa.php. */
class RegionsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    public function testSplitsTheCountryOffTheCode(): void
    {
        $this->assertSame(['es', 'MX'], Regions::split('es-mx'));
        $this->assertSame(['spa', 'AR'], Regions::split('spa_AR'));
        $this->assertSame(['es', '419'], Regions::split('es-419'));
        $this->assertSame(['spa', null], Regions::split('spa'));
        $this->assertSame(['es-', null], Regions::split('es-'));
    }

    public function testAnEntryOfTheCountryReplacesTheBaseOneWhereverItIs(): void
    {
        $config = ['words' => ['a' => [['word' => 'Polla', 'severity' => 'high'], ['word' => 'perra', 'severity' => 'high']], 'b' => ['zorra']],
            'regions' => ['CL' => ['c' => [['word' => 'polla', 'severity' => 'none'], ['word' => 'zorra', 'severity' => 'low'], ['word' => 'pico', 'severity' => 'medium']]]]];

        $this->assertSame(['words' => $config['words']], Regions::apply($config, null));
        $this->assertSame(['words' => $config['words']], Regions::apply($config, 'MX'), 'un país sin variante usa el idioma tal cual');
        $this->assertSame(['words' => [
            'a' => [['word' => 'perra', 'severity' => 'high']],
            'b' => [],
            'c' => [['word' => 'zorra', 'severity' => 'low'], ['word' => 'pico', 'severity' => 'medium']],
        ]], Regions::apply($config, 'CL'));
    }

    /** @return array<string,array{string,string,string}> idioma, línea, decisión */
    public static function lines(): array
    {
        return [
            'México: puto dirigido' => ['es-MX', 'eres un puto', 'reject'],
            'México: puto intensificador' => ['es-MX', 'los putos deberes', 'approve'],
            'base: puto no está' => ['spa', 'eres un puto', 'approve'],
            'España: puto intensificador' => ['es-ES', 'los putos deberes', 'approve'],
            'Argentina: trolo' => ['es-AR', 'sos un trolo', 'reject'],
            'Argentina: boludo entre amigos' => ['es-AR', 'boludo, vení', 'approve'],
            'base: boludo dirigido' => ['spa', 'eres un boludo', 'review'],
            'Argentina: coger' => ['es-AR', 'quiero coger con vos', 'review'],
            'base: coger es ambiguo' => ['spa', 'quiero coger con vos', 'approve'],
            'Chile: la polla es la lotería' => ['es-CL', 'hagamos una polla para el Mundial', 'approve'],
            'base: polla' => ['spa', 'hagamos una polla para el Mundial', 'reject'],
            'Chile: y pico' => ['es-CL', 'llego a las tres y pico', 'approve'],
            'Chile: pico acompañado' => ['es-CL', 'mándame tu pico, quiero follar', 'reject'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), "{$language}: {$line}");
    }

    public function testEveryVariantUsesTheDictionaryFormat(): void
    {
        foreach (glob(self::CONFIG_DIR . '/{languages,chat-topics}/*.php', GLOB_BRACE) ?: [] as $file) {
            foreach ((require $file)['regions'] ?? [] as $region => $categories) {
                $this->assertMatchesRegularExpression('/^([A-Z]{2}|\d{3})$/', (string) $region, $file);
                foreach ($categories as $entries) {
                    foreach ($entries as $entry) {
                        $this->assertContains($entry['severity'], ['low', 'medium', 'high', 'none'], "{$file} {$region} {$entry['word']}");
                        $this->assertNotSame('', $entry['riskType'] ?? '', "{$file} {$region} {$entry['word']}");
                    }
                }
            }
        }
    }
}
