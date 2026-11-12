<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Medida con la tabla de anchos de una fuente (ver datos/anchos.json) y su kerning (ver datos/kerning.json): el
 * ajuste de los pares de letras (latinas, griegas y cirílicas) dentro de una palabra, que casi siempre acorta la línea.
 * Lo que no está en la tabla (otras escrituras) no se ajusta, así que ahí la medida sale igual o algo mayor que la real.
 */
final class MedidaFuente implements Medida
{
    /**
     * @param array<int, int> $anchos código Unicode => ancho en milésimas de em
     * @param array<int, int> $extras lo mismo, para las letras que la fuente no tiene pero sí las fuentes extra (ver Fuentes::usarExtras)
     * @param array<string, int> $formas palabra compuesta de antemano (árabe, devanagari…) => su ancho en milésimas de em
     */
    public function __construct(
        private readonly array $anchos,
        /** Ancho que se da a una letra que ni la fuente ni las extra tienen: la media de las minúsculas. */
        private readonly int $promedio,
        private readonly int $unidades,
        private readonly array $extras = [],
        private readonly array $formas = [],
        /** @var array<string, string> letra que va antes en un par => la letra base con la que se ajusta igual («á» => «a») */
        private readonly array $primero = [],
        /** @var array<string, string> lo mismo, para la letra que va después */
        private readonly array $segundo = [],
        /** @var array<string, int> ajuste de cada par de letras («AV»), en milésimas de em */
        private readonly array $pares = [],
    ) {
    }

    public function em(string $texto): float
    {
        if ($this->formas === []) {
            return $this->sumar($texto) / $this->unidades;
        }

        // Una palabra compuesta de antemano mide lo que mide compuesta, no la suma de sus letras sueltas.
        $suma = 0;
        foreach (Fuentes::trozos($texto, $this->formas) as $trozo) {
            $suma += $this->formas[$trozo] ?? $this->sumar($trozo);
        }

        return $suma / $this->unidades;
    }

    private function sumar(string $texto): int
    {
        $suma = 0;
        $anterior = '';
        foreach (mb_str_split($texto) as $letra) {
            $codigo = mb_ord($letra);
            if ($codigo < 0x20) {
                $anterior = '';
                continue;
            }
            $suma += ($this->anchos[$codigo] ?? $this->extras[$codigo] ?? $this->promedio) + $this->ajuste($anterior, $letra);
            $anterior = $letra;
        }

        return $suma;
    }

    /** Lo que se acerca (negativo) o se separa la letra `$b` de la `$a` que la precede, en milésimas de em. */
    public function ajuste(string $a, string $b): int
    {
        return $a === '' || $this->pares === [] ? 0 : $this->pares[($this->primero[$a] ?? $a) . ($this->segundo[$b] ?? $b)] ?? 0;
    }

    /**
     * Las letras de `texto` que ni las fuentes extra tienen: con `--trazos` no se pueden dibujar. Las que sí
     * tiene alguna fuente extra (y esta no) se dibujan solo con `--trazos`.
     */
    public function sinContorno(string $texto): string
    {
        $faltan = [];
        foreach (Fuentes::trozos($texto, $this->formas) as $trozo) {
            if (isset($this->formas[$trozo])) {
                continue;
            }
            foreach (mb_str_split($this->noCubiertas($trozo)) as $letra) {
                if (!isset($this->extras[mb_ord($letra)])) {
                    $faltan[$letra] = true;
                }
            }
        }

        return implode('', array_keys($faltan));
    }

    /** Las letras de `texto` que la fuente no tiene, sin repetir y en el orden en que salen. */
    public function noCubiertas(string $texto): string
    {
        $faltan = [];
        foreach (mb_str_split($texto) as $letra) {
            $codigo = mb_ord($letra);
            if ($codigo >= 0x20 && !isset($this->anchos[$codigo])) {
                $faltan[$letra] = true;
            }
        }

        return implode('', array_keys($faltan));
    }
}
