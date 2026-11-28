<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

require_once __DIR__ . '/Excepciones.php';
require_once __DIR__ . '/RegistroJsonl.php';

/**
 * Interruptores de funciones y de sus opciones.
 *
 * Una «referencia» (ref) es el id de una función, p. ej. `gen.vistas`,
 * o el de una opción de ella, p. ej. `gen.vistas:abanico`.
 *
 * - El catálogo (catalogo.json) dice qué existe y de qué depende cada cosa.
 * - El estado (var/estado.json) guarda solo los interruptores que no están en su
 *   valor por defecto. Las claves son refs.
 *
 * Estado efectivo (lo que el sitio debe usar):
 *  - una función está encendida si su interruptor lo está;
 *  - una opción está encendida si su función lo está Y su propio interruptor lo está.
 *
 * Reglas (las mismas que aplica el panel):
 *  - no se enciende una función si alguna de sus dependencias no está encendida
 *    (las dependencias pueden ser funciones u opciones);
 *  - no se apaga algo si una función encendida lo necesita;
 *  - una función con `min_opciones` no puede quedarse sin ese mínimo de opciones encendidas.
 */
final class Interruptores
{
    /** Qué hace el sitio con una función apagada. */
    public const POLITICAS_AL_APAGAR = ['ocultar', 'no_disponible'];

    /** @var array<string, array<string, mixed>> id de función => definición */
    private array $funciones = [];

    /** @var array<string, string> */
    private array $plataformas = [];

    /** Instantánea del estado mientras está congelado (ver congelar()); null si no hay o aún no se leyó. */
    /** @var array<string, bool>|null */
    private ?array $instantanea = null;
    private bool $congelado = false;

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

    /**
     * Congela el estado: se lee una sola vez y las consultas siguientes usan esa instantánea, en vez de releer el archivo
     * en cada una. Hace falta para recorrer todos los interruptores (el panel, bin/estado.php): cada uno calcula sus
     * dependencias con cientos de consultas. Un cambio de otro proceso no se ve hasta descongelar(); cambiar() descongela.
     */
    public function congelar(): void
    {
        $this->congelado = true;
        $this->instantanea = null;
    }

    public function descongelar(): void
    {
        $this->congelado = false;
        $this->instantanea = null;
    }

    /** Número de interruptores: funciones más opciones. */
    public function totalInterruptores(): int
    {
        $n = 0;
        foreach ($this->funciones as $f) {
            $n += 1 + count($f['opciones']);
        }
        return $n;
    }

    /**
     * Interruptor propio de cada ref (sin tener en cuenta la función padre).
     *
     * @return array<string, bool>
     */
    public function interruptores(): array
    {
        $salida = [];
        $guardado = $this->leerEstado();
        foreach ($this->todasLasRefs() as $ref) {
            $salida[$ref] = $guardado[$ref] ?? $this->valorPorDefecto($ref);
        }
        return $salida;
    }

    /**
     * Estado efectivo de una ref: si el sitio la debe usar.
     *
     * @throws UnknownInterruptor si la ref no existe.
     */
    public function activo(string $ref): bool
    {
        [$id, $opcion] = $this->partir($ref);
        $propio = $this->propio($ref);
        if ($opcion === null) {
            return $propio;
        }
        return $propio && $this->propio($id);
    }

    /**
     * Refs que hay que encender antes de encender $ref (y que hoy están apagadas).
     *
     * @return list<string>
     */
    public function faltanParaEncender(string $ref): array
    {
        [$id, $opcion] = $this->partir($ref);
        $faltan = [];
        foreach ($this->dependenciasDe($id, $opcion) as $dep) {
            if (!$this->activo($dep)) {
                $faltan[] = $dep;
            }
        }
        return $faltan;
    }

