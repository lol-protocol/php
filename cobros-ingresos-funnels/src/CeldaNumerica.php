<?php

declare(strict_types=1);

namespace App;

use App\Repositories\MonedaRepository;

/**
 * Un numero de una celda de CSV con los decimales que se le piden. EscritorCsv escribe un float suelto con dos (un
 * saldo en dolares), pero un monto en yenes o en wones no tiene centavos: `1500.00` en una planilla muestra una
 * precision que la moneda no tiene, y no coincide con la pantalla (`¥1,500`). Sigue siendo un numero y no un texto:
 * una planilla lo suma, y el escritor no le pone apostrofe a un saldo negativo.
 */
final class CeldaNumerica
{
    public function __construct(
        public readonly float $valor,
        public readonly int $decimales,
    ) {
    }

    /** Un monto en su moneda original, con los mismos decimales con que lo muestra la pantalla (dinero_en_moneda). */
    public static function monto(float $monto, string $codigoMoneda): self
    {
        return new self($monto, MonedaRepository::decimalesParaMostrar($monto, $codigoMoneda));
    }
}
