<?php

namespace MarcalibrosApp;

/**
 * Limita cuántas veces puede hacer algo una misma persona en un rato, sin cuentas
 * ni base de datos. Cada persona se identifica por un resumen de su dirección IP
 * (HMAC con una clave propia del servidor), así la dirección no queda guardada.
 */
final class RateLimiter
{
    private ?string $secret = null;

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Registra un intento. Devuelve false si ya se llegó al límite en la ventana.
     *
     * @throws \RuntimeException si no se puede escribir en la carpeta
     */
    public function attempt(string $subject, int $limit, int $windowSeconds, int $now): bool
    {
        $this->ensureDirectory();
        $handle = @fopen($this->fileFor($subject), 'c+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el registro de intentos.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('No se pudo bloquear el registro de intentos.');
            }
            $stored = json_decode((string) stream_get_contents($handle), true);
            $times = array_values(array_filter(
                is_array($stored) ? $stored : [],
                static fn ($t): bool => is_int($t) && $t > $now - $windowSeconds
            ));
            $allowed = count($times) < $limit;
            if ($allowed) {
                $times[] = $now;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($times));
            return $allowed;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Segundos hasta que se libere un intento (para la cabecera Retry-After). */
    public function retryAfter(string $subject, int $windowSeconds, int $now): int
    {
        $stored = json_decode((string) @file_get_contents($this->fileFor($subject)), true);
        $times = is_array($stored) ? array_filter($stored, 'is_int') : [];
        return $times === [] ? 1 : max(1, min($times) + $windowSeconds - $now);
    }

    /** Borra los registros que ya no sirven (todos sus intentos son más viejos que la ventana). */
    public function purge(int $windowSeconds, int $now): int
    {
        $deleted = 0;
        foreach (glob("$this->directory/*.json") ?: [] as $file) {
            if ((int) @filemtime($file) < $now - $windowSeconds && @unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    private function fileFor(string $subject): string
    {
        return $this->directory . '/' . substr(hash_hmac('sha256', $subject, $this->secret()), 0, 40) . '.json';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No se pudo crear la carpeta de intentos.');
        }
    }

    private function secret(): string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }
        $this->ensureDirectory();
        $path = $this->directory . '/.clave';
        $stored = @file_get_contents($path);
        if ($stored === false || strlen($stored) < 32) {
            $stored = bin2hex(random_bytes(32));
            // "x" no pisa una clave que otro proceso haya creado un instante antes.
            $handle = @fopen($path, 'x');
            if ($handle !== false) {
                fwrite($handle, $stored);
                fclose($handle);
                chmod($path, 0600);
            } else {
                $stored = (string) @file_get_contents($path);
            }
        }
        if (strlen($stored) < 32) {
            throw new \RuntimeException('No se pudo leer la clave del registro de intentos.');
        }
        return $this->secret = $stored;
    }
}
