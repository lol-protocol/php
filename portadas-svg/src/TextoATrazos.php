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
                $anterior = '';
                foreach (mb_str_split($trozo) as $letra) {
                    $codigo = mb_ord($letra);
                    if ($codigo < 0x20) {
                        $anterior = '';
                        continue;
                    }
                    // El kerning acerca o separa la letra de la anterior, igual que al medir.
                    $avance += $medida->ajuste($anterior, $letra);
                    $anterior = $letra;
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

    /** Si el párrafo se lee de derecha a izquierda: su primera palabra con dirección es de derecha a izquierda (árabe, hebreo…). */
    public static function esDerechaAIzquierda(string $texto): bool
    {
        $formas = Fuentes::formas();
        foreach (Fuentes::trozos($texto, $formas) as $trozo) {
            if ($formas[$trozo][1] ?? false) {
                return true;
            }
            if (preg_match('/\p{L}/u', $trozo) === 1) {
                return false;
            }
        }

        return false;
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

    /** Signos que se espejan cuando caen dentro de un tramo de derecha a izquierda. */
    private const ESPEJOS = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«', '‹' => '›', '›' => '‹'];

    /**
     * Pone en orden visual los trozos de una línea con un bidi reducido (UAX nº 9, sin niveles de incrustación):
     *
     * - Las palabras de derecha a izquierda compuestas de antemano (árabe, hebreo…) son fuertes «R»; el resto de palabras
     *   con letras, «L». La dirección del párrafo es la de la primera palabra fuerte.
     * - Un número toma la dirección de la palabra fuerte que lo precede (dentro de un tramo R sigue escribiéndose de
     *   izquierda a derecha, pero ocupa su lugar en el orden de lectura).
     * - Un espacio o un signo entre dos tramos de la misma dirección es de esa dirección; entre dos distintas, de la del
     *   párrafo. Un paréntesis, corchete o comilla angular en un tramo R se espeja.
     * - Un párrafo de derecha a izquierda invierte el orden de sus tramos; dentro de un tramo R, los trozos se invierten.
     *
     * No resuelve números con signos pegados ni más de un nivel de mezcla.
     *
     * @param list<string> $trozos palabras, signos y espacios, en el orden en que se escribieron
     * @param array<string, array{int, bool, list<array{int, int, string}>}> $formas
     * @return list<string>
     */
    private function enOrdenVisual(array $trozos, array $formas): array
    {
        if ($trozos === []) {
            return [];
        }
        // 1. Clase de cada trozo: R, L (fuertes), N (número) o neutro (espacio o signo).
        $clases = [];
        foreach ($trozos as $i => $trozo) {
            $clases[$i] = match (true) {
                ($formas[$trozo][1] ?? false) === true => 'R',
                preg_match('/\p{L}/u', $trozo) === 1 => 'L',
                preg_match('/^\p{Nd}[\p{Nd}.,:%\/-]*$/u', $trozo) === 1 => 'N',
                default => '-',
            };
        }
        $base = 'L';
        foreach ($clases as $clase) {
            if ($clase === 'R' || $clase === 'L') {
                $base = $clase;
                break;
            }
        }
        if (!in_array('R', $clases, true)) {
            return $trozos;
        }

        // 2. Dirección de cada trozo: los números siguen a la palabra fuerte anterior (o al párrafo, si no hay).
        $direcciones = [];
        $anterior = $base;
        foreach ($clases as $i => $clase) {
            if ($clase === 'R' || $clase === 'L') {
                $anterior = $clase;
                $direcciones[$i] = $clase;
            } elseif ($clase === 'N') {
                $direcciones[$i] = $anterior;
            } else {
                $direcciones[$i] = null;
            }
        }
        // 3. Los neutros entre dos trozos de la misma dirección la toman; si no, la del párrafo.
        $total = count($trozos);
        foreach ($direcciones as $i => $direccion) {
            if ($direccion !== null) {
                continue;
            }
            $antes = null;
            for ($k = $i - 1; $k >= 0 && $antes === null; $k--) {
                $antes = $direcciones[$k];
            }
            $despues = null;
            for ($k = $i + 1; $k < $total && $despues === null; $k++) {
                $despues = $clases[$k] === '-' ? null : ($direcciones[$k] ?? $clases[$k]);
            }
            $direcciones[$i] = $antes !== null && $antes === $despues ? $antes : $base;
        }

        // 4. Tramos seguidos de la misma dirección; el espejo de los signos de un tramo R.
        $tramos = [];
        foreach ($trozos as $i => $trozo) {
            $direccion = (string) $direcciones[$i];
            $ultimo = array_key_last($tramos);
            if ($ultimo === null || $tramos[$ultimo]['dir'] !== $direccion) {
                $tramos[] = ['dir' => $direccion, 'trozos' => []];
                $ultimo = array_key_last($tramos);
            }
            $tramos[$ultimo]['trozos'][] = $direccion === 'R' && $clases[$i] === '-' ? (self::ESPEJOS[$trozo] ?? $trozo) : $trozo;
        }
        foreach ($tramos as &$tramo) {
            if ($tramo['dir'] === 'R') {
                $tramo['trozos'] = array_reverse($tramo['trozos']);
            }
        }
        unset($tramo);
        if ($base === 'R') {
            $tramos = array_reverse($tramos);
        }

        return array_merge(...array_map(static fn (array $tramo): array => $tramo['trozos'], $tramos));
    }
}
