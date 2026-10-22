<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Medidas de la cubierta, en milímetros. La cubierta completa, de izquierda a derecha, es:
 *
 *   sangrado | solapa | contratapa | lomo | portada | solapa | sangrado
 *
 * Sin solapas, esas dos no existen y el sangrado de cada lado pega con la contratapa o con la portada.
 * El alto es el corte más un sangrado arriba y otro abajo.
 */
final class Medidas
{
    public readonly float $anchoTotal;
    public readonly float $altoTotal;

    public function __construct(private readonly Libro $libro)
    {
        $this->anchoTotal = 2 * $libro->formato->sangrado + 2 * $libro->formato->solapas + 2 * $libro->formato->ancho + $libro->formato->lomo;
        $this->altoTotal = $libro->formato->alto + 2 * $libro->formato->sangrado;
    }

    /** Todo el lienzo de la cubierta completa, con sangrado. */
    public function lienzo(): Rectangulo
    {
        return new Rectangulo(0.0, 0.0, $this->anchoTotal, $this->altoTotal);
    }

    /** Área de corte de un panel, sin sangrado. */
    public function pieza(string $nombre): Rectangulo
    {
        $libro = $this->libro;
        $b = $libro->formato->sangrado;
        $f = $libro->formato->solapas;
        $a = $libro->formato->ancho;
        $s = $libro->formato->lomo;
        $alto = $libro->formato->alto;

        return match ($nombre) {
            'solapa-contratapa' => new Rectangulo($b, $b, $f, $alto),
            'contratapa' => new Rectangulo($b + $f, $b, $a, $alto),
            'lomo' => new Rectangulo($b + $f + $a, $b, $s, $alto),
            'portada' => new Rectangulo($b + $f + $a + $s, $b, $a, $alto),
            'solapa-portada' => new Rectangulo($b + $f + 2 * $a + $s, $b, $f, $alto),
            default => throw new \InvalidArgumentException("Pieza desconocida: '$nombre'."),
        };
    }

    /**
     * Paneles que dibuja cada vista. El sobrelomo lleva también las solapas si el libro las tiene.
     *
     * @return list<string>
     */
    public function paneles(string $vista): array
    {
        return match ($vista) {
            'portada' => ['portada'],
            'contratapa' => ['contratapa'],
            'lomo' => ['lomo'],
            'cubierta' => ['contratapa', 'lomo', 'portada'],
            'sobrelomo' => $this->libro->formato->solapas > 0
                ? ['solapa-contratapa', 'contratapa', 'lomo', 'portada', 'solapa-portada']
                : ['contratapa', 'lomo', 'portada'],
            default => throw new \InvalidArgumentException("Vista desconocida: '$vista'."),
        };
    }

    /**
     * Zona que muestra cada archivo: el panel, más el sangrado de los bordes que quedan al aire.
     * `cubierta` es contratapa, lomo y portada; `sobrelomo` es la cubierta completa con solapas.
     */
    public function vista(string $nombre): Rectangulo
    {
        $sinSolapas = $this->libro->formato->solapas === 0.0;
        $b = $this->libro->formato->sangrado;
        $a = $this->libro->formato->ancho;
        $contratapa = $this->pieza('contratapa')->x;
        $lomo = $this->pieza('lomo')->x;
        $portada = $this->pieza('portada')->x;

        [$desde, $hasta] = match ($nombre) {
            'portada' => [$portada, $portada + $a + ($sinSolapas ? $b : 0.0)],
            'contratapa' => [$sinSolapas ? 0.0 : $contratapa, $lomo],
            'lomo' => [$lomo, $portada],
            'cubierta' => [$sinSolapas ? 0.0 : $contratapa, $sinSolapas ? $this->anchoTotal : $portada + $a],
            'sobrelomo' => [0.0, $this->anchoTotal],
            default => throw new \InvalidArgumentException("Vista desconocida: '$nombre'."),
        };

        return new Rectangulo($desde, 0.0, $hasta - $desde, $this->altoTotal);
    }
}
