<?php

declare(strict_types=1);

namespace App;

use ArrayIterator;
use Iterator;
use IteratorIterator;

/** La respuesta de una exportacion: un archivo CSV que el navegador descarga en vez de mostrar. */
final class Descarga
{
    /**
     * Manda $filas como un CSV descargable, de a una (con un recorrido por lotes no se
     * junta todo en memoria). $nombreBase sale siempre del codigo, nunca de la URL: va tal
     * cual en el nombre del archivo.
     *
     * La primera fila se pide ANTES de mandar los headers: si la consulta falla, el
     * ManejadorDeErrores contesta con su 500 de siempre en vez de dejar un archivo
     * cortado con un 200.
     *
     * No se cachea (no-store): tiene datos personales de clientes.
     *
     * @param list<string> $encabezados
     * @param iterable<list<string|int|float|null>> $filas
     */
    public static function csv(string $nombreBase, array $encabezados, iterable $filas): void
    {
        $iterador = self::comoIterador($filas);
        $iterador->rewind();

        $salida = fopen('php://output', 'w');
        if ($salida === false) {
            throw new \RuntimeException('No se pudo abrir la salida para escribir el CSV.');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreBase . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');

        $csv = new EscritorCsv($salida);
        $csv->marcaDeOrden();
        $csv->fila($encabezados);
        for (; $iterador->valid(); $iterador->next()) {
            $csv->fila($iterador->current());
        }
        fclose($salida);
    }

    /**
     * @param iterable<list<string|int|float|null>> $filas
     * @return Iterator<int, list<string|int|float|null>>
     */
    private static function comoIterador(iterable $filas): Iterator
    {
        if (is_array($filas)) {
            return new ArrayIterator($filas);
        }

        return $filas instanceof Iterator ? $filas : new IteratorIterator($filas);
    }
}
