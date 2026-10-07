<?php

declare(strict_types=1);

namespace App\Tasas;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Lo que dice una fuente de tasas de cambio, ya leido y validado. Entiende el
 * formato "una moneda base y sus tasas" que usan casi todos los servicios
 * gratuitos: ExchangeRate-API (open.er-api.com, base_code y rates), Open Exchange
 * Rates (base, rates y timestamp), Frankfurter (base, rates y date).
 *
 *     {"base_code": "USD", "time_last_update_unix": 1791331200,
 *      "rates": {"USD": 1, "EUR": 0.86, "CLP": 953.1, ...}}
 *
 * Cada tasa es cuantas unidades de esa moneda valen 1 USD (1 USD = 953,1 CLP):
 * ActualizadorDeTasas la invierte para guardar cuanto vale 1 unidad en USD.
 *
 * Una tasa con un valor que no sirve (cero, negativo, texto, infinito) no tira la
 * respuesta entera: queda aparte en $rechazadas con su motivo, y las demas se
 * usan. Lo que si la tira es lo que la vuelve de fiar: que no sea JSON, que la
 * fuente avise un error, que la base no sea USD, o que USD no valga 1.
 */
final class RespuestaDeTasas
{
    /**
     * @param array<string, string> $unidadesPorUsd codigo => cuantas unidades de esa moneda valen 1 USD, en texto decimal
     * @param array<string, string> $rechazadas codigo => por que no se uso
     */
    private function __construct(
        public readonly array $unidadesPorUsd,
        public readonly array $rechazadas,
        public readonly ?DateTimeImmutable $fecha,
    ) {
    }

    /** @throws TasasInvalidas */
    public static function desdeJson(string $json): self
    {
        try {
            $datos = json_decode($json, true, 16, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new TasasInvalidas('La respuesta no es un JSON válido: ' . $e->getMessage());
        }
        if (!is_array($datos) || array_is_list($datos)) {
            throw new TasasInvalidas('La respuesta no es un objeto JSON.');
        }

        // ExchangeRate-API contesta 200 con {"result": "error", "error-type": "..."} cuando algo falla.
        if (($datos['result'] ?? 'success') !== 'success') {
            $motivo = is_string($datos['error-type'] ?? null) ? $datos['error-type'] : 'sin detalle';
            throw new TasasInvalidas("La fuente informa un error: {$motivo}.");
        }

        $base = $datos['base_code'] ?? $datos['base'] ?? null;
        if (!is_string($base)) {
            throw new TasasInvalidas('La respuesta no dice respecto de qué moneda están las tasas (base).');
        }
        if (strtoupper($base) !== 'USD') {
            throw new TasasInvalidas("La fuente da las tasas respecto de {$base}, y hacen falta respecto de USD.");
        }

        $rates = $datos['rates'] ?? null;
        if (!is_array($rates) || $rates === []) {
            throw new TasasInvalidas('La respuesta no trae tasas (rates).');
        }

        $unidades = [];
        $rechazadas = [];
        foreach ($rates as $clave => $valor) {
            $codigo = strtoupper((string) $clave);
            if (preg_match('/^[A-Z]{3}$/', $codigo) !== 1) {
                $rechazadas[$codigo] = 'no es un código de moneda';
                continue;
            }
            $texto = self::comoDecimal($valor);
            if ($texto === null) {
                $rechazadas[$codigo] = 'no es un número';
            } elseif ((float) $texto <= 0.0) {
                $rechazadas[$codigo] = 'no es mayor que cero';
            } else {
                $unidades[$codigo] = $texto;
            }
        }

        // La unica tasa que no hay que adivinar: 1 USD son 1 USD. Si la fuente dice otra cosa, algo esta mal con toda la respuesta.
        if (isset($unidades['USD']) && abs((float) $unidades['USD'] - 1.0) > 1e-9) {
            throw new TasasInvalidas("La respuesta es inconsistente: 1 USD vale {$unidades['USD']} USD.");
        }

        return new self($unidades, $rechazadas, self::fechaDe($datos));
    }

    /** El numero como texto decimal exacto (para que Postgres lo lea como numeric), o null si no es un numero finito. */
    private static function comoDecimal(mixed $valor): ?string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            return is_finite($valor) ? (string) json_encode($valor) : null;
        }
        if (is_string($valor) && is_numeric($valor) && is_finite((float) $valor)) {
            return trim($valor);
        }

        return null;
    }

    /** De cuando son las tasas, si la fuente lo dice: un unix timestamp (time_last_update_unix, timestamp) o un dia (date). */
    private static function fechaDe(array $datos): ?DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        foreach (['time_last_update_unix', 'timestamp'] as $clave) {
            if (isset($datos[$clave]) && (is_int($datos[$clave]) || (is_string($datos[$clave]) && ctype_digit($datos[$clave]))) && (int) $datos[$clave] > 0) {
                return (new DateTimeImmutable('@' . (int) $datos[$clave]))->setTimezone($utc);
            }
        }
        if (isset($datos['date']) && is_string($datos['date'])) {
            $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['date'], $utc);
            if ($dia !== false && $dia->format('Y-m-d') === $datos['date']) {
                return $dia;
            }
        }

        return null;
    }
}
