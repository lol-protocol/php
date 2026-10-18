<?php

declare(strict_types=1);

namespace PortadasSvg;

use PortadasSvg\Plantillas\Clasica;
use PortadasSvg\Plantillas\Sobria;

/**
 * Plantilla de cubierta: dibuja la portada, la contratapa, el lomo y las solapas.
 *
 * Todo se dibuja una sola vez sobre el lienzo de la cubierta completa (ver Medidas), y cada archivo es
 * solo un recorte: así una pieza suelta coincide exactamente con su parte de la cubierta.
 *
 * Una plantilla concreta implementa `portada()` y `coloresPorDefecto()`. Puede cambiar también la
 * fuente, el ancho medio de letra y los colores del lomo.
 */
abstract class Plantilla
{
    /** Espacio entre el borde del corte y el texto, en mm. */
    protected const MARGEN = 16.0;
    /** Distancia entre líneas, en múltiplos del tamaño de letra. */
    protected const INTERLINEADO = 1.35;
    /** Ancho del lomo a partir del cual se escribe texto en él, en mm. */
    protected const LOMO_MINIMO = 3.0;

    /** @return array<string, class-string<Plantilla>> */
    public static function disponibles(): array
    {
        return ['clasica' => Clasica::class, 'sobria' => Sobria::class];
    }

    public static function crear(string $nombre): self
    {
        $clase = self::disponibles()[$nombre] ?? null;
        if ($clase === null) {
            throw new \InvalidArgumentException("Plantilla desconocida: '$nombre'. Disponibles: " . implode(', ', array_keys(self::disponibles())) . '.');
        }

        return new $clase();
    }

    /** @return array{fondo: string, texto: string, acento: string} */
    abstract public function coloresPorDefecto(): array;

    /** Dibuja la cubierta completa, con sangrado. */
    public function dibujar(Libro $libro, Medidas $medidas): string
    {
        $svg = $this->rectangulo($medidas->lienzo(), ['fill' => $libro->colores['fondo']]);
        $svg .= $this->portada($libro, $medidas->pieza('portada'));
        $svg .= $this->contratapa($libro, $medidas->pieza('contratapa'));
        $svg .= $this->lomo($libro, $medidas->pieza('lomo'));
        if ($libro->solapasMm > 0) {
            $svg .= $this->solapa($libro->solapaContratapa, $libro, $medidas->pieza('solapa-contratapa'));
            $svg .= $this->solapa($libro->solapaPortada, $libro, $medidas->pieza('solapa-portada'));
        }

        return $svg;
    }

    /** Dibuja la portada dentro de su panel de corte. */
    abstract protected function portada(Libro $libro, Rectangulo $panel): string;

    /** Fuente CSS del texto. */
    protected function fuente(): string
    {
        return "Georgia, 'Times New Roman', serif";
    }

    /** Ancho medio de una letra, en múltiplos del tamaño de letra. Aproximado: cada fuente lo cambia. */
    protected function factorAncho(): float
    {
        return 0.5;
    }

    /**
     * Fondo y texto del lomo.
     *
     * @param array{fondo: string, texto: string, acento: string} $colores
     * @return array{fondo: string, texto: string}
     */
    protected function coloresLomo(array $colores): array
    {
        return ['fondo' => $colores['fondo'], 'texto' => $colores['texto']];
    }

    protected function contratapa(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        $svg = $this->linea($x, $panel->y + 22, $x + 12, $panel->y + 22, $colores['acento'], 0.8);
        if ($libro->contratapa !== '') {
            $texto = Texto::ajustar($libro->contratapa, $ancho, $panel->alto * 0.5, 4.2, 2.8, $this->factorAncho(), self::INTERLINEADO);
            $svg .= $this->parrafo($texto['lineas'], $x, $panel->y + 34 + $texto['tamano'] * 0.8, $texto['tamano'], 'start', $colores['texto']);
        }
        $svg .= $this->editorial($libro, $x, $panel->y + $panel->alto - 12, 'start');

        // Hueco en blanco para el código de barras del ISBN: el código lo pone quien imprime.
        $caja = new Rectangulo($panel->x + $panel->ancho - self::MARGEN - 42, $panel->y + $panel->alto - 38, 42, 26);
        $svg .= $this->rectangulo($caja, ['fill' => '#ffffff']);
        if ($libro->isbn !== '') {
            $svg .= $this->parrafo(['ISBN ' . $libro->isbn], $caja->x + 42, $caja->y - 3, 2.8, 'end', $colores['texto']);
        }

        return $svg;
    }

