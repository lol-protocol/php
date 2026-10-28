<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

/**
 * Un dibujo pequeño por categoría de Marcalibros (ver datos/categorias.json), de líneas finas en el color de
 * acento: una lira para la poesía, una balanza para las leyes, un faro para el modernismo anglosajón. Va en
 * la contratapa. Cada dibujo cabe en un cuadrado de `2 × radio` mm de lado centrado en el punto dado.
 *
 * Los dibujos se piensan en un cuadrado de -1 a 1 (con el eje y hacia abajo) y se escalan al radio.
 */
final class Motivo extends Trazos
{
    /** Categorías que tienen dibujo: las 27 de datos/categorias.json. */
    public const CATEGORIAS = [
        'romanticismo', 'realismo', 'naturalismo', 'modernismo', 'modernismo-anglosajon', 'vanguardias', 'gotico',
        'barroco', 'clasicismo', 'simbolismo', 'realismo-magico', 'manuales', 'libros-sagrados', 'leyes', 'cartas',
        'diarios', 'ciencia', 'cocina', 'filosofia', 'teatro', 'viajes', 'cuentos', 'poesia', 'historia',
        'periodismo', 'diccionarios', 'discursos',
    ];

    private readonly float $grosor;
    private readonly float $r;

    private function __construct(private readonly float $cx, private readonly float $cy, float $radio, private readonly string $color)
    {
        $this->grosor = self::grosor($radio);
        $this->r = $radio - $this->grosor;
    }

    /** El dibujo de la categoría, o '' si no tiene. */
    public static function dibujar(string $categoria, float $cx, float $cy, float $radio, string $color): string
    {
        $m = new self($cx, $cy, $radio, $color);

        return match ($categoria) {
            'romanticismo' => $m->amanecer(),
            'realismo' => $m->ciudad(),
            'naturalismo' => $m->trigo(),
            'modernismo' => $m->ondas(),
            'modernismo-anglosajon' => $m->faro(),
            'vanguardias' => $m->formas(),
            'gotico' => $m->ojiva(),
            'barroco' => $m->voluta(),
            'clasicismo' => $m->columna(),
            'simbolismo' => $m->luna(),
            'realismo-magico' => $m->mariposa(),
            'manuales' => $m->engranaje(),
            'libros-sagrados' => $m->vela(),
            'leyes' => $m->balanza(),
            'cartas' => $m->sobre(),
            'diarios' => $m->calendario(),
            'ciencia' => $m->atomo(),
            'cocina' => $m->olla(),
            'filosofia' => $m->esfera(),
            'teatro' => $m->mascara(),
            'viajes' => $m->brujula(),
            'cuentos' => $m->castillo(),
            'poesia' => $m->lira(),
            'historia' => $m->reloj(),
            'periodismo' => $m->periodico(),
            'diccionarios' => $m->lupa(),
            'discursos' => $m->megafono(),
            default => '',
        };
    }

    /**
     * Línea por puntos del cuadrado de -1 a 1.
     *
     * @param list<array{float, float}> $puntos
     */
    private function l(array $puntos, bool $cerrada = false): string
    {
        return self::linea(array_map(fn (array $p): array => [$this->cx + $p[0] * $this->r, $this->cy + $p[1] * $this->r], $puntos), $this->color, $this->grosor, $cerrada);
    }

    private function c(float $x, float $y, float $radio, bool $lleno = false): string
    {
        return self::circulo($this->cx + $x * $this->r, $this->cy + $y * $this->r, $radio * $this->r, $this->color, $this->grosor, $lleno);
    }

    /** Arco de circunferencia (ángulos en grados, 0° a la derecha, crecen hacia abajo). */
    private function a(float $x, float $y, float $radio, float $desde, float $hasta): string
    {
        return self::linea(self::arco($this->cx + $x * $this->r, $this->cy + $y * $this->r, $radio * $this->r, $desde, $hasta), $this->color, $this->grosor);
    }

    private function e(float $x, float $y, float $rx, float $ry, float $giro = 0.0): string
    {
        return self::linea(self::elipse($this->cx + $x * $this->r, $this->cy + $y * $this->r, $rx * $this->r, $ry * $this->r, $giro), $this->color, $this->grosor, true);
    }

    private function h(float $x, float $y, float $largo, float $ancho, float $giro): string
    {
        return self::linea(self::hoja($this->cx + $x * $this->r, $this->cy + $y * $this->r, $largo * $this->r, $ancho * $this->r, $giro), $this->color, $this->grosor, true);
    }

