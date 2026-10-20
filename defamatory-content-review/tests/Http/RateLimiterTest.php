<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\RateLimiter;
use PHPUnit\Framework\TestCase;

/** Límite de peticiones por cliente de public/moderar.php: sin base de datos y sin guardar la IP. */
class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/moderar-limites-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testAllowsUpToTheLimitInsideTheWindow(): void
    {
        $limiter = new RateLimiter($this->dir);

        $this->assertTrue($limiter->attempt('203.0.113.7', 2, 60, 1000));
        $this->assertTrue($limiter->attempt('203.0.113.7', 2, 60, 1010));
        $this->assertFalse($limiter->attempt('203.0.113.7', 2, 60, 1020));
        $this->assertSame(40, $limiter->retryAfter('203.0.113.7', 60, 1020));
        $this->assertTrue($limiter->attempt('198.51.100.9', 2, 60, 1020), 'otro cliente tiene su propio límite');
        $this->assertTrue($limiter->attempt('203.0.113.7', 2, 60, 1061), 'pasada la ventana, se libera');
    }

    public function testTheAddressIsNotWrittenToDisk(): void
    {
        (new RateLimiter($this->dir))->attempt('203.0.113.7', 5, 60, 1000);

        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            $this->assertStringNotContainsString('203.0.113.7', basename($file) . (string) @file_get_contents($file));
        }
    }

    public function testTheEndpointAnswers429WithRetryAfter(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $env = ['MODERAR_POR_MINUTO' => '2', 'MODERAR_LIMITES_DIR' => $this->dir] + getenv();
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", dirname(__DIR__, 2) . '/public/router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__, 2), $env);
        $this->assertNotFalse($server);
        for ($try = 0; $try < 50 && !@fsockopen('127.0.0.1', $port); $try++) {
            usleep(100000);
        }
        try {
            $statuses = array_map(fn(): string => $this->post($port)[0], range(1, 3));

            $this->assertSame(['200', '200', '429'], $statuses);
            $this->assertMatchesRegularExpression('/\r\nRetry-After: \d+\r\n/i', $this->post($port)[1]);
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
    }

    /** @return array{0:string,1:string} estado y cabeceras */
    private function post(int $port): array
    {
        $socket = stream_socket_client("tcp://127.0.0.1:$port", $errno, $error, 5);
        $this->assertNotFalse($socket, $error);
        fwrite($socket, "POST /moderar HTTP/1.0\r\nHost: x\r\nContent-Type: application/json\r\nContent-Length: 15\r\n\r\n{\"text\":\"hola\"}");
        $head = explode("\r\n\r\n", (string) stream_get_contents($socket), 2)[0];
        fclose($socket);

        return [explode(' ', $head)[1], $head . "\r\n"];
    }
}
