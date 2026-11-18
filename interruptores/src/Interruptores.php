<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

require_once __DIR__ . '/Excepciones.php';

/**
 * Interruptores de funciones: qué está encendido y qué está apagado.
 *
 * El catálogo (catalogo.json) dice qué funciones existen y de qué dependen.
 * El estado (var/estado.json) guarda solo si cada una está encendida.
 * Una función sin entrada en el estado usa su valor por defecto del catálogo.
 *
 * Reglas (las mismas que aplica el panel):
 *  - no se enciende una función si alguna de las que necesita está apagada;
 *  - no se apaga una función si alguna encendida la necesita.
 */
final class Interruptores
{
    /** @var array<string, array<string, mixed>> */
    private array $funciones = [];

    /** @var array<string, string> */
    private array $plataformas = [];

    public function __construct(
        private readonly string $rutaCatalogo,
        private readonly string $rutaEstado,
    ) {
        $this->cargarCatalogo();
    }

    /** @return array<string, array<string, mixed>> */
    public function catalogo(): array
    {
        return $this->funciones;
    }

    /** @return array<string, string> */
    public function plataformas(): array
    {
        return $this->plataformas;
    }

    /** @return array<string, bool> id => encendida */
    public function estados(): array
    {
        $guardado = $this->leerEstado();
        $salida = [];
        foreach ($this->funciones as $id => $f) {
            $salida[$id] = $guardado[$id] ?? (bool) $f['por_defecto'];
        }
        return $salida;
    }

    /**
     * @throws UnknownInterruptor si el id no existe en el catálogo.
     */
    public function activo(string $id): bool
    {
        $this->exigirExiste($id);
        $guardado = $this->leerEstado();
        return $guardado[$id] ?? (bool) $this->funciones[$id]['por_defecto'];
    }

    /**
     * Funciones que hay que encender antes de encender $id y que están apagadas.
     *
     * @return list<string>
     */
    public function faltanParaEncender(string $id): array
    {
        $this->exigirExiste($id);
        $estados = $this->estados();
        $faltan = [];
        foreach ($this->funciones[$id]['depende_de'] as $dep) {
            if (!$estados[$dep]) {
                $faltan[] = $dep;
            }
        }
        return $faltan;
    }

    /**
     * Funciones encendidas que necesitan $id, y que quedarían rotas si se apaga.
     *
     * @return list<string>
     */
    public function dependientesActivos(string $id): array
    {
        $this->exigirExiste($id);
        $estados = $this->estados();
        $afectadas = [];
        foreach ($this->funciones as $otro => $f) {
            if ($estados[$otro] && in_array($id, $f['depende_de'], true)) {
                $afectadas[] = $otro;
            }
        }
        return $afectadas;
    }

    /**
     * Enciende o apaga una función y guarda el estado.
     *
     * @throws UnknownInterruptor si el id no existe.
     * @throws DependenciaRota si el cambio dejaría una dependencia rota.
     */
    public function cambiar(string $id, bool $encendido): void
    {
        $this->exigirExiste($id);
        if ($encendido) {
            $faltan = $this->faltanParaEncender($id);
            if ($faltan !== []) {
                throw new DependenciaRota(
                    "«{$id}» necesita encendidas: " . implode(', ', $faltan) . '.'
                );
            }
        } else {
            $dependientes = $this->dependientesActivos($id);
            if ($dependientes !== []) {
                throw new DependenciaRota(
                    "«{$id}» no se puede apagar mientras estén encendidas: " . implode(', ', $dependientes) . '.'
                );
            }
        }

        $estado = $this->leerEstado();
        $estado[$id] = $encendido;
        $this->escribirEstado($estado);
    }

    private function cargarCatalogo(): void
    {
        $crudo = @file_get_contents($this->rutaCatalogo);
        if ($crudo === false) {
            throw new \RuntimeException("No se puede leer el catálogo: {$this->rutaCatalogo}");
        }
        $datos = json_decode($crudo, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($datos) || !isset($datos['funciones'], $datos['plataformas'])) {
            throw new \RuntimeException('El catálogo no tiene «funciones» ni «plataformas».');
        }

        $this->plataformas = $datos['plataformas'];
        foreach ($datos['funciones'] as $f) {
            $id = (string) ($f['id'] ?? '');
            if ($id === '' || isset($this->funciones[$id])) {
                throw new \RuntimeException("Id de función vacío o repetido: «{$id}».");
            }
            if (!isset($this->plataformas[$f['plataforma'] ?? ''])) {
                throw new \RuntimeException("«{$id}» apunta a una plataforma que no existe.");
            }
            $f['depende_de'] = array_values($f['depende_de'] ?? []);
            $f['por_defecto'] = (bool) ($f['por_defecto'] ?? true);
            $this->funciones[$id] = $f;
        }

        foreach ($this->funciones as $id => $f) {
            foreach ($f['depende_de'] as $dep) {
                if (!isset($this->funciones[$dep]) || $dep === $id) {
                    throw new \RuntimeException("«{$id}» depende de «{$dep}», que no existe o es ella misma.");
                }
            }
        }
    }

    private function exigirExiste(string $id): void
    {
        if (!isset($this->funciones[$id])) {
            throw new UnknownInterruptor("No hay ninguna función «{$id}» en el catálogo.");
        }
    }

    /** @return array<string, bool> */
    private function leerEstado(): array
    {
        if (!is_file($this->rutaEstado)) {
            return [];
        }
        $crudo = (string) file_get_contents($this->rutaEstado);
        $datos = json_decode($crudo, true);
        if (!is_array($datos)) {
            throw new \RuntimeException("El estado está dañado: {$this->rutaEstado}");
        }
        $salida = [];
        foreach ($datos as $id => $valor) {
            // Se ignoran los ids que ya no están en el catálogo.
            if (isset($this->funciones[$id]) && is_bool($valor)) {
                $salida[(string) $id] = $valor;
            }
        }
        return $salida;
    }

    /** @param array<string, bool> $estado */
    private function escribirEstado(array $estado): void
    {
        $dir = dirname($this->rutaEstado);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se puede crear {$dir}");
        }
        ksort($estado);
        $json = json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        // Escritura atómica: primero a un temporal, luego se renombra.
        $temp = $this->rutaEstado . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, $this->rutaEstado)) {
            @unlink($temp);
            throw new \RuntimeException("No se pudo guardar el estado en {$this->rutaEstado}");
        }
    }
}
