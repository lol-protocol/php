<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Ajuste de texto a un área. El ancho de cada texto lo da una `Medida`: con la tabla de una fuente, el
 * ancho real; con un factor fijo, un número redondo para las pruebas.
 */
final class Texto
{
    /** Paso al bajar el tamaño de letra buscando el que cabe, en mm. */
    private const PASO = 0.2;

    /**
     * Parte el texto en líneas que caben en `ancho` mm a ese tamaño de letra. Los párrafos van separados
     * por una línea en blanco; un salto de línea suelto dentro de un párrafo no corta la línea.
     *
     * @return list<string>
     */
    public static function envolver(string $texto, float $ancho, float $tamano, Medida $medida): array
    {
        $cabe = static fn (string $linea): bool => $medida->em($linea) * $tamano <= $ancho + 1e-9;
        $lineas = [];
        foreach (preg_split('/\n\s*\n/u', trim($texto)) ?: [] as $indice => $parrafo) {
            if ($indice > 0) {
                $lineas[] = '';
            }
            array_push($lineas, ...self::lineasDeParrafo($parrafo, $cabe));
        }

        return $lineas;
    }

    /**
     * Mayor tamaño de letra entre `tamanoMax` y `tamanoMin` con el que el texto cabe en `ancho` x `alto`
     * (cada línea ocupa `tamano * interlineado`). Si ni al mínimo cabe, lo recorta y pone «…».
     *
     * Con `$equilibrar`, las líneas salen lo más parejas posible en lugar de llenar cada una hasta el
     * borde: un título no acaba con una sola palabra suelta en la última línea.
     *
     * @return array{tamano: float, lineas: list<string>}
     */
    public static function ajustar(string $texto, float $ancho, float $alto, float $tamanoMax, float $tamanoMin, Medida $medida, float $interlineado, bool $equilibrar = false): array
    {
        if (trim($texto) === '') {
            return ['tamano' => $tamanoMax, 'lineas' => []];
        }

        for ($paso = 0; $tamanoMax - $paso * self::PASO >= $tamanoMin - 1e-9; $paso++) {
            $tamano = $tamanoMax - $paso * self::PASO;
            $lineas = self::envolver($texto, $ancho, $tamano, $medida);
            if (count($lineas) * $tamano * $interlineado <= $alto) {
                return ['tamano' => $tamano, 'lineas' => $equilibrar ? self::equilibrar($texto, $ancho, $tamano, $medida, $lineas) : $lineas];
            }
        }

        $lineas = self::envolver($texto, $ancho, $tamanoMin, $medida);
        $maximas = max(1, (int) floor($alto / ($tamanoMin * $interlineado)));
        if (count($lineas) > $maximas) {
            $lineas = array_slice($lineas, 0, $maximas);
            $ultima = (string) array_pop($lineas);
            $lineas[] = self::conPuntos($ultima, static fn (string $linea): bool => $medida->em($linea) * $tamanoMin <= $ancho + 1e-9);
        }

        return ['tamano' => $tamanoMin, 'lineas' => $lineas];
    }

    /**
     * Tamaño para que una sola línea quepa en `ancho`. Cada letra ocupa su ancho según la `Medida` más el
     * `espaciado` entre letras. Si ni al mínimo cabe, la recorta con «…».
     *
     * @return array{tamano: float, texto: string}
     */
    public static function unaLinea(string $texto, float $ancho, float $tamanoMax, float $tamanoMin, Medida $medida, float $espaciado = 0.0): array
    {
        $letras = max(1, mb_strlen($texto));
        $em = $medida->em($texto);
        $tamano = $em > 0 ? min($tamanoMax, ($ancho - $espaciado * $letras) / $em) : $tamanoMax;
        if ($tamano >= $tamanoMin) {
            return ['tamano' => $tamano, 'texto' => $texto];
        }

        $cabe = static fn (string $linea): bool => $medida->em($linea) * $tamanoMin + $espaciado * mb_strlen($linea) <= $ancho + 1e-9;

        return ['tamano' => $tamanoMin, 'texto' => self::conPuntos($texto, $cabe)];
    }

    /**
     * Reparte el texto en las mismas líneas que da `envolver`, pero lo más parejas posible: busca el ancho
     * más estrecho con el que siguen saliendo ese número de líneas, sin partir ninguna palabra.
     *
     * @param list<string> $lineas lo que dio `envolver` con el ancho completo
     * @return list<string>
     */
    private static function equilibrar(string $texto, float $ancho, float $tamano, Medida $medida, array $lineas): array
    {
        $cuantas = count($lineas);
        if ($cuantas < 2 || in_array('', $lineas, true)) {
            return $lineas;
        }

        $palabraMasAncha = 0.0;
        foreach (preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $palabraMasAncha = max($palabraMasAncha, $medida->em($palabra) * $tamano);
        }

        $mejor = $lineas;
        $desde = $palabraMasAncha;
        $hasta = $ancho;
        for ($vuelta = 0; $vuelta < 24 && $hasta - $desde > 1e-3; $vuelta++) {
            $medio = ($desde + $hasta) / 2;
            $candidatas = self::envolver($texto, $medio, $tamano, $medida);
            if (count($candidatas) <= $cuantas) {
                $mejor = $candidatas;
                $hasta = $medio;
            } else {
                $desde = $medio;
            }
        }

        return count($mejor) === $cuantas ? $mejor : $lineas;
    }

    /**
     * Acorta `texto` hasta que, con «…» al final, cabe. Si ya cabe con los puntos puestos, no le quita nada:
     * los puntos solo avisan de que el texto sigue.
     *
     * @param callable(string): bool $cabe
     */
    private static function conPuntos(string $texto, callable $cabe): string
    {
        $letras = mb_str_split($texto);
        for ($largo = count($letras); $largo >= 1; $largo--) {
            $candidato = rtrim(implode('', array_slice($letras, 0, $largo))) . '…';
            if ($cabe($candidato)) {
                return $candidato;
            }
        }

        return '…';
    }

    /**
     * @param callable(string): bool $cabe si una línea cabe en el ancho disponible
     * @return list<string>
     */
    private static function lineasDeParrafo(string $parrafo, callable $cabe): array
    {
        $lineas = [];
        $actual = '';
        foreach (preg_split('/\s+/u', trim($parrafo), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            // Una palabra más ancha que la línea se parte por letras.
            while (!$cabe($palabra)) {
                if ($actual !== '') {
                    $lineas[] = $actual;
                    $actual = '';
                }
                [$trozo, $palabra] = self::partir($palabra, $cabe);
                $lineas[] = $trozo;
                if ($palabra === '') {
                    continue 2;
                }
            }

            if ($actual === '') {
                $actual = $palabra;
            } elseif ($cabe($actual . ' ' . $palabra)) {
                $actual .= ' ' . $palabra;
            } else {
                $lineas[] = $actual;
                $actual = $palabra;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas;
    }

    /**
     * Parte una palabra en el trozo más largo que cabe (al menos una letra) y lo que sobra.
     *
     * @param callable(string): bool $cabe
     * @return array{string, string}
     */
    private static function partir(string $palabra, callable $cabe): array
    {
        $letras = mb_str_split($palabra);
        $n = 1;
        while ($n < count($letras) && $cabe(implode('', array_slice($letras, 0, $n + 1)))) {
            $n++;
        }

        return [implode('', array_slice($letras, 0, $n)), implode('', array_slice($letras, $n))];
    }
}