    private function amanecer(): string
    {
        $svg = $this->l([[-1, 0.55], [1, 0.55]]) . $this->a(0, 0.55, 0.5, 180, 360);
        foreach ([195, 232, 270, 308, 345] as $angulo) {
            $rad = deg2rad($angulo);
            $svg .= $this->l([[0.68 * cos($rad), 0.55 + 0.68 * sin($rad)], [0.95 * cos($rad), 0.55 + 0.95 * sin($rad)]]);
        }

        return $svg . $this->l([[-0.6, 0.85], [0.6, 0.85]]);
    }

    private function ciudad(): string
    {
        return $this->l([[-1, 0.8], [-1, 0.1], [-0.7, 0.1], [-0.7, -0.3], [-0.35, -0.3], [-0.35, 0.3], [-0.1, 0.3], [-0.1, -0.8], [0.25, -0.8], [0.25, -0.1], [0.5, -0.1], [0.5, 0.2], [1, 0.2], [1, 0.8]], true)
            . $this->l([[-0.55, -0.1], [-0.5, -0.1]]) . $this->l([[0.05, -0.55], [0.1, -0.55]]) . $this->l([[0.05, -0.3], [0.1, -0.3]]);
    }

    private function trigo(): string
    {
        $svg = $this->l([[0, 0.95], [0, -0.55]]) . $this->h(0, -0.75, 0.4, 0.18, 90);
        foreach ([-0.45, -0.1, 0.25] as $y) {
            $svg .= $this->h(-0.22, $y, 0.5, 0.2, 55) . $this->h(0.22, $y, 0.5, 0.2, -55);
        }

        return $svg;
    }

    private function ondas(): string
    {
        $svg = '';
        foreach ([-0.5, 0.0, 0.5] as $y) {
            $onda = [];
            for ($i = 0; $i <= self::SEGMENTOS; $i++) {
                $t = $i / self::SEGMENTOS;
                $onda[] = [-1 + 2 * $t, $y + 0.17 * sin(2 * M_PI * 2 * $t)];
            }
            $svg .= $this->l($onda);
        }

        return $svg;
    }

    private function faro(): string
    {
        return $this->l([[-0.28, 0.9], [-0.16, -0.4], [0.16, -0.4], [0.28, 0.9]], true)
            . $this->l([[-0.2, -0.4], [-0.2, -0.7], [0.2, -0.7], [0.2, -0.4]]) . $this->l([[-0.26, -0.7], [0, -0.95], [0.26, -0.7]])
            . $this->l([[0.3, -0.62], [0.95, -0.78]]) . $this->l([[0.3, -0.5], [0.95, -0.34]])
            . $this->l([[-0.3, -0.62], [-0.95, -0.78]]) . $this->l([[-0.3, -0.5], [-0.95, -0.34]])
            . $this->l([[-0.6, 0.9], [0.6, 0.9]]) . $this->l([[-0.22, 0.1], [0.22, 0.1]]);
    }

    private function formas(): string
    {
        return $this->c(-0.2, -0.25, 0.5)
            . $this->l([[-0.85, 0.2], [-0.15, 0.2], [-0.15, 0.9], [-0.85, 0.9]], true)
            . $this->l([[0.15, 0.9], [0.9, 0.9], [0.52, 0.0]], true)
            . $this->l([[0.4, -0.85], [0.9, -0.3]]);
    }

    private function ojiva(): string
    {
        return $this->a(0.5, 0.1, 1.0, 180, 240) . $this->a(-0.5, 0.1, 1.0, 300, 360)
            . $this->l([[-0.5, 0.1], [-0.5, 0.9], [0.5, 0.9], [0.5, 0.1]])
            . $this->a(0.3, 0.2, 0.6, 180, 240) . $this->a(-0.3, 0.2, 0.6, 300, 360)
            . $this->l([[-0.3, 0.2], [-0.3, 0.9]]) . $this->l([[0.3, 0.2], [0.3, 0.9]]) . $this->c(0, -0.3, 0.08);
    }

    private function voluta(): string
    {
        return $this->l(self::espiral(-0.45, 0.1, 0.5, 270, 1.5, -1)) . $this->l(self::espiral(0.45, 0.1, 0.5, 270, 1.5, 1)) . $this->l([[-0.45, -0.4], [0.45, -0.4]])
            . $this->c(-0.3, 0.85, 0.07, true) . $this->c(0, 0.85, 0.07, true) . $this->c(0.3, 0.85, 0.07, true);
    }