    /**
     * Funciones encendidas que necesitan $ref, y que quedarían rotas si se apaga.
     *
     * @return list<string> ids de función
     */
    public function dependientesActivos(string $ref): array
    {
        $this->partir($ref);
        $afectadas = [];
        foreach ($this->funciones as $id => $f) {
            // Cada función y cada una de sus opciones, con sus propias dependencias.
            $candidatas = [$id => null];
            foreach ($f['opciones'] as $o) {
                $candidatas["{$id}:{$o['id']}"] = $o['id'];
            }
            foreach ($candidatas as $candidata => $opcion) {
                if (!$this->activo($candidata)) {
                    continue;
                }
                foreach ($this->dependenciasDe($id, $opcion) as $dep) {
                    if ($this->afecta($ref, $dep)) {
                        $afectadas[] = $candidata;
                        break;
                    }
                }
            }
        }
        return $afectadas;
    }

    /**
     * Enciende o apaga una ref y guarda el estado.
     *
     * @throws UnknownInterruptor si la ref no existe.
     * @throws DependenciaRota si el cambio dejaría algo roto.
     */
    public function cambiar(string $ref, bool $encendido, string $quien = 'sistema'): void
    {
        $this->partir($ref);
        // Todo el cambio (validar, escribir y anotar) va con el estado bloqueado:
        // dos cambios a la vez no se pisan ni se saltan una dependencia.
        $bloqueo = $this->bloquear();
        // Con el estado bloqueado nadie más lo cambia: las validaciones usan una sola lectura.
        $this->congelar();
        try {
            $antes = $this->propio($ref);
            $this->validarCambio($ref, $encendido);
            $estado = $this->leerEstado();
            $estado[$ref] = $encendido;
            $this->escribirEstado($estado);
            $this->anotar($ref, $antes, $encendido, $quien);
        } finally {
            $this->descongelar();
            $this->desbloquear($bloqueo);
        }
    }

    /**
     * Aplica varios cambios a la vez (una escena, el apagado de emergencia, un cambio programado). Se valida el estado
     * final completo, no cada paso: apagar una cadena de dependencias de uno en uno pasaría por estados inválidos.
     * Si el estado final rompería algo (una función encendida sin lo que necesita, o sin su mínimo de opciones),
     * no se guarda nada.
     *
     * @param array<string, bool> $cambios ref => valor nuevo
     * @return array<string, bool> ref => valor anterior, solo de lo que de verdad cambió (vacío si no cambió nada)
     * @throws UnknownInterruptor si alguna ref no existe.
     * @throws DependenciaRota si el estado final no es válido.
     */
    public function aplicar(array $cambios, string $quien = 'sistema', string $motivo = ''): array
    {
        foreach (array_keys($cambios) as $ref) {
            $this->partir((string) $ref);
        }
        $bloqueo = $this->bloquear();
        try {
            $this->descongelar();
            $actual = $this->leerEstadoDelArchivo();
            $nuevo = $actual;
            $antes = [];
            foreach ($cambios as $ref => $valor) {
                $ref = (string) $ref;
                $previo = $actual[$ref] ?? $this->valorPorDefecto($ref);
                if ($previo !== (bool) $valor) {
                    $antes[$ref] = $previo;
                    $nuevo[$ref] = (bool) $valor;
                }
            }
            if ($antes === []) {
                return [];
            }
            // El estado final se valida como si ya estuviera guardado.
            $this->congelado = true;
            $this->instantanea = $nuevo;
            $errores = $this->erroresDelEstado();
            if ($errores !== []) {
                throw new DependenciaRota(implode(' ', $errores));
            }
            $this->escribirEstado($nuevo);
            foreach ($antes as $ref => $previo) {
                $this->anotar($ref, $previo, $nuevo[$ref], $quien, $motivo);
            }
            return $antes;
        } finally {
            $this->descongelar();
            $this->desbloquear($bloqueo);
        }
    }

