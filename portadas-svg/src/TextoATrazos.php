<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Escribe texto como contornos en lugar de con una fuente (ver `--trazos`): una línea es un grupo escalado al
 * tamaño de letra y cada letra un `<use>` de su trazado, definido una sola vez en `<defs>`. Así la pieza no
 * depende de ninguna fuente y se ve igual en cualquier visor.
 *
 * No aplica kerning, igual que la medida del texto. Una letra que ni las fuentes ni las fuentes extra tienen
 * deja su hueco (con el ancho medio) y no se dibuja. Las palabras compuestas de antemano (árabe, devanagari…)
 * se colocan enteras, y las de derecha a izquierda se ordenan (ver `enOrdenVisual`).
 *
 * El `<use>` lleva solo `xlink:href`: Inkscape 1.2 no resuelve un `href` sin prefijo y perdería todo el texto, y los
 * demás visores probados (Chromium, librsvg, resvg, CairoSVG) leen el `xlink:href` sin problema.
 */
final class TextoATrazos
{
    /** @var array<string, string> contornos usados por la pieza en curso, por identificador */
    private array $glifos = [];
    /** @var array<string, string> letra corta de cada cara usada, para que los identificadores sean cortos */
    private array $caras = [];

    /** Olvida lo usado: se llama al empezar cada pieza. */
    public function reiniciar(): void
    {
        $this->glifos = [];
        $this->caras = [];
    }

    /**
     * @param list<string> $lineas
     * @param float $espaciado espacio entre letras, en mm
     * @param string $antes transformación previa del grupo, para el texto girado del lomo
     */
    public function escribir(array $lineas, float $x, float $y, float $tamano, string $anclaje, string $color, string $familia, string $estilo, int $peso, MedidaFuente $medida, float $espaciado, float $interlineado, string $antes = ''): string
    {
        $escala = $tamano / 1000;
        $entreLetras = $espaciado / $escala; // en milésimas de em
        $cara = $this->caras["$familia/$estilo/$peso"] ??= chr(97 + count($this->caras));
        $formas = Fuentes::formas();

        $svg = '';
        foreach ($lineas as $indice => $linea) {
            $letras = '';
            $avance = 0.0;
            foreach ($this->enOrdenVisual(Fuentes::trozos($linea, $formas), $formas) as $trozo) {
                if (isset($formas[$trozo])) {
                    [$ancho, , $piezas] = $formas[$trozo];
                    foreach ($piezas as [$px, $py, $contorno]) {
                        $letras .= $this->usar('f' . substr(md5($contorno), 0, 8), $contorno, $avance + $px, $py);
                    }
                    $avance += $ancho;
                    continue;
                }
                foreach (mb_str_split($trozo) as $letra) {
                    $codigo = mb_ord($letra);
                    if ($codigo < 0x20) {
                        continue;
                    }
                    $contorno = Fuentes::contorno($familia, $estilo, $peso, $codigo);
                    if ($contorno === null) {
                        $avance += $medida->em($letra) * 1000 + $entreLetras;
                        continue;
                    }
                    if ($contorno[1] !== '') {
                        $letras .= $this->usar("g$cara$codigo", $contorno[1], $avance);
                    }
                    $avance += $contorno[0] + $entreLetras;
                }
            }
            if ($letras === '') {
                continue;
            }
            $ancho = $avance * $escala;
            $inicio = match ($anclaje) {
                'middle' => $x - $ancho / 2,
                'end' => $x - $ancho,
                default => $x,
            };
            $base = $y + $indice * $tamano * $interlineado;
            $svg .= Svg::elemento('g', [
                'transform' => $antes . 'translate(' . Svg::n($inicio) . ' ' . Svg::n($base) . ') scale(' . rtrim(rtrim(number_format($escala, 6, '.', ''), '0'), '.') . ')',
                'fill' => $color,
            ], "\n" . $letras);
        }

        return $svg;
    }

    /** Si todas las palabras del texto se leen de derecha a izquierda (árabe, hebreo…): un texto así se alinea a la derecha. */
    public static function esDerechaAIzquierda(string $texto): bool
    {
        $formas = Fuentes::formas();
        $palabras = array_filter(Fuentes::trozos($texto, $formas), static fn (string $trozo): bool => trim($trozo) !== '');

        return $palabras !== [] && array_filter($palabras, static fn (string $trozo): bool => $formas[$trozo][1] ?? false) === $palabras;
    }

    /** Los contornos que usan las letras de la pieza, una sola vez cada uno, para referirse a ellos con `<use>`. */
    public function defs(): string
    {
        if ($this->glifos === []) {
            return '';
        }
        ksort($this->glifos);
        $trazados = '';
        foreach ($this->glifos as $id => $contorno) {
            $trazados .= Svg::elemento('path', ['id' => $id, 'd' => $contorno]);
        }

        return "<defs>\n" . $trazados . "</defs>\n";
    }

    /** Un `<use>` del contorno con ese identificador, que se anota para `defs()`. */
    private function usar(string $id, string $contorno, float $x, float $y = 0.0): string
    {
        $this->glifos[$id] = $contorno;

        return Svg::elemento('use', ['xlink:href' => "#$id", 'x' => $x] + ($y === 0.0 ? [] : ['y' => $y]));
    }

    /**
     * Pone en orden visual los trozos de una línea: las palabras de derecha a izquierda compuestas de antemano
     * (árabe, hebreo…) se leen de derecha a izquierda, así que una línea hecha solo de ellas se invierte entera, y
     * en una frase mezclada se invierte cada tramo seguido de ellas, con los espacios de en medio. Es un bidi
     * mínimo: no resuelve números ni paréntesis dentro de un tramo, ni más de un nivel de mezcla.
     *
     * @param list<string> $trozos palabras, signos y espacios, en el orden en que se escribieron
     * @param array<string, array{int, bool, list<array{int, int, string}>}> $formas
     * @return list<string>
     */
    private function enOrdenVisual(array $trozos, array $formas): array
    {
        $derechaAIzquierda = static fn (string $trozo): bool => $formas[$trozo][1] ?? false;
        $palabras = array_filter($trozos, static fn (string $trozo): bool => trim($trozo) !== '');
        if ($palabras !== [] && array_filter($palabras, $derechaAIzquierda) === $palabras) {
            return array_reverse($trozos);
        }

        $resultado = [];
        $tramo = [];
        foreach ($trozos as $trozo) {
            if ($derechaAIzquierda($trozo) || ($tramo !== [] && trim($trozo) === '')) {
                $tramo[] = $trozo;
                continue;
            }
            array_push($resultado, ...self::invertirTramo($tramo));
            $tramo = [];
            $resultado[] = $trozo;
        }
        array_push($resultado, ...self::invertirTramo($tramo));

        return $resultado;
    }

    /**
     * Invierte un tramo de palabras de derecha a izquierda. Un tramo termina en palabra: los espacios del final
     * no son parte de él y se quedan donde estaban.
     *
     * @param list<string> $tramo
     * @return list<string>
     */
    private static function invertirTramo(array $tramo): array
    {
        $cola = [];
        while ($tramo !== [] && trim(end($tramo)) === '') {
            array_unshift($cola, (string) array_pop($tramo));
        }

        return [...array_reverse($tramo), ...$cola];
    }
}
