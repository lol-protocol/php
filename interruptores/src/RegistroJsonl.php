<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

/**
 * Registro de eventos en JSON Lines: una línea por evento, sólo se añade.
 *
 *  - anotar() bloquea el archivo, y si ya pasó el límite de tamaño lo aparta
 *    (p. ej. cambios-20261118T201500Z-1a2b.jsonl) y empieza uno nuevo.
 *  - ultimas() lee desde el final, así que el coste no depende del tamaño del archivo.
 *
 * Solo se ven los eventos del archivo actual; los apartados quedan en la misma carpeta.
 */
final class RegistroJsonl
{
    public const LIMITE_BYTES = 5_000_000;
    private const BLOQUE = 8192;

    public function __construct(
        private readonly string $ruta,
        private readonly int $limiteBytes = self::LIMITE_BYTES,
    ) {
    }

    /** @param array<string, mixed> $evento */
    public function anotar(array $evento): void
    {
        $dir = dirname($this->ruta);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se puede crear {$dir}");
        }
        $linea = json_encode($evento, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        $fh = fopen($this->ruta . '.lock', 'c');
        if ($fh === false || !flock($fh, LOCK_EX)) {
            throw new \RuntimeException('No se pudo bloquear el registro.');
        }
        try {
            clearstatcache(true, $this->ruta);
            if (is_file($this->ruta) && filesize($this->ruta) >= $this->limiteBytes) {
                $apartado = $this->apartado();
                if (!rename($this->ruta, $apartado)) {
                    throw new \RuntimeException("No se pudo apartar el registro en {$apartado}");
                }
            }
            if (file_put_contents($this->ruta, $linea, FILE_APPEND) === false) {
                throw new \RuntimeException('No se pudo anotar en el registro.');
            }
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Los últimos $n eventos del archivo actual, el más nuevo primero.
     *
     * @return list<array<string, mixed>>
     */
    public function ultimas(int $n): array
    {
        // PHP guarda en caché el tamaño que vio al último stat(): sin esto, un anotar() previo
        // en el mismo proceso hace que se pierda la última línea.
        clearstatcache(true, $this->ruta);
        if ($n <= 0 || !is_file($this->ruta)) {
            return [];
        }
        $fh = fopen($this->ruta, 'rb');
        if ($fh === false) {
            return [];
        }
        $pos = (int) filesize($this->ruta);
        $buf = '';
        $lineas = [];
        try {
            while ($pos > 0 && count($lineas) <= $n + 1) {
                $leer = min(self::BLOQUE, $pos);
                $pos -= $leer;
                fseek($fh, $pos);
                $buf = (string) fread($fh, $leer) . $buf;
                $lineas = explode("\n", $buf);
            }
        } finally {
            fclose($fh);
        }
        if ($pos > 0) {
            array_shift($lineas); // el primer trozo puede empezar a mitad de una línea
        }

        $salida = [];
        for ($k = count($lineas) - 1; $k >= 0 && count($salida) < $n; $k--) {
            $linea = trim($lineas[$k]);
            if ($linea === '') {
                continue;
            }
            $evento = json_decode($linea, true);
            if (is_array($evento)) {
                $salida[] = $evento;
            }
        }
        return $salida;
    }

    private function apartado(): string
    {
        $base = pathinfo($this->ruta, PATHINFO_FILENAME);
        $ext = pathinfo($this->ruta, PATHINFO_EXTENSION);
        return dirname($this->ruta) . '/' . $base . '-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(2))
            . ($ext !== '' ? '.' . $ext : '');
    }
}