    private function columna(): string
    {
        $svg = $this->l([[-0.9, -0.55], [0, -0.95], [0.9, -0.55]], true) . $this->l([[-0.9, -0.55], [0.9, -0.55], [0.9, -0.4], [-0.9, -0.4]], true)
            . $this->l([[-0.6, -0.4], [0.6, -0.4], [0.6, -0.25], [-0.6, -0.25]], true) . $this->l([[-0.7, 0.6], [0.7, 0.6], [0.7, 0.8], [-0.7, 0.8]], true);
        foreach ([-0.5, -0.25, 0.0, 0.25, 0.5] as $x) {
            $svg .= $this->l([[$x, -0.25], [$x, 0.6]]);
        }

        return $svg;
    }

    private function luna(): string
    {
        // Media luna: el contorno del círculo grande que queda fuera del pequeño, y el del pequeño que queda
        // dentro del grande.
        [$x1, $y1, $r1, $x2, $y2, $r2] = [-0.1, 0.0, 0.8, 0.3, -0.1, 0.62];
        $n = 2 * self::SEGMENTOS;
        /** @var list<array{array{float, float}, bool}> $fuera */
        $fuera = [];
        /** @var list<array{array{float, float}, bool}> $dentro */
        $dentro = [];
        for ($i = 0; $i < $n; $i++) {
            $rad = 2 * M_PI * $i / $n;
            $grande = [$x1 + $r1 * cos($rad), $y1 + $r1 * sin($rad)];
            $pequeno = [$x2 + $r2 * cos($rad), $y2 + $r2 * sin($rad)];
            $fuera[] = [$grande, hypot($grande[0] - $x2, $grande[1] - $y2) >= $r2];
            $dentro[] = [$pequeno, hypot($pequeno[0] - $x1, $pequeno[1] - $y1) <= $r1];
        }
        return $this->l([...self::tramo($fuera), ...array_reverse(self::tramo($dentro))], true)
            . $this->l(self::estrella(0.62, -0.6, 0.2, 0.05), true);
    }

    /**
     * El tramo seguido de puntos marcados `true`, empezando justo después del hueco para que salga seguido
     * aunque cruce los 0°.
     *
     * @param list<array{array{float, float}, bool}> $puntos
     * @return list<array{float, float}>
     */
    private static function tramo(array $puntos): array
    {
        $n = count($puntos);
        $inicio = 0;
        for ($i = 0; $i < $n; $i++) {
            if (!$puntos[($i + $n - 1) % $n][1] && $puntos[$i][1]) {
                $inicio = $i;
                break;
            }
        }
        $seguido = [];
        for ($k = 0; $k < $n && $puntos[($inicio + $k) % $n][1]; $k++) {
            $seguido[] = $puntos[($inicio + $k) % $n][0];
        }

        return $seguido;
    }

    private function mariposa(): string
    {
        return $this->e(0, 0, 0.05, 0.42)
            . $this->h(-0.42, -0.25, 0.8, 0.55, 25) . $this->h(0.42, -0.25, 0.8, 0.55, -25)
            . $this->h(-0.33, 0.35, 0.6, 0.38, -25) . $this->h(0.33, 0.35, 0.6, 0.38, 25)
            . $this->l([[0, -0.42], [-0.2, -0.8]]) . $this->l([[0, -0.42], [0.2, -0.8]]);
    }

    private function engranaje(): string
    {
        $puntos = [];
        for ($i = 0; $i < 12; $i++) {
            $base = 30 * $i;
            foreach ([[-10, 0.72], [-6, 0.95], [6, 0.95], [10, 0.72]] as [$desvio, $largo]) {
                $rad = deg2rad($base + $desvio);
                $puntos[] = [$largo * cos($rad), $largo * sin($rad)];
            }
        }

        return $this->l($puntos, true) . $this->c(0, 0, 0.3);
    }

    private function vela(): string
    {
        $svg = $this->l([[-0.2, 0.15], [-0.2, 0.9], [0.2, 0.9], [0.2, 0.15]]) . $this->l([[0, 0.15], [0, 0.03]]) . $this->h(0, -0.25, 0.5, 0.28, 90);
        foreach ([-150, -125, -100, -80, -55, -30] as $angulo) {
            $rad = deg2rad($angulo);
            $svg .= $this->l([[0.42 * cos($rad), -0.25 + 0.42 * sin($rad)], [0.64 * cos($rad), -0.25 + 0.64 * sin($rad)]]);
        }

        return $svg;
    }

