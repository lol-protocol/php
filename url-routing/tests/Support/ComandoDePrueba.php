<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Runs a script of bin/ the way whoever operates the site does: a subprocess with a clean environment, and
 * its exit code and both output streams back. Use it from a class that extends TestCase.
 */
trait ComandoDePrueba
{
    /**
     * @param string $script the file in bin/, without .php
     * @param list<string> $args
     * @param array<string, string> $entorno environment of the process, over PATH
     * @param list<string> $php options for the php binary itself (-d ...)
     * @return array{0: int, 1: string, 2: string} exit code, stdout, stderr
     */
    private function ejecutarComando(string $script, array $args = [], array $entorno = [], array $php = []): array
    {
        $proceso = proc_open(
            [PHP_BINARY, ...$php, dirname(__DIR__, 2) . "/bin/{$script}.php", ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $entorno + ['PATH' => (string)getenv('PATH')]
        );
        $this->assertIsResource($proceso);
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proceso), (string)$salida, (string)$error];
    }
}
