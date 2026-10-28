<?php

declare(strict_types=1);

namespace App;

/**
 * Escribe filas en formato CSV (RFC 4180: comas, comillas dobles solo donde hacen falta
 * y fin de linea CRLF) sobre un stream.
 *
 * Una celda de texto que empieza con = + - @ (o con tabulador o retorno de carro) la
 * interpreta Excel o LibreOffice como una formula: el nombre de un cliente que sea
 * «=HYPERLINK(...)» ejecuta algo al abrir el archivo (inyeccion de formulas, CSV
 * injection). Por eso todo texto lleva delante un apostrofe cuando empieza asi, que
 * la planilla muestra como texto y no se interpreta. Los numeros (int y float) no
 * pasan por ahi: un saldo de -5.00 tiene que seguir siendo un numero.
 */
final class EscritorCsv
{
    /** Con lo que puede empezar un texto para que una planilla lo tome por formula. */
    private const INICIOS_DE_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /** @var resource */
    private $salida;

    /** @param resource $salida un stream abierto para escribir */
    public function __construct($salida)
    {
        $this->salida = $salida;
    }

    /**
     * La marca de orden de bytes UTF-8, que va una sola vez al principio: sin ella, Excel
     * abre el archivo en la codificacion del sistema y las tildes y las enes salen rotas.
     */
    public function marcaDeOrden(): void
    {
        fwrite($this->salida, "\xEF\xBB\xBF");
    }

    /** @param list<string|int|float|null> $celdas */
    public function fila(array $celdas): void
    {
        // El parametro $escape va vacio a proposito: es el comportamiento del RFC 4180 (una
        // comilla se escapa duplicandola) y el unico que PHP 8.4 no avisa que va a cambiar.
        fputcsv($this->salida, array_map(self::celda(...), $celdas), ',', '"', '', "\r\n");
    }

    /** Una celda como texto de CSV: los enteros tal cual, los decimales con dos cifras, null vacio y el texto sin formulas. */
    public static function celda(string|int|float|null $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            $redondeado = round($valor, 2);

            // -0.00 (un saldo de -0.001) es un cero: sin esto saldria con el signo.
            return number_format($redondeado == 0.0 ? 0.0 : $redondeado, 2, '.', '');
        }

        return self::sinFormula($valor);
    }

    /** El texto, con un apostrofe delante si una planilla lo tomaria por una formula. */
    public static function sinFormula(string $texto): string
    {
        return $texto !== '' && in_array($texto[0], self::INICIOS_DE_FORMULA, true) ? "'" . $texto : $texto;
    }
}
