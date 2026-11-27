<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

/**
 * Cambios programados: encender o apagar una función, o aplicar una escena, a una hora dada (UTC).
 *
 * Nada se ejecuta solo: `php bin/programados.php ejecutar` aplica lo vencido y se pone en un cron (cada minuto).
 * Cada cambio programado queda `pendiente`, y pasa a `hecho`, `error` (con el motivo: por ejemplo, que ya no
 * se pueda aplicar sin romper una dependencia) o `cancelado`. Un error no se reintenta.
 *
 * Archivo: var/programados.json. Los terminados se conservan hasta MAX_TERMINADOS.
 */
final class Programados
{
    public const MAX_PENDIENTES = 200;
    public const MAX_TERMINADOS = 100;
    public const MAX_ADELANTO_SEGUNDOS = 366 * 86400;

    /** @var (\Closure(): int)|null */
    private ?\Closure $reloj;

    /** @param (\Closure(): int)|null $reloj solo para pruebas */
    public function __construct(private readonly string $ruta, ?\Closure $reloj = null)
    {
        $this->reloj = $reloj;
    }

    /**
     * Programa una escena (`['escena' => id]`) o un cambio (`['ref' => ref, 'valor' => bool]`).
     *
     * @param array<string, mixed> $accion
     * @param int $cuando marca de tiempo UTC
     * @return string el id del cambio programado
     */
    public function agendar(int $cuando, array $accion, string $quien): string
    {
        $accion = $this->normalizar($accion);
        $ahora = $this->ahora();
        if ($cuando <= $ahora) {
            throw new \InvalidArgumentException('La hora tiene que ser futura (UTC).');
        }
        if ($cuando > $ahora + self::MAX_ADELANTO_SEGUNDOS) {
            throw new \InvalidArgumentException('No se programa con más de un año de adelanto.');
        }
        $id = bin2hex(random_bytes(4));
        $quien = substr((string) preg_replace('/[[:cntrl:]]/', '', $quien), 0, 64);
        $this->modificar(function (array $lista) use ($id, $cuando, $accion, $quien, $ahora): array {
            $pendientes = count(array_filter($lista, static fn (array $p): bool => $p['estado'] === 'pendiente'));
            if ($pendientes >= self::MAX_PENDIENTES) {
                throw new \RuntimeException('Hay demasiados cambios programados pendientes.');
            }
            $lista[] = ['id' => $id, 'cuando' => $cuando, 'accion' => $accion, 'creado_por' => $quien,
                'creado' => $ahora, 'estado' => 'pendiente', 'resultado' => ''];
            return $lista;
        });
        return $id;
    }

    /** @return list<array<string, mixed>> todos, por hora; los pendientes primero */
    public function listar(): array
    {
        $lista = $this->leer();
        usort($lista, static function (array $a, array $b): int {
            return [$a['estado'] === 'pendiente' ? 0 : 1, $a['cuando']] <=> [$b['estado'] === 'pendiente' ? 0 : 1, $b['cuando']];
        });
        return $lista;
    }

    /** @throws \RuntimeException si no existe o ya no está pendiente */
    public function cancelar(string $id): void
    {
        $this->modificar(function (array $lista) use ($id): array {
            foreach ($lista as &$p) {
                if ($p['id'] === $id) {
                    if ($p['estado'] !== 'pendiente') {
                        throw new \RuntimeException("El cambio «{$id}» ya no está pendiente ({$p['estado']}).");
                    }
                    $p['estado'] = 'cancelado';
                    return $lista;
                }
            }
            throw new \RuntimeException("No existe el cambio programado «{$id}».");
        });
    }

    /**
     * Aplica lo vencido, en orden. Un error de uno no frena a los demás.
     *
     * @return list<array{id: string, estado: string, resultado: string}>
     */
    public function ejecutarVencidos(Interruptores $interruptores, Escenas $escenas): array
    {
        $hechos = [];
        $this->modificar(function (array $lista) use ($interruptores, $escenas, &$hechos): array {
            $ahora = $this->ahora();
            usort($lista, static fn (array $a, array $b): int => $a['cuando'] <=> $b['cuando']);
            foreach ($lista as &$p) {
                if ($p['estado'] !== 'pendiente' || $p['cuando'] > $ahora) {
                    continue;
                }
                $quien = 'programado:' . $p['creado_por'];
                try {
                    if (isset($p['accion']['escena'])) {
                        $cambiados = $escenas->aplicar($p['accion']['escena'], $quien);
                    } else {
                        $cambiados = $interruptores->aplicar([$p['accion']['ref'] => $p['accion']['valor']], $quien, 'programado:' . $p['id']);
                    }
                    $p['estado'] = 'hecho';
                    $p['resultado'] = count($cambiados) . ' interruptor(es) cambiado(s)';
                } catch (\Throwable $e) {
                    $p['estado'] = 'error';
                    $p['resultado'] = substr($e->getMessage(), 0, 300);
                }
                $p['ejecutado'] = $ahora;
                $hechos[] = ['id' => $p['id'], 'estado' => $p['estado'], 'resultado' => $p['resultado']];
            }
            unset($p);
            return $this->podar($lista);
        });
        return $hechos;
    }

    // --- internas --------------------------------------------------------------

    /**
     * @param array<string, mixed> $accion
     * @return array<string, mixed>
     */
    private function normalizar(array $accion): array
    {
        if (isset($accion['escena'])) {
            return ['escena' => (string) $accion['escena']];
        }
        if (isset($accion['ref']) && array_key_exists('valor', $accion)) {
            return ['ref' => (string) $accion['ref'], 'valor' => (bool) $accion['valor']];
        }
        throw new \InvalidArgumentException('Hay que dar una escena, o una ref con su valor.');
    }

    /**
     * @param list<array<string, mixed>> $lista
     * @return list<array<string, mixed>>
     */
    private function podar(array $lista): array
    {
        $terminados = array_values(array_filter($lista, static fn (array $p): bool => $p['estado'] !== 'pendiente'));
        if (count($terminados) <= self::MAX_TERMINADOS) {
            return $lista;
        }
        usort($terminados, static fn (array $a, array $b): int => $b['cuando'] <=> $a['cuando']);
        $quedan = array_flip(array_column(array_slice($terminados, 0, self::MAX_TERMINADOS), 'id'));
        return array_values(array_filter($lista, static fn (array $p): bool => $p['estado'] === 'pendiente' || isset($quedan[$p['id']])));
    }

    private function ahora(): int
    {
        return $this->reloj !== null ? ($this->reloj)() : time();
    }

    /** @return list<array<string, mixed>> */
    private function leer(): array
    {
        if (!is_file($this->ruta)) {
            return [];
        }
        $datos = json_decode((string) file_get_contents($this->ruta), true);
        if (!is_array($datos)) {
            throw new \RuntimeException("El archivo de cambios programados está dañado: {$this->ruta}");
        }
        return array_values($datos);
    }

    /** @param callable(list<array<string, mixed>>): list<array<string, mixed>> $cambio */
    private function modificar(callable $cambio): void
    {
        $dir = dirname($this->ruta);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se puede crear {$dir}");
        }
        $fh = fopen($this->ruta . '.lock', 'c');
        if ($fh === false || !flock($fh, LOCK_EX)) {
            throw new \RuntimeException('No se pudo bloquear los cambios programados.');
        }
        try {
            $nueva = $cambio($this->leer());
            $json = json_encode(array_values($nueva), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            $temp = $this->ruta . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($temp, $json) === false || !rename($temp, $this->ruta)) {
                @unlink($temp);
                throw new \RuntimeException("No se pudo guardar {$this->ruta}");
            }
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
