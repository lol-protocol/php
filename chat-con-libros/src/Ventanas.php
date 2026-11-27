<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Parte los párrafos de un libro largo en ventanas de unas pocas oraciones que se solapan.
 *
 * En un libro con párrafos de una página, un párrafo gana la búsqueda solo por tener muchas
 * palabras, y la respuesta tiene que mostrar el trozo que importa. Cada ventana conserva el
 * capítulo de su párrafo. Solo PHP: la versión en Java busca por párrafos.
 */
final class Ventanas
{
    /**
     * Las oraciones de un texto. Corta después de «. ? ! …» (y de las comillas o paréntesis de
     * cierre) cuando sigue un espacio y una mayúscula, un signo de apertura o una raya de diálogo.
     *
     * @return list<string>
     */
    public static function oraciones(string $texto): array
    {
        $partes = preg_split('/(?<=[.?!…])[»")\]]?\s+(?=[\p{Lu}¿¡«"(—–-])/u', trim($texto)) ?: [];
        return array_values(array_filter(
            array_map('trim', $partes),
            static fn (string $s): bool => $s !== '',
        ));
    }

    /**
     * Un párrafo de menos de $corto caracteres («Y dijo don Quijote:», «—preguntó Maritornes.»)
     * se une con el siguiente del mismo capítulo: solo no dice nada y, con el peso del título,
     * ganaría la búsqueda sin tener contenido.
     *
     * @param list<array{capitulo:string, texto:string}> $parrafos
     * @return list<array{capitulo:string, texto:string}>
     */
    public static function unirCortos(array $parrafos, int $corto): array
    {
        if ($corto <= 0) {
            return $parrafos;
        }
        $salida = [];
        $pendiente = null;
        foreach ($parrafos as $p) {
            if ($pendiente !== null && $pendiente['capitulo'] === $p['capitulo']) {
                $p = ['capitulo' => $p['capitulo'], 'texto' => $pendiente['texto'] . ' ' . $p['texto']];
            } elseif ($pendiente !== null) {
                $salida[] = $pendiente;
            }
            $pendiente = null;
            if (mb_strlen($p['texto']) < $corto) {
                $pendiente = $p;
                continue;
            }
            $salida[] = $p;
        }
        if ($pendiente !== null) {
            $salida[] = $pendiente;
        }
        return $salida;
    }

    /**
     * Ventanas de $tamano oraciones que avanzan de $tamano - $solape en $tamano - $solape. Un
     * párrafo con $tamano oraciones o menos es una sola ventana.
     *
     * @param list<array{capitulo:string, texto:string}> $parrafos
     * @return list<array{capitulo:string, texto:string}>
     */
    public static function partir(array $parrafos, int $tamano, int $solape, int $corto = 0): array
    {
        $tamano = max(1, $tamano);
        $paso = max(1, $tamano - $solape);
        $ventanas = [];
        foreach (self::unirCortos($parrafos, $corto) as $p) {
            $o = self::oraciones($p['texto']);
            if (count($o) <= $tamano) {
                $ventanas[] = $p;
                continue;
            }
            for ($k = 0; $k < count($o); $k += $paso) {
                $ventanas[] = ['capitulo' => $p['capitulo'], 'texto' => implode(' ', array_slice($o, $k, $tamano))];
                if ($k + $tamano >= count($o)) {
                    break;
                }
            }
        }
        return $ventanas;
    }
}
