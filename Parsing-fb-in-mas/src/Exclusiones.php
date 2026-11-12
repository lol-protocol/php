<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/**
 * Personas que no deben aparecer en lo que escriben estos programas: las que pidieron retirarse, o
 * cuyo retiro se decidió (ver RECLAMOS.md en la raíz). Es un archivo de texto que los programas de
 * `bin/` respetan con `--excluir=archivo`, una entrada por línea:
 *
 *     https://www.facebook.com/ana.ejemplo     una página (sin importar mayúsculas, `www.` ni la barra final)
 *     twitter:anaejemplo                       una cuenta: red y usuario, como salen en el perfil
 *     wikidata:Q42                             vale también para Wikidata
 *     # un comentario                          las líneas vacías y las que empiezan por # se ignoran
 *
 * Esta lista NO está conectada a la cola de pedidos de retiro del sitio (`url-routing/`): se mantiene a
 * mano, y quien decide un retiro tiene que agregar la entrada aquí. Retirar a alguien de lo que ya se
 * generó tampoco es automático: solo evita que vuelva a salir al reprocesar.
 */
final class Exclusiones
{
    /** @param array<string, true> $claves */
    private function __construct(private readonly array $claves)
    {
    }

    public static function vacia(): self
    {
        return new self([]);
    }

    /** @throws RuntimeException si el archivo no se puede leer o una línea no es una URL ni «red:usuario» */
    public static function desdeArchivo(string $ruta): self
    {
        $texto = is_file($ruta) ? file_get_contents($ruta) : false;
        if ($texto === false) {
            throw new RuntimeException("No se pudo leer la lista de exclusión $ruta");
        }
        return self::desdeTexto($texto);
    }

    /** @throws RuntimeException si una línea no es una URL ni «red:usuario» */
    public static function desdeTexto(string $texto): self
    {
        $claves = [];
        foreach (preg_split('/\R/', $texto) ?: [] as $n => $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            if (Lote::esUrl($linea)) {
                $claves['url:' . Lote::claveUrl($linea)] = true;
            } elseif (preg_match('/^([a-z][a-z0-9_-]*):(\S+)$/i', $linea, $m) === 1) {
                $claves['cuenta:' . strtolower($m[1]) . ':' . strtolower($m[2])] = true;
            } else {
                // Fallar en voz alta: una línea mal escrita no puede dejar a alguien sin excluir en silencio.
                throw new RuntimeException('Lista de exclusión, línea ' . ($n + 1) . ': no es una URL ni «red:usuario»');
            }
        }
        return new self($claves);
    }

    public function cantidad(): int
    {
        return count($this->claves);
    }

    /**
     * @param array<string, mixed> $perfil un perfil como lo escribe `Perfil::aArreglo()`
     */
    public function contiene(array $perfil): bool
    {
        $url = $perfil['url'] ?? null;
        if (is_string($url) && isset($this->claves['url:' . Lote::claveUrl($url)])) {
            return true;
        }
        $red = $perfil['red'] ?? null;
        $usuario = $perfil['usuario'] ?? null;
        return is_string($red) && is_string($usuario)
            && isset($this->claves['cuenta:' . strtolower($red) . ':' . strtolower($usuario)]);
    }

    /**
     * @param list<array<string, mixed>> $perfiles
     * @return array{perfiles: list<array<string, mixed>>, excluidos: int}
     */
    public function filtrar(array $perfiles): array
    {
        $quedan = [];
        foreach ($perfiles as $p) {
            if (!$this->contiene($p)) {
                $quedan[] = $p;
            }
        }
        return ['perfiles' => $quedan, 'excluidos' => count($perfiles) - count($quedan)];
    }
}