    protected function lomo(Libro $libro, Rectangulo $panel): string
    {
        $colores = $this->coloresLomo($libro->colores);
        // El lomo no tiene borde exterior, así que su color llega también al sangrado de arriba y de abajo.
        $fondo = new Rectangulo($panel->x, 0.0, $panel->ancho, $panel->alto + 2 * $libro->sangradoMm);
        $svg = $this->rectangulo($fondo, ['fill' => $colores['fondo']]);
        if ($panel->ancho < self::LOMO_MINIMO) {
            return $svg;
        }

        $largo = $panel->alto - 2 * self::MARGEN;
        $texto = Texto::unaLinea($libro->titulo . ' · ' . $libro->autor, $largo, min(4.5, $panel->ancho * 0.6), 2.2, $this->factorAncho());

        // El texto va girado 90°. Al girarlo, la línea base queda en x y las letras crecen hacia un lado
        // u otro según el sentido; se corre x para que el texto quede centrado en el lomo.
        $sentido = $libro->lomoDireccion === 'abajo-arriba' ? -1 : 1;
        $x = $panel->x + $panel->ancho / 2 - $sentido * 0.35 * $texto['tamano'];
        $y = $panel->y + $panel->alto / 2;

        return $svg . Svg::elemento('text', [
            'transform' => 'translate(' . Svg::n($x) . ' ' . Svg::n($y) . ') rotate(' . (90 * $sentido) . ')',
            'font-family' => $this->fuente(),
            'font-size' => $texto['tamano'],
            'text-anchor' => 'middle',
            'fill' => $colores['texto'],
        ], Svg::e($texto['texto']));
    }

    /** Solapa interior: texto arriba a la izquierda, con su margen. */
    protected function solapa(string $texto, Libro $libro, Rectangulo $panel): string
    {
        if (trim($texto) === '') {
            return '';
        }

        $margen = 10.0;
        $ajuste = Texto::ajustar($texto, $panel->ancho - 2 * $margen, $panel->alto - 2 * $margen, 4.0, 2.8, $this->factorAncho(), self::INTERLINEADO);

        return $this->parrafo($ajuste['lineas'], $panel->x + $margen, $panel->y + $margen + $ajuste['tamano'] * 0.8, $ajuste['tamano'], 'start', $libro->colores['texto']);
    }

    /** Pie con editorial y colección, si hay alguna. */
    protected function editorial(Libro $libro, float $x, float $y, string $anclaje): string
    {
        $pie = implode(' · ', array_filter([$libro->editorial, $libro->coleccion], static fn (string $dato): bool => $dato !== ''));

        return $pie === '' ? '' : $this->parrafo([$pie], $x, $y, 3.2, $anclaje, $libro->colores['texto']);
    }

    /**
     * Texto de varias líneas con la primera línea base en (x, y). Una línea vacía deja un hueco.
     *
     * @param list<string> $lineas
     * @param array<string, float|int|string> $extra
     */
    protected function parrafo(array $lineas, float $x, float $y, float $tamano, string $anclaje, string $color, array $extra = []): string
    {
        return Svg::elemento('text', [
            'x' => $x,
            'y' => $y,
            'font-family' => $this->fuente(),
            'font-size' => $tamano,
            'text-anchor' => $anclaje,
            'fill' => $color,
        ] + $extra, Svg::lineas($lineas, $x, $tamano, self::INTERLINEADO));
    }

    /**
     * Línea base de la primera línea para que un bloque de `lineas` líneas quede centrado en la zona
     * que va de `y` a `y + alto`.
     */
    protected function baseline(float $y, float $alto, int $lineas, float $tamano): float
    {
        $visible = ($lineas - 1) * $tamano * self::INTERLINEADO + $tamano;

        return $y + ($alto - $visible) / 2 + $tamano * 0.8;
    }

    /** @param array<string, float|int|string> $estilo */
    protected function rectangulo(Rectangulo $zona, array $estilo): string
    {
        return Svg::elemento('rect', ['x' => $zona->x, 'y' => $zona->y, 'width' => $zona->ancho, 'height' => $zona->alto] + $estilo);
    }

    protected function linea(float $x1, float $y1, float $x2, float $y2, string $color, float $grosor): string
    {
        return Svg::elemento('line', ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 'stroke' => $color, 'stroke-width' => $grosor]);
    }
}
