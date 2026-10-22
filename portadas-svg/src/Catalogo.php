<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Las dos clasificaciones de un libro, leídas de datos/:
 *
 * - la categoría de Marcalibros (movimiento literario o tipo de texto), copiada de su catálogo para que
 *   este proyecto no dependa de esa carpeta;
 * - la tradición cultural, que decide el adorno de la cubierta.
 *
 * Se escriben por su `slug` (`filosofia`) o por su nombre (`Filosofía y ensayo`), con o sin tildes.
 */
final class Catalogo
{
    /** @var list<array{slug: string, nombre: string, familia: string, tono: int}>|null */
    private static ?array $categorias = null;
    /** @var list<array{slug: string, nombre: string}>|null */
    private static ?array $tradiciones = null;

    /**
     * Cada categoría trae su `familia` (movimiento literario o tipo de texto) y un `tono` de 0 a 360° que
     * da el color de sus libros.
     *
     * @return list<array{slug: string, nombre: string, familia: string, tono: int}>
     */
    public static function categorias(): array
    {
        return self::$categorias ??= array_map(
            static fn (array $fila): array => [
                'slug' => $fila['slug'],
                'nombre' => $fila['nombre'],
                'familia' => $fila['familia'] ?? throw new \RuntimeException('datos/categorias.json: falta la familia de ' . $fila['slug'] . '.'),
                'tono' => $fila['tono'] ?? throw new \RuntimeException('datos/categorias.json: falta el tono (0 a 360) de ' . $fila['slug'] . '.'),
            ],
            self::leer('categorias'),
        );
    }

    /** @return list<array{slug: string, nombre: string}> */
    public static function tradiciones(): array
    {
        return self::$tradiciones ??= array_map(
            static fn (array $fila): array => ['slug' => $fila['slug'], 'nombre' => $fila['nombre']],
            self::leer('tradiciones'),
        );
    }

    /** @return array{slug: string, nombre: string, familia: string, tono: int} */
    public static function categoria(string $clave): array
    {
        return self::buscar(self::categorias(), $clave, 'categoría');
    }

    /** @return array{slug: string, nombre: string} */
    public static function tradicion(string $clave): array
    {
        return self::buscar(self::tradiciones(), $clave, 'tradición');
    }

    /**
     * @template T of array{slug: string, nombre: string}
     * @param list<T> $opciones
     * @return T
     */
    private static function buscar(array $opciones, string $clave, string $que): array
    {
        $buscada = Nombres::plegar($clave);
        foreach ($opciones as $opcion) {
            if ($buscada === Nombres::plegar($opcion['slug']) || $buscada === Nombres::plegar($opcion['nombre'])) {
                return $opcion;
            }
        }

        throw new \InvalidArgumentException("la $que «{$clave}» no existe; usa una de: " . implode(', ', array_column($opciones, 'slug')) . '.');
    }

    /** @return list<array{slug: string, nombre: string, familia?: string, tono?: int}> */
    private static function leer(string $nombre): array
    {
        $ruta = __DIR__ . "/../datos/$nombre.json";
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new \RuntimeException("No se pudo leer datos/$nombre.json.");
        }
        $filas = json_decode($contenido, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($filas) || $filas === [] || !array_is_list($filas)) {
            throw new \RuntimeException("datos/$nombre.json debe ser una lista con algún elemento.");
        }

        $resultado = [];
        $vistos = [];
        foreach ($filas as $fila) {
            if (!is_array($fila) || !is_string($fila['slug'] ?? null) || !is_string($fila['nombre'] ?? null)
                || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $fila['slug']) || isset($vistos[$fila['slug']])) {
                throw new \RuntimeException("datos/$nombre.json: cada elemento necesita un 'slug' único (a-z, cifras y guiones) y un 'nombre'.");
            }
            $vistos[$fila['slug']] = true;
            $tono = $fila['tono'] ?? null;
            if ($tono !== null && (!is_int($tono) || $tono < 0 || $tono > 360)) {
                throw new \RuntimeException("datos/$nombre.json: el tono de '{$fila['slug']}' debe ser un entero de 0 a 360.");
            }
            $resultado[] = ['slug' => $fila['slug'], 'nombre' => $fila['nombre']]
                + (is_string($fila['familia'] ?? null) ? ['familia' => $fila['familia']] : [])
                + ($tono === null ? [] : ['tono' => $tono]);
        }

        return $resultado;
    }
}
