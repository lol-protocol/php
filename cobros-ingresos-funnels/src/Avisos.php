<?php

declare(strict_types=1);

namespace App;

/**
 * Los mensajes cortos que una pantalla muestra arriba (views/_avisos.php):
 * un aviso cuando lo pedido por URL no se pudo respetar (un periodo que no
 * existe, un rango al reves) y, en las pantallas de destino de un alta, una
 * edicion o una anulacion, la confirmacion de que se hizo. Cada aviso es
 * ['tipo' => ..., 'texto' => ...]: el tipo solo decide el color.
 */
final class Avisos
{
    public const OK = 'ok';
    public const ATENCION = 'atencion';

    /**
     * Las redirecciones de las altas, ediciones y anulaciones llevan el id de lo
     * que acaban de hacer (?creada=ID para una boleta, ?creado=ID para un pago,
     * ?creado=1 para un cliente, que ya va con su ?id=): hasta ahora ninguna
     * pantalla las leia y no habia confirmacion en ningun lado. Por pantalla de
     * destino, parametro => mensaje (%d es el id).
     */
    private const CONFIRMACIONES = [
        'cobros' => ['creada' => 'Boleta #%d creada.', 'editada' => 'Boleta #%d actualizada.', 'anulada' => 'Boleta #%d anulada.'],
        'pagos' => ['creado' => 'Pago #%d registrado.', 'editado' => 'Pago #%d actualizado.', 'anulado' => 'Pago #%d anulado.'],
        'cliente' => ['creado' => 'Cliente creado.'],
    ];

    /**
     * La confirmacion que corresponde a $pagina segun lo que pide la URL, o
     * nada. Solo vale un id entero positivo: cualquier otra cosa en esos
     * parametros se ignora, y el mensaje nunca repite texto de la URL.
     * @return list<array{tipo: string, texto: string}>
     */
    public static function confirmaciones(string $pagina): array
    {
        foreach (self::CONFIRMACIONES[$pagina] ?? [] as $parametro => $mensaje) {
            $id = filter_var(trim((string) ($_GET[$parametro] ?? '')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($id !== false) {
                return [self::ok(sprintf($mensaje, $id))];
            }
        }

        return [];
    }

    /** @return array{tipo: string, texto: string} */
    public static function ok(string $texto): array
    {
        return ['tipo' => self::OK, 'texto' => $texto];
    }

    /** @return array{tipo: string, texto: string} */
    public static function atencion(string $texto): array
    {
        return ['tipo' => self::ATENCION, 'texto' => $texto];
    }
}
