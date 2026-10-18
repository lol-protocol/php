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
    /** @param array{fondo: string, texto: string, acento: string} $colores */
    private function __construct(
        public readonly string $id,
        public readonly string $titulo,
        public readonly string $subtitulo,
        public readonly string $autor,
        public readonly string $traductor,
        public readonly string $editorial,
        public readonly string $coleccion,
        public readonly string $isbn,
        public readonly string $contratapa,
        public readonly string $solapaContratapa,
        public readonly string $solapaPortada,
        public readonly string $plantilla,
        public readonly array $colores,
        /** Degradado del fondo, o null si el fondo es un color sólido. */
        public readonly ?Degradado $degradado,
        /** Corte, sangrado, solapas y lomo, en mm. */
        public readonly float $anchoMm,
        public readonly float $altoMm,
        public readonly float $sangradoMm,
        public readonly float $solapasMm,
        public readonly float $lomoMm,
        public readonly string $lomoDireccion,
    ) {
    }

    public static function desdeArchivo(string $ruta): self
    {
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new \InvalidArgumentException("No se pudo leer $ruta.");
        }
        $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
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
        $ancho = self::numero($formato, 'ancho_mm', 135.0, 50.0);
        $alto = self::numero($formato, 'alto_mm', 210.0, 50.0);
        $sangrado = self::numero($datos, 'sangrado_mm', 3.0, 0.0);

        $solapas = self::numero($datos, 'solapas_mm', 0.0, 0.0);
        if ($solapas > 0.0 && $solapas < 40.0) {
            throw new \InvalidArgumentException("$id: 'solapas_mm' debe ser 0 (sin solapas) o 40 o más.");
        }

        if (isset($datos['lomo_mm'])) {
            $lomo = self::numero($datos, 'lomo_mm', null, 0.0);
        } else {
            $grosor = self::numero($datos, 'grosor_hoja_mm', 0.1, 0.02);
            $lomo = ceil(self::numero($datos, 'paginas', null, 2.0) / 2) * $grosor;
        }

        $direccion = self::cadena($datos, 'lomo_direccion', 'arriba-abajo');
        if (!in_array($direccion, ['arriba-abajo', 'abajo-arriba'], true)) {
            throw new \InvalidArgumentException("$id: 'lomo_direccion' debe ser arriba-abajo o abajo-arriba.");
        }

        return new self(
            id: $id,
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
            plantilla: $plantilla,
            colores: $colores,
            degradado: $degradado,
            anchoMm: $ancho,
            altoMm: $alto,
            sangradoMm: $sangrado,
            solapasMm: $solapas,
            lomoMm: $lomo,
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
     * Número mayor o igual que `$minimo`. Con `$defecto` null, el campo es obligatorio.
     *
     * @param array<string, mixed> $datos
     */
    private static function numero(array $datos, string $campo, ?float $defecto, float $minimo): float
    {
        $valor = $datos[$campo] ?? $defecto;
        if ((!is_int($valor) && !is_float($valor)) || $valor < $minimo) {
            throw new \InvalidArgumentException("'$campo' debe ser un número mayor o igual que $minimo.");
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
