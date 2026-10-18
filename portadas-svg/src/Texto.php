<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Ajuste de texto a un área sin medir fuentes: cada letra ocupa `factorAncho` veces el tamaño de letra.
 * Es una aproximación; manda la vista previa con la fuente real.
 */
final class Texto
{
    /** Paso al bajar el tamaño de letra buscando el que cabe, en mm. */
    private const PASO = 0.2;

    /**
     * Parte el texto en líneas que caben en `ancho` mm. Los párrafos van separados por una línea en
     * blanco; un salto de línea suelto dentro de un párrafo no corta la línea.
     *
     * @return list<string>
     */
    public static function envolver(string $texto, float $ancho, float $tamano, float $factorAncho): array
    {
        $porLinea = max(1, (int) floor($ancho / ($tamano * $factorAncho)));
        $lineas = [];
        foreach (preg_split('/\n\s*\n/u', trim($texto)) ?: [] as $indice => $parrafo) {
            if ($indice > 0) {
                $lineas[] = '';
            }
            array_push($lineas, ...self::lineasDeParrafo($parrafo, $porLinea));
        }

        return $lineas;
    }

    /**
     * Mayor tamaño de letra entre `tamanoMax` y `tamanoMin` con el que el texto cabe en `ancho` x `alto`
     * (cada línea ocupa `tamano * interlineado`). Si ni al mínimo cabe, lo recorta y pone «…».
     *
     * @return array{tamano: float, lineas: list<string>}
     */
    public static function ajustar(string $texto, float $ancho, float $alto, float $tamanoMax, float $tamanoMin, float $factorAncho, float $interlineado): array
    {
        if (trim($texto) === '') {
            return ['tamano' => $tamanoMax, 'lineas' => []];
        }

        for ($paso = 0; $tamanoMax - $paso * self::PASO >= $tamanoMin - 1e-9; $paso++) {
            $tamano = $tamanoMax - $paso * self::PASO;
            $lineas = self::envolver($texto, $ancho, $tamano, $factorAncho);
            if (count($lineas) * $tamano * $interlineado <= $alto) {
                return ['tamano' => $tamano, 'lineas' => $lineas];
            }
        }

        $lineas = self::envolver($texto, $ancho, $tamanoMin, $factorAncho);
        $maximas = max(1, (int) floor($alto / ($tamanoMin * $interlineado)));
        if (count($lineas) > $maximas) {
            $lineas = array_slice($lineas, 0, $maximas);
            $ultima = count($lineas) - 1;
            $lineas[$ultima] = rtrim(mb_substr($lineas[$ultima], 0, -1)) . '…';
        }

        return ['tamano' => $tamanoMin, 'lineas' => $lineas];
    }

    /**
     * Tamaño para que una sola línea quepa en `ancho`. Si ni al mínimo cabe, la recorta con «…».
     *
     * @return array{tamano: float, texto: string}
     */
    public static function unaLinea(string $texto, float $ancho, float $tamanoMax, float $tamanoMin, float $factorAncho): array
    {
        $tamano = min($tamanoMax, $ancho / (max(1, mb_strlen($texto)) * $factorAncho));
        if ($tamano >= $tamanoMin) {
            return ['tamano' => $tamano, 'texto' => $texto];
        }

        $porLinea = max(1, (int) floor($ancho / ($tamanoMin * $factorAncho)));
        if (mb_strlen($texto) > $porLinea) {
            $texto = rtrim(mb_substr($texto, 0, $porLinea - 1)) . '…';
        }

        return ['tamano' => $tamanoMin, 'texto' => $texto];
    }

    /** @return list<string> */
    private static function lineasDeParrafo(string $parrafo, int $porLinea): array
    {
        $lineas = [];
        $actual = '';
        foreach (preg_split('/\s+/u', trim($parrafo), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            while (mb_strlen($palabra) > $porLinea) {
                if ($actual !== '') {
                    $lineas[] = $actual;
                    $actual = '';
                }
                $lineas[] = mb_substr($palabra, 0, $porLinea);
                $palabra = mb_substr($palabra, $porLinea);
            }

            if ($actual === '') {
                $actual = $palabra;
            } elseif (mb_strlen($actual . ' ' . $palabra) <= $porLinea) {
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
}
