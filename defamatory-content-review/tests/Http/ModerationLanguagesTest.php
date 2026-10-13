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

    /** @return array<string,mixed> */
    private function post(ModerationEndpoint $endpoint, string $text, string $language): array
    {
        return $endpoint->handle('POST', (string) json_encode(['text' => $text, 'language' => $language]))['body'];
    }
}
