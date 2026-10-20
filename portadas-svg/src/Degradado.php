<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Degradado de color para el fondo de la cubierta, en SVG.
 *
 * Las coordenadas van en milímetros sobre el lienzo entero (userSpaceOnUse). Así el degradado es
 * continuo entre piezas, y cada recorte muestra el mismo tramo que la cubierta completa.
 */
final class Degradado
{
    /** Identificador del degradado dentro del SVG. */
    public const ID = 'fondo-degradado';

    /** Cuánto se aleja el segundo color de «auto» del color del texto. */
    private const DESPLAZAMIENTO_AUTO = 0.4;

    /** @param list<string> $colores */
    private function __construct(
        public readonly string $tipo,
        public readonly float $angulo,
        public readonly array $colores,
    ) {
    }

    /**
     * Lee el objeto `degradado` del JSON. `tipo` es lineal (por defecto) o radial; `angulo` va en grados,
     * 0 de izquierda a derecha y 90 de arriba abajo; `colores` es una lista de 2 a 6 colores o «auto».
     * Con «auto», el segundo color se aleja del color de texto, así que el contraste mejora de un extremo
     * al otro.
     *
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos, string $fondo, string $texto): self
    {
        $tipo = $datos['tipo'] ?? 'lineal';
        if (!in_array($tipo, ['lineal', 'radial'], true)) {
            throw new \InvalidArgumentException("degradado.tipo debe ser lineal o radial.");
        }

        $angulo = $datos['angulo'] ?? 90;
        if (!is_int($angulo) && !is_float($angulo)) {
            throw new \InvalidArgumentException("degradado.angulo debe ser un número de grados.");
        }

        $colores = $datos['colores'] ?? 'auto';
        if ($colores === 'auto') {
            $hacia = Color::esClaro($texto) ? '#000000' : '#ffffff';
            $colores = [$fondo, Color::mezclar($fondo, $hacia, self::DESPLAZAMIENTO_AUTO)];
        } elseif (!is_array($colores) || count($colores) < 2 || count($colores) > 6) {
            throw new \InvalidArgumentException('degradado.colores debe ser «auto» o una lista de 2 a 6 colores.');
        }
        foreach ($colores as $color) {
            if (!is_string($color) || !Color::valido($color)) {
                throw new \InvalidArgumentException('degradado.colores: cada color debe ser #rrggbb.');
            }
        }

        return new self($tipo, (float) $angulo, array_values($colores));
    }

    /** Elemento `<defs>` con el degradado, para el lienzo dado. */
    public function defs(Rectangulo $lienzo): string
    {
        $paradas = '';
        $ultima = count($this->colores) - 1;
        foreach ($this->colores as $indice => $color) {
            $paradas .= Svg::elemento('stop', ['offset' => $indice / $ultima, 'stop-color' => $color]);
        }

        return '<defs>' . "\n" . $this->gradiente($lienzo, $paradas) . '</defs>' . "\n";
    }

    public function descripcion(): string
    {
        $forma = $this->tipo === 'radial' ? 'radial desde el centro' : 'lineal, ' . Svg::n($this->angulo) . '°';

        return "degradado $forma: " . implode(' → ', $this->colores);
    }

    private function gradiente(Rectangulo $lienzo, string $paradas): string
    {
        $cx = $lienzo->x + $lienzo->ancho / 2;
        $cy = $lienzo->y + $lienzo->alto / 2;

        if ($this->tipo === 'radial') {
            // El radio llega a la esquina: el último color queda justo en los vértices.
            $radio = sqrt($lienzo->ancho ** 2 + $lienzo->alto ** 2) / 2;

            return Svg::elemento('radialGradient', [
                'id' => self::ID,
                'gradientUnits' => 'userSpaceOnUse',
                'cx' => $cx,
                'cy' => $cy,
                'r' => $radio,
            ], $paradas);
        }

        // La recta del degradado pasa por el centro y su largo cubre las cuatro esquinas.
        $radianes = deg2rad($this->angulo);
        $ux = cos($radianes);
        $uy = sin($radianes);
        $mitad = (abs($lienzo->ancho * $ux) + abs($lienzo->alto * $uy)) / 2;

        return Svg::elemento('linearGradient', [
            'id' => self::ID,
            'gradientUnits' => 'userSpaceOnUse',
            'x1' => $cx - $ux * $mitad,
            'y1' => $cy - $uy * $mitad,
            'x2' => $cx + $ux * $mitad,
            'y2' => $cy + $uy * $mitad,
        ], $paradas);
    }
}
