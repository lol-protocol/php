<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\ModerationEndpoint;
use PHPUnit\Framework\TestCase;

/** El idioma lo elige quien escribe: el endpoint revisa además los de la plataforma. */
class ModerationLanguagesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    public function testChoosingAnotherLanguageDoesNotSmuggleAnInsult(): void
    {
        $body = $this->post(new ModerationEndpoint(self::CONFIG_DIR), 'Fulano es un estafador, hijo de puta', 'eng');

        $this->assertSame(['eng', 'spa'], $body['checkedLanguages']);
        $this->assertSame('reject', $body['decision']);
    }

    public function testPlatformLanguagesAreConfigurable(): void
    {
        $body = $this->post(new ModerationEndpoint(self::CONFIG_DIR, 'por', ['por']), 'hola', 'ita');

        $this->assertSame(['ita', 'por'], $body['checkedLanguages']);
        $this->assertSame('approve', $body['decision']);
    }

    public function testOneFindingPerOccurrenceWithAllItsTypes(): void
    {
        $body = $this->post(new ModerationEndpoint(self::CONFIG_DIR), 'quiero follar, puta puta', 'spa');
        $follar = array_values(array_filter($body['matches'], fn(array $m): bool => $m['term'] === 'follar'));

        $this->assertCount(1, $follar);
        $this->assertSame(['difamatorio', 'sexual'], $follar[0]['contentTypes']);
        $this->assertCount(2, array_filter($body['matches'], fn(array $m): bool => $m['term'] === 'puta'), 'una repetición real cuenta dos veces');
    }

    /** @return array<string,mixed> */
    private function post(ModerationEndpoint $endpoint, string $text, string $language): array
    {
        return $endpoint->handle('POST', (string) json_encode(['text' => $text, 'language' => $language]))['body'];
    }
}