    /**
     * Lo que está roto en el estado actual (el leído o el de la instantánea): una función encendida sin una dependencia
     * encendida, o sin su mínimo de opciones, y lo mismo para sus opciones encendidas.
     *
     * @return list<string>
     */
    private function erroresDelEstado(): array
    {
        $errores = [];
        foreach ($this->funciones as $id => $f) {
            if (!$this->propio($id)) {
                continue;
            }
            foreach ($f['depende_de'] as $dep) {
                if (!$this->activo($dep)) {
                    $errores[] = "«{$id}» necesita encendida: {$dep}.";
                }
            }
            if ($f['min_opciones'] > 0 && $this->minimoSinCubrir($id, null)) {
                $errores[] = "«{$id}» necesita al menos {$f['min_opciones']} opción(es) encendida(s).";
            }
            foreach ($f['opciones'] as $o) {
                $ref = "{$id}:{$o['id']}";
                if (!$this->propio($ref)) {
                    continue;
                }
                foreach ($o['depende_de'] as $dep) {
                    if (!$this->activo($dep)) {
                        $errores[] = "«{$ref}» necesita encendida: {$dep}.";
                    }
                }
            }
        }
        return $errores;
    }

    /**
     * Cambios recientes, el más nuevo primero.
     *
     * @return list<array{ts: string, quien: string, ref: string, antes: bool, despues: bool}>
     */
    public function historial(int $max = 50): array
    {
        $salida = [];
        foreach ($this->registroCambios()->ultimas($max) as $e) {
            if (isset($e['ts'], $e['quien'], $e['ref'], $e['antes'], $e['despues'])) {
                $salida[] = $e;
            }
        }
        return $salida;
    }

    /**
     * Qué hace el sitio con esta ref cuando está apagada: 'ocultar' o 'no_disponible'.
     * Una opción usa la política de su función.
     */
    public function alApagar(string $ref): string
    {
        [$id] = $this->partir($ref);
        return $this->funciones[$id]['al_apagar'];
    }

    /** @throws DependenciaRota si el cambio dejaría algo roto (con el estado bloqueado). */
    private function validarCambio(string $ref, bool $encendido): void
    {
        [$id, $opcion] = $this->partir($ref);

        if ($encendido) {
            $faltan = $this->faltanParaEncender($ref);
            if ($faltan !== []) {
                throw new DependenciaRota(
                    "«{$ref}» necesita encendidas: " . implode(', ', $faltan) . '.'
                );
            }
            if ($opcion === null && $this->minimoSinCubrir($id, null)) {
                throw new DependenciaRota(
                    "«{$id}» necesita al menos {$this->funciones[$id]['min_opciones']} opción(es) encendida(s)."
                );
            }
        } else {
            $dependientes = $this->dependientesActivos($ref);
            if ($dependientes !== []) {
                throw new DependenciaRota(
                    "«{$ref}» no se puede apagar mientras estén encendidas: " . implode(', ', $dependientes) . '.'
                );
            }
            // El mínimo solo rige mientras la función está encendida.
            if ($opcion !== null && $this->propio($id) && $this->minimoSinCubrir($id, $opcion)) {
                throw new DependenciaRota(
                    "«{$id}» necesita al menos {$this->funciones[$id]['min_opciones']} opción(es) encendida(s): no se puede apagar «{$opcion}»."
                );
            }
        }
    }

    // --- internas --------------------------------------------------------------

