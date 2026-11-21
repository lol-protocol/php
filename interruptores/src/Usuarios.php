<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

/**
 * Usuarios del panel: contraseña hasheada y un rol.
 *
 *  - lector: ve el panel y el registro de cambios, no cambia nada.
 *  - editor: además enciende y apaga.
 *
 * Bloqueo por usuario y origen: tras MAX_FALLOS claves incorrectas seguidas desde un
 * mismo origen (la IP guardada), ese origen queda bloqueado BLOQUEO_SEGUNDOS. Otro origen
 * sigue pudiendo entrar: así, quien falla desde su propia red no bloquea a la persona real.
 * Los fallos cuentan solo dentro de esa misma ventana de BLOQUEO_SEGUNDOS.
 *
 * Un usuario que no existe responde igual que uno que sí existe (mismo tiempo, mismo
 * mensaje), para no revelar qué nombres hay.
 *
 * Archivo: var/usuarios.json, fuera de git. Nunca se imprime un hash.
 */
final class Usuarios
{
    public const ROLES = ['lector', 'editor'];
    public const MAX_FALLOS = 5;
    public const BLOQUEO_SEGUNDOS = 900;

    private const CLAVE_MIN = 12;
    private const CLAVE_MAX = 72; // password_hash con bcrypt no usa más de 72 bytes

    private static ?string $hashFicticio = null;

    /** @var (\Closure(): int)|null */
    private ?\Closure $reloj;

    /** @param (\Closure(): int)|null $reloj solo para pruebas */
    public function __construct(
        private readonly string $ruta,
        ?\Closure $reloj = null,
    ) {
        $this->reloj = $reloj;
    }

    public function hay(): bool
    {
        return $this->leer() !== [];
    }

    /** @return array<string, array{rol: string, creado: string}> sin hashes ni intentos */
    public function listar(): array
    {
        $salida = [];
        foreach ($this->leer() as $nombre => $u) {
            $salida[$nombre] = ['rol' => $u['rol'], 'creado' => $u['creado']];
        }
        return $salida;
    }

    /** Rol actual de un usuario, o null si ya no existe. Sirve para refrescar una sesión abierta. */
    public function buscar(string $nombre): ?array
    {
        $u = $this->leer()[$nombre] ?? null;
        return $u === null ? null : ['nombre' => $nombre, 'rol' => (string) $u['rol']];
    }

    public function crear(string $nombre, string $rol, string $clave): void
    {
        $this->validarNombre($nombre);
        $this->validarRol($rol);
        $this->validarClave($clave);
        $this->modificar(function (array $datos) use ($nombre, $rol, $clave): array {
            if (isset($datos[$nombre])) {
                throw new \RuntimeException("Ya existe el usuario «{$nombre}».");
            }
            $datos[$nombre] = [
                'hash' => password_hash($clave, PASSWORD_DEFAULT),
                'rol' => $rol,
                'creado' => gmdate('Y-m-d\TH:i:s\Z', $this->ahora()),
                'intentos' => [],
            ];
            return $datos;
        });
    }

    public function cambiarClave(string $nombre, string $clave): void
    {
        $this->validarClave($clave);
        $this->modificar(function (array $datos) use ($nombre, $clave): array {
            $this->exigirExiste($datos, $nombre);
            $datos[$nombre]['hash'] = password_hash($clave, PASSWORD_DEFAULT);
            $datos[$nombre]['intentos'] = [];
            return $datos;
        });
    }

    public function cambiarRol(string $nombre, string $rol): void
    {
        $this->validarRol($rol);
        $this->modificar(function (array $datos) use ($nombre, $rol): array {
            $this->exigirExiste($datos, $nombre);
            if ($datos[$nombre]['rol'] === 'editor' && $rol !== 'editor') {
                $this->exigirOtroEditor($datos, $nombre);
            }
            $datos[$nombre]['rol'] = $rol;
            return $datos;
        });
    }

    public function borrar(string $nombre): void
    {
        $this->modificar(function (array $datos) use ($nombre): array {
            $this->exigirExiste($datos, $nombre);
            if ($datos[$nombre]['rol'] === 'editor') {
                $this->exigirOtroEditor($datos, $nombre);
            }
            unset($datos[$nombre]);
            return $datos;
        });
    }

    public function bloqueado(string $nombre, string $origen = 'local'): bool
    {
        $u = $this->leer()[$nombre] ?? null;
        return $u !== null && $this->bloqueadoEn($u, $origen, $this->ahora());
    }

