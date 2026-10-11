<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\Config;
use MarcalibrosApp\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private function config(bool $trust = false): Config
    {
        return new Config('/p', '/s', trustForwardedFor: $trust);
    }

    /** @param array<string, mixed> $server */
    private function fromServer(array $server, bool $trust = false, array $post = [], array $files = []): Request
    {
        return Request::fromGlobals($this->config($trust), $server, [], $post, $files);
    }

    /** @return array<string, array{string, string}> */
    public static function paths(): array
    {
        return [
            'raíz' => ['/', '/'],
            'con consulta' => ['/?categoria=leyes', '/'],
            'barra final' => ['/crear/', '/crear'],
            'varias barras finales' => ['/crear///', '/crear'],
            'codificada' => ['/mio/mp-1%2Fx', '/mio/mp-1/x'],
            'sin barra inicial' => ['crear', '/crear'],
            'UTF-8 inválido' => ['/%FF%FE', '/ruta-invalida'],
            'byte nulo' => ['/a%00b', '/ruta-invalida'],
        ];
    }

    #[DataProvider('paths')]
    public function testPathIsNormalised(string $uri, string $expected): void
    {
        $this->assertSame($expected, $this->fromServer(['REQUEST_URI' => $uri])->path);
    }

    public function testMethodIsUppercased(): void
    {
        $this->assertSame('POST', $this->fromServer(['REQUEST_METHOD' => 'post'])->method);
    }

    public function testHeadersComeFromServerVariables(): void
    {
        $request = $this->fromServer(['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_IF_NONE_MATCH' => '"a"', 'CONTENT_TYPE' => 'x']);
        $this->assertSame('cross-site', $request->header('Sec-Fetch-Site'));
        $this->assertSame('"a"', $request->header('if-none-match'));
        $this->assertSame(null, $request->header('content-type'));
    }

    public function testClientIpIgnoresForwardedForByDefault(): void
    {
        $request = $this->fromServer(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8']);
        $this->assertSame('203.0.113.9', $request->clientIp);
    }

    public function testClientIpUsesTheLastForwardedAddressWhenTrusted(): void
    {
        $request = $this->fromServer(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 198.51.100.4'], true);
        $this->assertSame('198.51.100.4', $request->clientIp);
    }

    public function testInvalidForwardedAddressFallsBackToRemoteAddress(): void
    {
        $request = $this->fromServer(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'no-es-una-ip'], true);
        $this->assertSame('10.0.0.1', $request->clientIp);
    }

    public function testMissingRemoteAddressBecomesZeros(): void
    {
        $this->assertSame('0.0.0.0', $this->fromServer([])->clientIp);
        $this->assertSame('0.0.0.0', $this->fromServer(['REMOTE_ADDR' => 'basura'])->clientIp);
    }

    public function testBodyTooLargeNeedsADeclaredLengthOverThePostLimit(): void
    {
        $limit = Request::iniBytes((string) ini_get('post_max_size'));
        if ($limit === 0) {
            $this->markTestSkipped('post_max_size sin límite en este entorno.');
        }
        $over = $this->fromServer(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => (string) ($limit + 1)]);
        $this->assertTrue($over->bodyTooLarge);

        $under = $this->fromServer(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '1000']);
        $this->assertFalse($under->bodyTooLarge, 'Un cuerpo que no es un formulario no es un cuerpo demasiado grande.');

        $withData = $this->fromServer(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => (string) ($limit + 1)], false, ['a' => 'b']);
        $this->assertFalse($withData->bodyTooLarge);

        $get = $this->fromServer(['REQUEST_METHOD' => 'GET', 'CONTENT_LENGTH' => (string) ($limit + 1)]);
        $this->assertFalse($get->bodyTooLarge);
    }

    /** @return array<string, array{string, int}> */
    public static function iniValues(): array
    {
        return [
            'megabytes' => ['8M', 8_388_608],
            'kilobytes' => ['512K', 524_288],
            'gigabytes' => ['1G', 1_073_741_824],
            'minúscula' => ['2m', 2_097_152],
            'con espacio' => ['2 M', 2_097_152],
            'bytes' => ['1000', 1000],
            'sin límite' => ['0', 0],
            'menos uno' => ['-1', 0],
            'vacío' => ['', 0],
            'basura' => ['abc', 0],
        ];
    }

    #[DataProvider('iniValues')]
    public function testIniBytes(string $value, int $expected): void
    {
        $this->assertSame($expected, Request::iniBytes($value));
    }

    public function testFieldAndParamOnlyReturnText(): void
    {
        $request = new Request('POST', '/', ['a' => 'x', 'b' => ['y']], ['c' => 'z', 'd' => ['w'], 'e' => 5]);
        $this->assertSame('x', $request->param('a'));
        $this->assertSame(null, $request->param('b'));
        $this->assertSame(null, $request->param('missing'));
        $this->assertSame('z', $request->field('c'));
        $this->assertSame(null, $request->field('d'));
        $this->assertSame(null, $request->field('e'));
    }

    public function testUploadNormalisesTheFileEntry(): void
    {
        $request = new Request('POST', '/', files: ['imagen' => ['name' => 'a.jpg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10]]);
        $this->assertSame(['name' => 'a.jpg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10], $request->upload('imagen'));
        $this->assertSame(null, $request->upload('otro'));
    }

    public function testUploadWithSeveralFilesIsAnError(): void
    {
        $request = new Request('POST', '/', files: ['imagen' => ['name' => ['a', 'b'], 'tmp_name' => ['/x', '/y'], 'error' => [0, 0], 'size' => [1, 2]]]);
        $file = $request->upload('imagen');
        $this->assertNotNull($file);
        $this->assertSame(UPLOAD_ERR_PARTIAL, $file['error']);
    }
}
