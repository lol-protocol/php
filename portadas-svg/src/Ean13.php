<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * El código de barras EAN-13 de un ISBN, como módulos: cada módulo es una franja de ancho fijo, negra (`1`) o blanca
 * (`0`). Un EAN-13 son 95 módulos: guarda de inicio (101), seis dígitos con la codificación L o G según el primer
 * dígito, guarda central (01010), seis dígitos en R y guarda final (101). El primer dígito no se dibuja: lo da el patrón
 * de L y G de los otros seis.
 */
final class Ean13
{
    /** Cuántos módulos tiene el código sin contar las zonas en blanco de los lados. */
    public const MODULOS = 95;

    private const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
    private const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
    private const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
    /** Qué codificación (L o G) lleva cada uno de los seis primeros dígitos, según el primer dígito del EAN. */
    private const PARIDAD = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    /**
     * Los 13 dígitos del EAN de un ISBN-13 o ISBN-10 con el dígito de control bien (un ISBN-10 se pasa a 978 + sus
     * nueve primeros dígitos + el control nuevo), o null si el texto no es un ISBN válido.
     */
    public static function desdeIsbn(string $isbn): ?string
    {
        if (!Isbn::valido($isbn)) {
            return null;
        }
        $limpio = strtoupper((string) preg_replace('/^\s*ISBN(?:-1[03])?:?\s*|[\s-]/i', '', $isbn));
        $base = strlen($limpio) === 13 ? substr($limpio, 0, 12) : '978' . substr($limpio, 0, 9);

        return $base . self::control($base);
    }

    /** El dígito de control de los 12 primeros dígitos de un EAN-13. */
    public static function control(string $doce): string
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int) $doce[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $suma % 10) % 10);
    }

    /** Los 95 módulos de un EAN de 13 dígitos, como una cadena de `0` y `1`. */
    public static function modulos(string $ean): string
    {
        if (preg_match('/^\d{13}$/', $ean) !== 1 || $ean[12] !== self::control(substr($ean, 0, 12))) {
            throw new \InvalidArgumentException("«{$ean}» no es un EAN-13 válido.");
        }
        $paridad = self::PARIDAD[(int) $ean[0]];
        $salida = '101';
        for ($i = 1; $i <= 6; $i++) {
            $salida .= ($paridad[$i - 1] === 'L' ? self::L : self::G)[(int) $ean[$i]];
        }
        $salida .= '01010';
        for ($i = 7; $i <= 12; $i++) {
            $salida .= self::R[(int) $ean[$i]];
        }

        return $salida . '101';
    }

    /**
     * Las franjas negras de unos módulos, como [primer módulo, cuántos]. Las franjas de las guardas (inicio, centro y
     * final) se marcan aparte en `esGuarda`, porque se dibujan más largas.
     *
     * @return list<array{int, int}>
     */
    public static function franjas(string $modulos): array
    {
        $franjas = [];
        $inicio = null;
        foreach (str_split($modulos . '0') as $posicion => $modulo) {
            if ($modulo === '1' && $inicio === null) {
                $inicio = $posicion;
            } elseif ($modulo === '0' && $inicio !== null) {
                $franjas[] = [$inicio, $posicion - $inicio];
                $inicio = null;
            }
        }

        return $franjas;
    }

    /** Si el módulo en esa posición (0 a 94) es de una guarda: la de inicio, la central o la final. */
    public static function esGuarda(int $posicion): bool
    {
        return $posicion < 3 || ($posicion >= 45 && $posicion < 50) || $posicion >= 92;
    }
}