    /**
     * Comprueba la clave. Devuelve nombre y rol si es correcta, o null.
     * Cuenta los fallos del origen y bloquea ese origen al llegar al máximo.
     *
     * @return array{nombre: string, rol: string}|null
     */
    public function verificar(string $nombre, string $clave, string $origen = 'local'): ?array
    {
        $resultado = null;
        $this->modificar(function (array $datos) use ($nombre, $clave, $origen, &$resultado): array {
            $u = $datos[$nombre] ?? null;
            if ($u === null) {
                // Mismo trabajo que con un usuario real: no se nota si el nombre existe.
                password_verify($clave, self::hashFicticio());
                return $datos;
            }
            $ahora = $this->ahora();
            $u['intentos'] = $this->podar($u['intentos'] ?? [], $ahora);
            if ($this->bloqueadoEn($u, $origen, $ahora)) {
                $datos[$nombre] = $u;
                return $datos;
            }
            if (!password_verify($clave, $u['hash'])) {
                $i = $u['intentos'][$origen] ?? ['fallos' => 0];
                $i['fallos'] = (int) $i['fallos'] + 1;
                $i['ultimo'] = $ahora;
                if ($i['fallos'] >= self::MAX_FALLOS) {
                    $i['bloqueado_hasta'] = $ahora + self::BLOQUEO_SEGUNDOS;
                    $i['fallos'] = 0;
                }
                $u['intentos'][$origen] = $i;
                $datos[$nombre] = $u;
                return $datos;
            }
            unset($u['intentos'][$origen]);
            $datos[$nombre] = $u;
            $resultado = ['nombre' => $nombre, 'rol' => (string) $u['rol']];
            return $datos;
        });
        return $resultado;
    }

    // --- internas --------------------------------------------------------------

    private function ahora(): int
    {
        return $this->reloj !== null ? ($this->reloj)() : time();
    }

    /** @param array<string, mixed> $u */
    private function bloqueadoEn(array $u, string $origen, int $ahora): bool
    {
        return isset($u['intentos'][$origen]['bloqueado_hasta'])
            && (int) $u['intentos'][$origen]['bloqueado_hasta'] > $ahora;
    }

    /**
     * Quita los orígenes sin bloqueo cuyos últimos fallos ya salieron de la ventana.
     *
     * @param array<string, array<string, mixed>> $intentos
     * @return array<string, array<string, mixed>>
     */
    private function podar(array $intentos, int $ahora): array
    {
        foreach ($intentos as $origen => $i) {
            $bloqueado = isset($i['bloqueado_hasta']) && (int) $i['bloqueado_hasta'] > $ahora;
            $reciente = isset($i['ultimo']) && (int) $i['ultimo'] + self::BLOQUEO_SEGUNDOS > $ahora;
            if (!$bloqueado && !$reciente) {
                unset($intentos[$origen]);
            }
        }
        return $intentos;
    }

    private static function hashFicticio(): string
    {
        return self::$hashFicticio ??= password_hash('clave-que-nadie-usa', PASSWORD_DEFAULT);
    }

    private function validarNombre(string $nombre): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $nombre) !== 1) {
            throw new \InvalidArgumentException('El nombre debe tener de 3 a 32 caracteres: minúsculas, números, punto, guion o guion bajo.');
        }
    }

    private function validarRol(string $rol): void
    {
        if (!in_array($rol, self::ROLES, true)) {
            throw new \InvalidArgumentException('El rol debe ser «lector» o «editor».');
        }
    }

    private function validarClave(string $clave): void
    {
        $largo = strlen($clave);
        if ($largo < self::CLAVE_MIN || $largo > self::CLAVE_MAX) {
            throw new \InvalidArgumentException('La clave debe tener entre ' . self::CLAVE_MIN . ' y ' . self::CLAVE_MAX . ' caracteres.');
        }
    }

    /** @param array<string, mixed> $datos */
    private function exigirExiste(array $datos, string $nombre): void
    {
        if (!isset($datos[$nombre])) {
            throw new \RuntimeException("No existe el usuario «{$nombre}».");
        }
    }

    /** @param array<string, mixed> $datos */
    private function exigirOtroEditor(array $datos, string $nombre): void
    {
        foreach ($datos as $otro => $u) {
            if ($otro !== $nombre && $u['rol'] === 'editor') {
                return;
            }
        }
        throw new \RuntimeException('Tiene que quedar al menos un editor.');
    }

    /** @return array<string, array<string, mixed>> */
    private function leer(): array
    {
        if (!is_file($this->ruta)) {
            return [];
        }
        $datos = json_decode((string) file_get_contents($this->ruta), true);
        if (!is_array($datos)) {
            throw new \RuntimeException("El archivo de usuarios está dañado: {$this->ruta}");
        }
        return $datos;
    }

    /**
     * Lee, cambia y escribe todo bajo un bloqueo exclusivo: dos cambios a la vez no se pisan.
     *
     * @param callable(array<string, array<string, mixed>>): array<string, array<string, mixed>> $cambio
     */
    private function modificar(callable $cambio): void
    {
        $dir = dirname($this->ruta);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se puede crear {$dir}");
        }
        $fh = fopen($this->ruta . '.lock', 'c');
        if ($fh === false || !flock($fh, LOCK_EX)) {
            throw new \RuntimeException('No se pudo bloquear los usuarios.');
        }
        try {
            $nuevos = $cambio($this->leer());
            $json = json_encode($nuevos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
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
