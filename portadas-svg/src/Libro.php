<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Datos de un libro ya validados, leídos de un JSON (ver README.md). Lo que no se da toma un valor
 * por defecto razonable; lo que no tiene sentido (un color mal escrito, un lomo negativo) falla antes
 * de dibujar nada.
 */
final class Libro
{
    /**
     * Límites de las medidas, en mm. Pasarse de ahí casi seguro es un error de unidades (centímetros o
     * pulgadas en vez de milímetros), y es mejor avisar antes de dibujar un lomo de cinco metros.
     */
    private const LADO_MINIMO = 50.0;
    private const LADO_MAXIMO = 500.0;
    private const SANGRADO_MAXIMO = 10.0;
    private const SOLAPA_MINIMA = 40.0;
    private const LOMO_MAXIMO = 150.0;
    /** Grosor de una hoja de papel, en mm. */
    private const GROSOR_MINIMO = 0.02;
    private const GROSOR_MAXIMO = 0.5;

    /** @param array{fondo: string, texto: string, acento: string} $colores */
    private function __construct(
        public readonly string $id,
        public readonly Textos $textos,
        public readonly Formato $formato,
        public readonly string $plantilla,
        public readonly array $colores,
        /** Degradado del fondo, o null si el fondo es un color sólido. */
        public readonly ?Degradado $degradado,
        public readonly string $lomoDireccion,
    ) {
    }

    public static function desdeArchivo(string $ruta): self
    {
        if (!is_file($ruta) || !is_readable($ruta)) {
            throw new \InvalidArgumentException("$ruta: no existe o no se puede leer.");
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new \InvalidArgumentException("No se pudo leer $ruta.");
        }
        try {
            $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \InvalidArgumentException("$ruta: el JSON no es válido (" . $error->getMessage() . '). Revisa comas, comillas y llaves.');
        }
        if (!is_array($datos)) {
            throw new \InvalidArgumentException("$ruta: el JSON debe ser un objeto.");
        }

        return self::desdeArray(pathinfo($ruta, PATHINFO_FILENAME), $datos);
    }

    /** @param array<string, mixed> $datos */
    public static function desdeArray(string $id, array $datos): self
    {
        $titulo = self::cadena($datos, 'titulo');
        $autor = self::cadena($datos, 'autor');
        if ($titulo === '' || $autor === '') {
            throw new \InvalidArgumentException("$id: 'titulo' y 'autor' son obligatorios.");
        }

        $plantilla = self::cadena($datos, 'plantilla', 'clasica');
        $colores = Plantilla::crear($plantilla)->coloresPorDefecto();
        foreach (self::objeto($datos, 'colores') as $rol => $color) {
            if (!array_key_exists($rol, $colores)) {
                throw new \InvalidArgumentException("$id: el color '$rol' no existe; usa fondo, texto o acento.");
            }
            if (!is_string($color) || !Color::valido($color)) {
                throw new \InvalidArgumentException("$id: el color '$rol' debe ser #rrggbb.");
            }
            $colores[$rol] = $color;
        }

        $degradado = isset($datos['degradado'])
            ? Degradado::desdeArray(self::objeto($datos, 'degradado'), $colores['fondo'], $colores['texto'])
            : null;

        $formato = self::objeto($datos, 'formato');
        $ancho = self::numero($formato, 'ancho_mm', 135.0, self::LADO_MINIMO, self::LADO_MAXIMO);
        $alto = self::numero($formato, 'alto_mm', 210.0, self::LADO_MINIMO, self::LADO_MAXIMO);
        $sangrado = self::numero($datos, 'sangrado_mm', 3.0, 0.0, self::SANGRADO_MAXIMO);

        $solapas = self::numero($datos, 'solapas_mm', 0.0, 0.0);
        if ($solapas > 0.0 && $solapas < self::SOLAPA_MINIMA) {
            throw new \InvalidArgumentException("$id: 'solapas_mm' debe ser 0 (sin solapas) o " . self::SOLAPA_MINIMA . ' o más.');
        }
        if ($solapas > $ancho) {
            throw new \InvalidArgumentException("$id: 'solapas_mm' ($solapas mm) no puede ser mayor que el ancho de la portada ($ancho mm).");
        }

        if (isset($datos['lomo_mm'])) {
            $lomo = self::numero($datos, 'lomo_mm', null, 0.0, self::LOMO_MAXIMO);
        } else {
            $grosor = self::numero($datos, 'grosor_hoja_mm', 0.1, self::GROSOR_MINIMO, self::GROSOR_MAXIMO);
            $lomo = ceil(self::numero($datos, 'paginas', null, 2.0) / 2) * $grosor;
            if ($lomo > self::LOMO_MAXIMO) {
                throw new \InvalidArgumentException("$id: con esas 'paginas' y ese 'grosor_hoja_mm' el lomo sale de " . round($lomo, 1) . ' mm, y el máximo es ' . self::LOMO_MAXIMO . " mm. Revisa los dos campos o da 'lomo_mm'.");
            }
        }

        $direccion = self::cadena($datos, 'lomo_direccion', 'arriba-abajo');
        if (!in_array($direccion, ['arriba-abajo', 'abajo-arriba'], true)) {
            throw new \InvalidArgumentException("$id: 'lomo_direccion' debe ser arriba-abajo o abajo-arriba.");
        }

        $textos = new Textos(
            titulo: $titulo,
            subtitulo: self::cadena($datos, 'subtitulo'),
            autor: $autor,
            traductor: self::cadena($datos, 'traductor'),
            editorial: self::cadena($datos, 'editorial'),
            coleccion: self::cadena($datos, 'coleccion'),
            isbn: self::cadena($datos, 'isbn'),
            contratapa: self::cadena($datos, 'contratapa'),
            solapaContratapa: self::cadena($datos, 'solapa_contratapa'),
            solapaPortada: self::cadena($datos, 'solapa_portada'),
        );

        return new self(
            id: $id,
            textos: $textos,
            formato: new Formato(ancho: $ancho, alto: $alto, sangrado: $sangrado, solapas: $solapas, lomo: $lomo),
            plantilla: $plantilla,
            colores: $colores,
            degradado: $degradado,
            lomoDireccion: $direccion,
        );
    }

    /** @param array<string, mixed> $datos */
    private static function cadena(array $datos, string $campo, string $defecto = ''): string
    {
        $valor = $datos[$campo] ?? $defecto;
        if (!is_string($valor)) {
            throw new \InvalidArgumentException("'$campo' debe ser texto.");
        }

        return trim($valor);
    }

    /**
     * Número entre `$minimo` y `$maximo` (sin tope si es null). Con `$defecto` null, el campo es obligatorio.
     *
     * @param array<string, mixed> $datos
     */
    private static function numero(array $datos, string $campo, ?float $defecto, float $minimo, ?float $maximo = null): float
    {
        $valor = $datos[$campo] ?? $defecto;
        if ((!is_int($valor) && !is_float($valor)) || $valor < $minimo || ($maximo !== null && $valor > $maximo)) {
            throw new \InvalidArgumentException($maximo === null
                ? "'$campo' debe ser un número mayor o igual que $minimo."
                : "'$campo' debe ser un número entre $minimo y $maximo.");
        }

        return (float) $valor;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private static function objeto(array $datos, string $campo): array
    {
        $valor = $datos[$campo] ?? [];
        if (!is_array($valor)) {
            throw new \InvalidArgumentException("'$campo' debe ser un objeto JSON.");
        }

        return $valor;
    }
}
