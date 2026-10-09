<?php

namespace Tests\Http;

use PHPUnit\Framework\TestCase;

/** public/router.php y public/moderar.php de punta a punta, con el servidor embebido de PHP. */
class ModerationServerTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $root = dirname(__DIR__, 2);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, $root . '/public/router.php'];
        self::$server = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root) ?: null;
        for ($try = 0; $try < 50 && !@fsockopen('127.0.0.1', self::$port); $try++) {
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public function testModeratesAPostedLineAsJson(): void
    {
        [$status, $headers, $body] = $this->request('POST', '/moderar', '{"text":"te voy a matar","language":"spa"}');

        $this->assertSame(200, $status);
        $this->assertSame('application/json; charset=utf-8', $headers['content-type']);
        $this->assertSame('no-store', $headers['cache-control']);
        $this->assertSame('nosniff', $headers['x-content-type-options']);
        $this->assertSame("default-src 'none'; frame-ancestors 'none'", $headers['content-security-policy']);
        $this->assertSame('reject', json_decode($body, true)['decision']);
    }

    /** Un formulario de otra página llega como text/plain: no se atiende, y nada responde con permisos CORS. */
    public function testRefusesACrossSiteFormPost(): void
    {
        [$status, $headers, $body] = $this->request('POST', '/moderar', '{"text":"hola"}', 'text/plain');

        $this->assertSame(415, $status);
        $this->assertSame('tipo_no_soportado', json_decode($body, true)['error']['code']);
        $this->assertArrayNotHasKey('access-control-allow-origin', $headers);
    }

    public function testErrorsKeepTheirStatusAndHeaders(): void
    {
        [$status, $headers, $body] = $this->request('GET', '/moderar');

        $this->assertSame(405, $status);
        $this->assertSame('POST', $headers['allow']);
        $this->assertSame('metodo_no_permitido', json_decode($body, true)['error']['code']);
    }

    public function testServesTheDemoAndTheFrontScriptOnly(): void
    {
        $this->assertStringContainsString('limit-repeated-letters.js', $this->request('GET', '/')[2]);
        $this->assertStringContainsString('limitRepeatedLetters', $this->request('GET', '/limit-repeated-letters.js')[2]);
        foreach (['/config/languages/spa.php', '/composer.json', '/moderar.php', '/../README.md'] as $path) {
            $this->assertSame(404, $this->request('GET', $path)[0], $path);
        }
    }

    /** @return array{0:int, 1:array<string,string>, 2:string} */
    private function request(string $method, string $path, string $body = '', string $type = 'application/json'): array
    {
        $socket = stream_socket_client('tcp://127.0.0.1:' . self::$port, $errno, $error, 5);
        $this->assertNotFalse($socket, "El servidor de prueba no responde: $error");
        fwrite($socket, "$method $path HTTP/1.0\r\nHost: 127.0.0.1\r\nContent-Type: $type\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body);
        [$head, $content] = explode("\r\n\r\n", (string) stream_get_contents($socket), 2) + [1 => ''];
        fclose($socket);
        $lines = explode("\r\n", $head);
        $headers = [];
        foreach (array_slice($lines, 1) as $line) {
            [$name, $value] = explode(':', $line, 2) + [1 => ''];
            $headers[strtolower($name)] = trim($value);
        }

        return [(int) explode(' ', $lines[0])[1], $headers, $content];
    }
}