    private function balanza(): string
    {
        $svg = $this->c(0, -0.85, 0.08) . $this->l([[0, -0.77], [0, 0.8]]) . $this->l([[-0.4, 0.8], [0.4, 0.8]]) . $this->l([[-0.65, -0.55], [0.65, -0.55]]);
        foreach ([-0.65, 0.65] as $x) {
            $svg .= $this->l([[$x - 0.3, 0.05], [$x, -0.55], [$x + 0.3, 0.05]]) . $this->a($x, 0.05, 0.3, 0, 180);
        }

        return $svg;
    }

    private function sobre(): string
    {
        return $this->l([[-0.9, -0.55], [0.9, -0.55], [0.9, 0.55], [-0.9, 0.55]], true) . $this->l([[-0.9, -0.55], [0, 0.15], [0.9, -0.55]])
            . $this->l([[-0.9, 0.55], [-0.2, -0.05]]) . $this->l([[0.9, 0.55], [0.2, -0.05]]);
    }

    private function calendario(): string
    {
        $svg = $this->l([[-0.7, -0.75], [0.7, -0.75], [0.7, 0.85], [-0.7, 0.85]], true) . $this->l([[-0.7, -0.4], [0.7, -0.4]])
            . $this->l([[-0.35, -0.95], [-0.35, -0.6]]) . $this->l([[0.35, -0.95], [0.35, -0.6]]);
        foreach ([-0.4, 0.0, 0.4] as $x) {
            foreach ([-0.1, 0.25, 0.6] as $y) {
                $svg .= $this->c($x, $y, 0.06, true);
            }
        }

        return $svg;
    }

    private function atomo(): string
    {
        return $this->e(0, 0, 0.95, 0.35, 0) . $this->e(0, 0, 0.95, 0.35, 60) . $this->e(0, 0, 0.95, 0.35, 120) . $this->c(0, 0, 0.12, true);
    }

    private function olla(): string
    {
        $svg = $this->l([[-0.65, -0.1], [-0.55, 0.75], [0.55, 0.75], [0.65, -0.1]]) . $this->l([[-0.78, -0.1], [0.78, -0.1]]) . $this->a(0, -0.1, 0.15, 180, 360)
            . $this->l([[-0.65, 0.05], [-0.92, 0.05]]) . $this->l([[0.65, 0.05], [0.92, 0.05]]);
        foreach ([-0.3, 0.0, 0.3] as $x) {
            $vapor = [];
            for ($i = 0; $i <= 24; $i++) {
                $t = $i / 24;
                $vapor[] = [$x + 0.07 * sin(2 * M_PI * 1.5 * $t), -0.3 - 0.6 * $t];
            }
            $svg .= $this->l($vapor);
        }

        return $svg;
    }

    private function esfera(): string
    {
        return $this->c(0, 0, 0.85) . $this->e(0, 0, 0.85, 0.28, 0) . $this->e(0, 0, 0.3, 0.85, 0) . $this->e(0, 0, 0.85, 0.28, 35)
            . $this->l([[0, -0.97], [0, 0.97]]) . $this->c(0, 0, 0.1, true);
    }

    private function mascara(): string
    {
        return $this->e(0, 0, 0.65, 0.85) . $this->h(-0.28, -0.2, 0.38, 0.16, 15) . $this->h(0.28, -0.2, 0.38, 0.16, -15)
            . $this->a(0, 0.1, 0.38, 25, 155) . $this->l([[0, -0.05], [-0.05, 0.1], [0.05, 0.1]]);
    }

    private function brujula(): string
    {
        $svg = $this->c(0, 0, 0.9) . $this->l([[0, -0.7], [0.16, 0], [0, 0.7], [-0.16, 0]], true) . $this->l([[-0.5, 0], [0, -0.12], [0.5, 0], [0, 0.12]], true);
        for ($i = 0; $i < 12; $i++) {
            $rad = deg2rad(30 * $i);
            $largo = $i % 3 === 0 ? 0.75 : 0.82;
            $svg .= $this->l([[0.9 * cos($rad), 0.9 * sin($rad)], [$largo * cos($rad), $largo * sin($rad)]]);
        }

        return $svg;
    }

