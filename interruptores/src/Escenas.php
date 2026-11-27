<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

/**
 * Escenas: conjuntos de cambios con nombre (mantenimiento, apagado de emergencia, campaña…), definidos en escenas.json.
 *
 * Una escena puede:
 *  - `apagar` y `encender`: listas de refs (una función o una opción);
 *  - `apagar_tipos`: apagar todas las funciones de esos tipos (producto, interno…);
 *  - `mantener`: refs que la escena no apaga aunque entren por `apagar` o `apagar_tipos`;
 *  - `confirmar`: el panel pide una confirmación antes de aplicarla.
 *
 * Aplicar una escena es una sola operación (Interruptores::aplicar): se valida el estado final y, si rompería algo,
 * no cambia nada. Se recuerda lo que cambió para poder deshacer la última (var/ultima-escena.json).
 */
final class Escenas
{
    /** @var array<string, array<string, mixed>> */
    private array $escenas = [];

    public function __construct(
        string $rutaEscenas,
        private readonly Interruptores $interruptores,
        private readonly string $rutaUltima,
    ) {
        $crudo = @file_get_contents($rutaEscenas);
        if ($crudo === false) {
            throw new \RuntimeException("No se puede leer las escenas: {$rutaEscenas}");
        }
        $datos = json_decode($crudo, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($datos) || !isset($datos['escenas']) || !is_array($datos['escenas'])) {
            throw new \RuntimeException('escenas.json no tiene la lista «escenas».');
        }
        $tipos = array_unique(array_column($interruptores->catalogo(), 'tipo'));
        $refs = array_flip(array_keys($interruptores->interruptores()));
        foreach ($datos['escenas'] as $e) {
            $id = (string) ($e['id'] ?? '');
            if (preg_match('/^[a-z0-9-]{2,32}$/', $id) !== 1 || isset($this->escenas[$id])) {
                throw new \RuntimeException("Escena sin id válido o repetida: «{$id}».");
            }
            $e['nombre'] = (string) ($e['nombre'] ?? $id);
            $e['descripcion'] = (string) ($e['descripcion'] ?? '');
            $e['confirmar'] = (bool) ($e['confirmar'] ?? false);
            foreach (['apagar', 'encender', 'mantener', 'apagar_tipos'] as $clave) {
                $e[$clave] = array_values($e[$clave] ?? []);
            }
            foreach (['apagar', 'encender', 'mantener'] as $clave) {
                foreach ($e[$clave] as $ref) {
                    if (!isset($refs[$ref])) {
                        throw new \RuntimeException("La escena «{$id}» nombra «{$ref}», que no existe.");
                    }
                }
            }
            foreach ($e['apagar_tipos'] as $tipo) {
                if (!in_array($tipo, $tipos, true)) {
                    throw new \RuntimeException("La escena «{$id}» usa el tipo «{$tipo}», que ninguna función tiene.");
                }
            }
            $this->escenas[$id] = $e;
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function escenas(): array
    {
        return $this->escenas;
    }

    /**
     * Los cambios que haría una escena, ya expandidos: ref => valor.
     *
     * @return array<string, bool>
     * @throws UnknownInterruptor si la escena no existe.
     */
    public function cambios(string $id): array
    {
        $e = $this->exigir($id);
        $cambios = [];
        foreach ($this->interruptores->catalogo() as $fid => $f) {
            if (in_array($f['tipo'], $e['apagar_tipos'], true)) {
                $cambios[$fid] = false;
            }
        }
        foreach ($e['apagar'] as $ref) {
            $cambios[$ref] = false;
        }
        foreach ($e['encender'] as $ref) {
            $cambios[$ref] = true;
        }
        foreach ($e['mantener'] as $ref) {
            unset($cambios[$ref]);
        }
        return $cambios;
    }

    /**
     * Aplica una escena y recuerda lo que cambió, para poder deshacerla.
     *
     * @return array<string, bool> ref => valor anterior, de lo que cambió
     * @throws UnknownInterruptor|DependenciaRota
     */
    public function aplicar(string $id, string $quien): array
    {
        $antes = $this->interruptores->aplicar($this->cambios($id), $quien, "escena:{$id}");
        if ($antes !== []) {
            $this->guardarUltima(['escena' => $id, 'quien' => $quien, 'ts' => gmdate('Y-m-d\TH:i:s\Z'), 'antes' => $antes]);
        }
        return $antes;
    }

    /** @return array{escena: string, quien: string, ts: string, antes: array<string, bool>}|null */
    public function ultima(): ?array
    {
        if (!is_file($this->rutaUltima)) {
            return null;
        }
        $datos = json_decode((string) file_get_contents($this->rutaUltima), true);
        return is_array($datos) && isset($datos['escena'], $datos['antes']) ? $datos : null;
    }

    /**
     * Vuelve al estado anterior a la última escena aplicada.
     *
     * @return array<string, bool> ref => valor que tenía justo antes de deshacer
     * @throws \RuntimeException si no hay nada que deshacer.
     * @throws DependenciaRota si, desde entonces, restaurar rompería algo.
     */
    public function deshacer(string $quien): array
    {
        $ultima = $this->ultima();
        if ($ultima === null) {
            throw new \RuntimeException('No hay ninguna escena que deshacer.');
        }
        $cambiado = $this->interruptores->aplicar($ultima['antes'], $quien, "deshacer:{$ultima['escena']}");
        @unlink($this->rutaUltima);
        return $cambiado;
    }

    /** @return array<string, mixed> */
    private function exigir(string $id): array
    {
        if (!isset($this->escenas[$id])) {
            throw new UnknownInterruptor("No hay ninguna escena «{$id}».");
        }
        return $this->escenas[$id];
    }

    /** @param array<string, mixed> $datos */
    private function guardarUltima(array $datos): void
    {
        $dir = dirname($this->rutaUltima);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se puede crear {$dir}");
        }
        $temp = $this->rutaUltima . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($temp, $json) === false || !rename($temp, $this->rutaUltima)) {
            @unlink($temp);
            throw new \RuntimeException("No se pudo guardar {$this->rutaUltima}");
        }
    }
}
