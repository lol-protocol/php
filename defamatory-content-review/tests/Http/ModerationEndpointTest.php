<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\ModerationEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModerationEndpointTest extends TestCase
{
    private ModerationEndpoint $endpoint;

    protected function setUp(): void
    {
        $this->endpoint = new ModerationEndpoint(dirname(__DIR__, 2) . '/config');
    }

    public function testRejectsAThreatWithItsMatchesAndTheCensoredLine(): void
    {
        $response = $this->post(['text' => 'te voy a matar, puta', 'language' => 'es']);

        $this->assertSame(200, $response['status']);
        $this->assertSame('spa', $response['body']['language']);
        $this->assertSame('reject', $response['body']['decision']);
        $this->assertTrue($response['body']['censor']);
        $this->assertSame(['difamatorio', 'belico'], $response['body']['contentTypes']);
        $this->assertSame('**************, ****', $response['body']['censored']);
        $this->assertSame(['found' => 'puta', 'term' => 'puta', 'contentType' => 'difamatorio', 'severity' => 'high'], $response['body']['matches'][0]);
    }

    public function testApprovesAnEverydayLineInTheDefaultLanguage(): void
    {
        $response = $this->post(['text' => 'Hola, ¿vamos a coger el bus?']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['language' => 'spa', 'decision' => 'approve', 'censor' => false, 'contentTypes' => [],
            'censored' => 'Hola, ¿vamos a coger el bus?', 'matches' => []], $response['body']);
    }

    public function testUsesTheRequestedLanguage(): void
    {
        $this->assertSame('review', $this->post(['text' => 'you idiot', 'language' => 'eng'])['body']['decision']);
        $this->assertSame('approve', $this->post(['text' => 'Bize katıl', 'language' => 'tur'])['body']['decision']);
    }

    public function testTextAtTheLimitIsReviewed(): void
    {
        $this->assertSame(200, $this->post(['text' => str_repeat('á', ModerationEndpoint::MAX_TEXT_CHARS)])['status']);
    }

    /** @return array<string,array{0:string,1:string,2:int,3:string}> */
    public static function invalidRequests(): array
    {
        $tooLong = json_encode(['text' => str_repeat('a', ModerationEndpoint::MAX_TEXT_CHARS + 1)]);
        return [
            'GET' => ['GET', '{"text":"hola"}', 405, 'metodo_no_permitido'],
            'cuerpo enorme' => ['POST', str_repeat(' ', ModerationEndpoint::MAX_BODY_BYTES + 1), 413, 'cuerpo_demasiado_grande'],
            'no es JSON' => ['POST', 'hola', 400, 'texto_requerido'],
            'sin text' => ['POST', '{"language":"spa"}', 400, 'texto_requerido'],
            'text no es string' => ['POST', '{"text":["hola"]}', 400, 'texto_requerido'],
            'lista JSON' => ['POST', '["hola"]', 400, 'texto_requerido'],
            'UTF-8 inválido' => ['POST', "{\"text\":\"\xC3\x28\"}", 400, 'texto_requerido'],
            'texto largo' => ['POST', (string) $tooLong, 413, 'texto_demasiado_largo'],
            'idioma desconocido' => ['POST', '{"text":"hola","language":"klingon"}', 400, 'idioma_no_soportado'],
            'idioma no string' => ['POST', '{"text":"hola","language":3}', 400, 'idioma_no_soportado'],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsGetAnErrorCode(string $method, string $body, int $status, string $code): void
    {
        $response = $this->endpoint->handle($method, $body);

        $this->assertSame($status, $response['status']);
        $this->assertSame($code, $response['body']['error']['code']);
        $this->assertNotSame('', $response['body']['error']['message']);
    }

    public function testOnlyAcceptsAJsonBody(): void
    {
        $types = ['text/plain' => 415, 'application/x-www-form-urlencoded' => 415, '' => 415, 'Application/JSON; charset=utf-8' => 200];
        foreach ($types as $type => $status) {
            $this->assertSame($status, $this->endpoint->handle('POST', '{"text":"hola"}', (string) $type)['status'], (string) $type);
        }
    }

    public function testMethodNotAllowedSaysWhichOneIs(): void
    {
        $this->assertSame(['Allow' => 'POST'], $this->endpoint->handle('PUT', '')['headers']);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{status:int, headers:array<string,string>, body:array<string,mixed>}
     */
    private function post(array $input): array
    {
        return $this->endpoint->handle('POST', (string) json_encode($input));
    }
}
