<?php

namespace DefamatoryContentReview\Http;

/**
 * Cuántas peticiones puede hacer un mismo cliente en una ventana de tiempo, sin
 * base de datos: un archivo por cliente en una carpeta. El cliente (su IP) se
 * guarda como HMAC con una clave propia del servidor, así la dirección no queda
 * escrita en disco. Lo usa public/moderar.php; la idea es la del RateLimiter de
 * Marcalibros.
 */
final class RateLimiter
{
    private ?string $secret = null;

    public function __construct(private readonly string $directory) { }

    /**
     * Registra una petición. Devuelve false si el cliente ya llegó al límite.
     *
     * @throws \RuntimeException si no se puede escribir en la carpeta
     */
    public function attempt(string $client, int $limit, int $windowSeconds, int $now): bool
    {
        $handle = @fopen($this->fileFor($client), 'c+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el registro de peticiones.');
        }
        try {
            flock($handle, LOCK_EX);
            $stored = json_decode((string) stream_get_contents($handle), true);
            $times = array_values(array_filter(is_array($stored) ? $stored : [], fn($t): bool => is_int($t) && $t > $now - $windowSeconds));
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

    /** Segundos hasta que se libere una petición (para la cabecera Retry-After). */
    public function retryAfter(string $client, int $windowSeconds, int $now): int
    {
        $stored = json_decode((string) @file_get_contents($this->fileFor($client)), true);
        $times = is_array($stored) ? array_filter($stored, 'is_int') : [];

        return $times === [] ? 1 : max(1, min($times) + $windowSeconds - $now);
    }

    private function fileFor(string $client): string
    {
        return $this->directory . '/' . substr(hash_hmac('sha256', $client, $this->secret()), 0, 40) . '.json';
    }

    private function secret(): string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No se pudo crear la carpeta del límite de peticiones.');
        }
        $path = $this->directory . '/.clave';
        $stored = @file_get_contents($path);
        if ($stored === false || strlen($stored) < 32) {
            $stored = bin2hex(random_bytes(32));
            $handle = @fopen($path, 'x'); // «x» no pisa una clave que otro proceso creó un instante antes
            if ($handle !== false) {
                fwrite($handle, $stored);
                fclose($handle);
                chmod($path, 0600);
            } else {
                $stored = (string) @file_get_contents($path) ?: $stored;
            }
        }

        return $this->secret = $stored;
    }
}
