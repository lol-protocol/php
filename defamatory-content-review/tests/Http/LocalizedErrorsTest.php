<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\ModerationEndpoint;
use DefamatoryContentReview\Report\Messages;
use PHPUnit\Framework\TestCase;

class LocalizedErrorsTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../../config';

    public function testAcceptLanguageNegotiationPicksTheFirstAvailable(): void
    {
        $this->assertSame('por', Messages::negotiate('pt-BR,pt;q=0.9,en;q=0.8'));
        $this->assertSame('eng', Messages::negotiate('xx, en-US;q=0.8'));
        $this->assertSame('deu', Messages::negotiate('deu'));
        $this->assertSame('spa', Messages::negotiate(''));
        $this->assertSame('spa', Messages::negotiate('sw, is;q=0.5'));
    }

    public function testEndpointErrorsComeInTheRequestedLanguage(): void
    {
        $endpoint = new ModerationEndpoint(self::CONFIG);

        $this->assertSame('Use POST.', $endpoint->handle('GET', '', 'application/json', 'eng')['body']['error']['message']);
        $this->assertSame('Usa POST.', $endpoint->handle('GET', '')['body']['error']['message']);
        $long = (string) json_encode(['text' => str_repeat('a', ModerationEndpoint::MAX_TEXT_CHARS + 1)]);
        $error = $endpoint->handle('POST', $long, 'application/json', 'jpn')['body']['error'];
        $this->assertSame('texto_demasiado_largo', $error['code']);
        $this->assertSame('テキストが 10000 文字を超えています。', $error['message']);
    }
}
