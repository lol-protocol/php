<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\ModerationEndpoint;
use PHPUnit\Framework\TestCase;

class LocalizedErrorsTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../../config';

    public function testAcceptLanguageNegotiationPicksTheHeaviestAvailable(): void
    {
        $endpoint = new ModerationEndpoint(self::CONFIG);

        $this->assertSame('por', $endpoint->negotiateLocale('pt-BR,pt;q=0.9,en;q=0.8'));
        $this->assertSame('por', $endpoint->negotiateLocale('en;q=0.1, pt;q=0.9'));
        $this->assertSame('eng', $endpoint->negotiateLocale('xx, en-US;q=0.8'));
        $this->assertSame('spa', $endpoint->negotiateLocale('en;q=0, sw'));
        $this->assertSame('deu', $endpoint->negotiateLocale('deu'));
        $this->assertSame('spa', $endpoint->negotiateLocale(''));
    }

    public function testMessagesComeFromTheGivenConfigDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/msgs-' . bin2hex(random_bytes(4));
        mkdir($dir . '/messages', 0777, true);
        copy(self::CONFIG . '/messages/spa.php', $dir . '/messages/spa.php');
        file_put_contents($dir . '/messages/eng.php', "<?php return ['error.metodo_no_permitido' => 'Custom.'];");
        foreach (['supported-languages.php', 'language-families.php'] as $f) {
            copy(self::CONFIG . '/' . $f, $dir . '/' . $f);
        }

        $this->assertSame('Custom.', (new ModerationEndpoint($dir))->handle('GET', '', 'application/json', 'eng')['body']['error']['message']);
        $this->assertSame('Use POST.', (new ModerationEndpoint(self::CONFIG))->handle('GET', '', 'application/json', 'eng')['body']['error']['message']);
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