    private function castillo(): string
    {
        $svg = '';
        foreach ([-1, 1] as $lado) {
            $svg .= $this->l([[$lado * 0.9, 0.85], [$lado * 0.9, -0.1], [$lado * 0.45, -0.1], [$lado * 0.45, 0.85]])
                . $this->l([[$lado * 0.95, -0.1], [$lado * 0.675, -0.75], [$lado * 0.4, -0.1]], true)
                . $this->l([[$lado * 0.75, 0.0], [$lado * 0.75, 0.2]]);
        }

        return $svg . $this->l([[-0.45, 0.85], [-0.45, 0.05], [-0.3, 0.05], [-0.3, 0.2], [-0.15, 0.2], [-0.15, 0.05], [0.15, 0.05], [0.15, 0.2], [0.3, 0.2], [0.3, 0.05], [0.45, 0.05], [0.45, 0.85]])
            . $this->l([[-0.15, 0.85], [-0.15, 0.6]]) . $this->a(0, 0.6, 0.15, 180, 360) . $this->l([[0.15, 0.6], [0.15, 0.85]]) . $this->l([[-0.95, 0.85], [0.95, 0.85]]);
    }

    private function lira(): string
    {
        $brazo = [[-0.6, -0.85], [-0.8, -0.4], [-0.7, 0.15], [-0.35, 0.55], [0, 0.65]];
        $espejo = array_map(static fn (array $p): array => [-$p[0], $p[1]], array_reverse($brazo));
        $svg = $this->l([...$brazo, ...array_slice($espejo, 1)]) . $this->l([[-0.7, -0.85], [0.7, -0.85]]) . $this->l([[-0.3, 0.9], [0.3, 0.9]]) . $this->l([[0, 0.65], [0, 0.9]]);
        foreach ([-0.3, -0.1, 0.1, 0.3] as $x) {
            $svg .= $this->l([[$x, -0.85], [$x, 0.5]]);
        }

        return $svg;
    }

    private function reloj(): string
    {
        return $this->l([[-0.6, -0.9], [0.6, -0.9]]) . $this->l([[-0.6, 0.9], [0.6, 0.9]])
            . $this->l([[-0.5, -0.9], [0.5, -0.9], [0.5, -0.7], [0.06, 0], [0.5, 0.7], [0.5, 0.9], [-0.5, 0.9], [-0.5, 0.7], [-0.06, 0], [-0.5, -0.7]], true)
            . $this->l([[0, 0], [0, 0.45]]) . $this->l([[-0.4, 0.9], [0, 0.5], [0.4, 0.9]]) . $this->l([[-0.3, -0.6], [0.3, -0.6]]);
    }

    private function periodico(): string
    {
        $svg = $this->l([[-0.85, -0.8], [0.85, -0.8], [0.85, 0.8], [-0.85, 0.8]], true) . $this->l([[-0.7, -0.65], [0.7, -0.65], [0.7, -0.35], [-0.7, -0.35]], true)
            . $this->l([[0.05, -0.2], [0.7, -0.2], [0.7, 0.3], [0.05, 0.3]], true);
        foreach ([-0.15, 0.05, 0.25, 0.45, 0.65] as $y) {
            $svg .= $this->l([[-0.7, $y], [-0.1, $y]]);
        }

        return $svg . $this->l([[0.05, 0.5], [0.7, 0.5]]) . $this->l([[0.05, 0.65], [0.7, 0.65]]);
    }

    private function lupa(): string
    {
        return $this->c(-0.2, -0.2, 0.6) . $this->l([[0.23, 0.23], [0.9, 0.9]]) . $this->l([[0.15, 0.3], [0.3, 0.15]])
            . $this->l([[-0.45, 0.0], [-0.2, -0.5], [0.05, 0.0]]) . $this->l([[-0.37, -0.15], [-0.03, -0.15]]);
    }

    private function megafono(): string
    {
        return $this->l([[-0.8, -0.2], [0.3, -0.65], [0.3, 0.65], [-0.8, 0.2]], true) . $this->e(0.3, 0, 0.1, 0.65)
            . $this->l([[-0.5, 0.14], [-0.55, 0.8], [-0.3, 0.8], [-0.25, 0.28]])
            . $this->a(0.2, 0, 0.55, -35, 35) . $this->a(0.2, 0, 0.78, -35, 35);
    }

    /**
     * Estrella de cuatro puntas (largo `$punta`, cintura `$cintura`) centrada en (x, y), en el cuadrado de -1 a 1.
     *
     * @return list<array{float, float}>
     */
    private static function estrella(float $x, float $y, float $punta, float $cintura): array
    {
        $puntos = [];
        for ($i = 0; $i < 8; $i++) {
            $rad = deg2rad(45 * $i - 90);
            $largo = $i % 2 === 0 ? $punta : $cintura;
            $puntos[] = [$x + $largo * cos($rad), $y + $largo * sin($rad)];
        }

        return $puntos;
    }
}