    /**
     * Bloqueo exclusivo del estado (un archivo .lock al lado de estado.json).
     *
     * @return resource
     */
    private function bloquear()
    {
        $dir = dirname($this->rutaEstado);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se puede crear {$dir}");
        }
        $fh = fopen($this->rutaEstado . '.lock', 'c');
        if ($fh === false || !flock($fh, LOCK_EX)) {
            throw new \RuntimeException('No se pudo bloquear el estado.');
        }
        return $fh;
    }

    /** @param resource $fh */
    private function desbloquear($fh): void
    {
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /** Añade un cambio al registro (el estado ya está bloqueado por cambiar()). */
    private function anotar(string $ref, bool $antes, bool $despues, string $quien, string $motivo = ''): void
    {
        $quien = substr((string) preg_replace('/[[:cntrl:]]/', '', $quien), 0, 64);
        $evento = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'quien' => $quien,
            'ref' => $ref,
            'antes' => $antes,
            'despues' => $despues,
        ];
        if ($motivo !== '') {
            $evento['motivo'] = substr((string) preg_replace('/[[:cntrl:]]/', '', $motivo), 0, 80);
        }
        $this->registroCambios()->anotar($evento);
    }

    private function registroCambios(): RegistroJsonl
    {
        return new RegistroJsonl(dirname($this->rutaEstado) . '/cambios.jsonl');
    }

    /**
     * Las dependencias de una función o, si se da la opción, de esa opción.
     *
     * @return list<string>
     */
    private function dependenciasDe(string $id, ?string $opcion): array
    {
        if ($opcion === null) {
            return $this->funciones[$id]['depende_de'];
        }
        foreach ($this->funciones[$id]['opciones'] as $o) {
            if ($o['id'] === $opcion) {
                return $o['depende_de'];
            }
        }
        return [];
    }

    /** Interruptor propio de una ref (lo que dice el estado o el catálogo). */
    private function propio(string $ref): bool
    {
        $guardado = $this->leerEstado();
        return $guardado[$ref] ?? $this->valorPorDefecto($ref);
    }

    private function valorPorDefecto(string $ref): bool
    {
        [$id, $opcion] = $this->partir($ref);
        if ($opcion === null) {
            return (bool) $this->funciones[$id]['por_defecto'];
        }
        foreach ($this->funciones[$id]['opciones'] as $o) {
            if ($o['id'] === $opcion) {
                return (bool) $o['por_defecto'];
            }
        }
        return false; // no se llega aquí: partir() ya validó la opción
    }

    /**
     * ¿Apagar $ref (o encenderla, si $encendido) deja una dependencia $dep sin efecto?
     * Una dependencia de función se ve afectada si es la misma ref o, si $ref es una
     * función, cualquiera de sus opciones.
     */
    private function afecta(string $ref, string $dep): bool
    {
        if ($dep === $ref) {
            return true;
        }
        if (!str_contains($ref, ':')) {
            return str_starts_with($dep, $ref . ':');
        }
        return false;
    }

    /**
     * ¿Tendría $id menos opciones encendidas que su mínimo?
     * Si $opcionQueSeApaga no es null, se cuenta como si ya estuviera apagada.
     */
    private function minimoSinCubrir(string $id, ?string $opcionQueSeApaga): bool
    {
        $min = (int) $this->funciones[$id]['min_opciones'];
        if ($min === 0) {
            return false;
        }
        $on = 0;
        foreach ($this->funciones[$id]['opciones'] as $o) {
            $ref = "{$id}:{$o['id']}";
            $estaOn = $this->propio($ref);
            if ($o['id'] === $opcionQueSeApaga) {
                $estaOn = false;
            }
            $on += $estaOn ? 1 : 0;
        }
        return $on < $min;
    }

    /** @return list<string> */
    private function todasLasRefs(): array
    {
        $refs = [];
        foreach ($this->funciones as $id => $f) {
            $refs[] = $id;
            foreach ($f['opciones'] as $o) {
                $refs[] = "{$id}:{$o['id']}";
            }
        }
        return $refs;
    }

    /**
     * Separa una ref en [id, opción|null] y valida que exista.
     *
     * @return array{0: string, 1: ?string}
     * @throws UnknownInterruptor
     */
    private function partir(string $ref): array
    {
        $partes = explode(':', $ref, 2);
        $id = $partes[0];
        if (!isset($this->funciones[$id])) {
            throw new UnknownInterruptor("No hay ninguna función «{$id}» en el catálogo.");
        }
        if (count($partes) === 1) {
            return [$id, null];
        }
        $opcion = $partes[1];
        foreach ($this->funciones[$id]['opciones'] as $o) {
            if ($o['id'] === $opcion) {
                return [$id, $opcion];
            }
        }
        throw new UnknownInterruptor("No hay ninguna opción «{$opcion}» en «{$id}».");
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
            if ($id === '' || str_contains($id, ':') || isset($this->funciones[$id])) {
                throw new \RuntimeException("Id de función vacío, repetido o con «:»: «{$id}».");
            }
            if (!isset($this->plataformas[$f['plataforma'] ?? ''])) {
                throw new \RuntimeException("«{$id}» apunta a una plataforma que no existe.");
            }
            $opciones = [];
            foreach ($f['opciones'] ?? [] as $o) {
                $oid = (string) ($o['id'] ?? '');
                if ($oid === '' || str_contains($oid, ':') || isset($opciones[$oid])) {
                    throw new \RuntimeException("Opción vacía, repetida o con «:» en «{$id}»: «{$oid}».");
                }
                $opciones[$oid] = [
                    'id' => $oid,
                    'nombre' => (string) ($o['nombre'] ?? $oid),
                    'por_defecto' => (bool) ($o['por_defecto'] ?? true),
                    'depende_de' => array_values($o['depende_de'] ?? []),
                ];
            }
            $f['opciones'] = array_values($opciones);
            $f['depende_de'] = array_values($f['depende_de'] ?? []);
            $f['por_defecto'] = (bool) ($f['por_defecto'] ?? true);
            $f['min_opciones'] = (int) ($f['min_opciones'] ?? 0);
            if ($f['min_opciones'] > count($f['opciones'])) {
                throw new \RuntimeException("«{$id}» pide {$f['min_opciones']} opciones pero tiene " . count($f['opciones']) . '.');
            }
            $f['al_apagar'] = (string) ($f['al_apagar'] ?? 'ocultar');
            if (!in_array($f['al_apagar'], self::POLITICAS_AL_APAGAR, true)) {
                throw new \RuntimeException("«{$id}» tiene una política al apagar desconocida: «{$f['al_apagar']}».");
            }
            $f['supuesto'] = (bool) ($f['supuesto'] ?? false);
            $f['origen'] = (string) ($f['origen'] ?? 'repo');
            $f['carpetas'] = array_values($f['carpetas'] ?? []);
            $this->funciones[$id] = $f;
        }

        foreach ($this->funciones as $id => $f) {
            $quienes = [$id => $f['depende_de']];
            foreach ($f['opciones'] as $o) {
                $quienes["{$id}:{$o['id']}"] = $o['depende_de'];
            }
            foreach ($quienes as $quien => $deps) {
                foreach ($deps as $dep) {
                    if ($dep === $quien) {
                        throw new \RuntimeException("«{$quien}» depende de sí misma.");
                    }
                    try {
                        $this->partir($dep);
                    } catch (UnknownInterruptor) {
                        throw new \RuntimeException("«{$quien}» depende de «{$dep}», que no existe.");
                    }
                }
            }
        }
    }

    /** @return array<string, bool> */
    private function leerEstado(): array
    {
        if ($this->congelado && $this->instantanea !== null) {
            return $this->instantanea;
        }
        $estado = $this->leerEstadoDelArchivo();
        if ($this->congelado) {
            $this->instantanea = $estado;
        }
        return $estado;
    }

    /** @return array<string, bool> */
    private function leerEstadoDelArchivo(): array
    {
        if (!is_file($this->rutaEstado)) {
            return [];
        }
        $crudo = (string) file_get_contents($this->rutaEstado);
        $datos = json_decode($crudo, true);
        if (!is_array($datos)) {
            throw new \RuntimeException("El estado está dañado: {$this->rutaEstado}");
        }
        $validas = array_flip($this->todasLasRefs());
        $salida = [];
        foreach ($datos as $ref => $valor) {
            // Las refs que ya no existen en el catálogo se ignoran.
            if (isset($validas[(string) $ref]) && is_bool($valor)) {
                $salida[(string) $ref] = $valor;
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
