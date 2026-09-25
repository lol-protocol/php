<?php

declare(strict_types=1);

namespace App;

use DateTimeZone;
use RuntimeException;

final class Config
{
    public const MONEDA = '$';
    public const NOMBRE_SISTEMA = 'Panel de Cobros, Ingresos y Funnel';

    /**
     * Formatea un monto ya consolidado a USD (los agregados/KPIs siempre lo
     * estan). El signo va antes del simbolo ("-$58.00", no "$-58.00"): los
     * cobros netos pueden dar negativo cuando hay devoluciones.
     */
    public static function money(float $amount): string
    {
        return ($amount < 0 ? '-' : '') . self::MONEDA . number_format(abs($amount), 2);
    }

    /**
     * true solo con APP_ENV=dev. Cualquier otro valor, o ninguno, es
     * produccion: la opcion segura es la que no hay que acordarse de activar.
     */
    public static function esDesarrollo(): bool
    {
        return getenv('APP_ENV') === 'dev';
    }

    /**
     * Las variables de entorno sin las cuales la app no deberia arrancar (las
     * credenciales de la base). En desarrollo, las que falten caen en su
     * valor de $valoresDesarrollo; en produccion, que falte alguna es un
     * error que las nombra a todas juntas, para no tener que descubrirlas de
     * a una.
     *
     * Antes todas las DB_* caian en silencio a valores de desarrollo, asi que
     * un error de tipeo (DB_PASS en vez de DB_PASSWORD) no fallaba: se
     * conectaba con la clave de desarrollo, o daba un error de autenticacion
     * que no decia que variable faltaba.
     *
     * @param array<string, string> $valoresDesarrollo nombre => valor por defecto en desarrollo
     * @return array<string, string> nombre => valor
     */
    public static function variablesObligatorias(array $valoresDesarrollo): array
    {
        $valores = [];
        $faltantes = [];
        foreach ($valoresDesarrollo as $nombre => $valorDesarrollo) {
            $valor = self::leer($nombre) ?? (self::esDesarrollo() ? $valorDesarrollo : null);
            if ($valor === null) {
                $faltantes[] = $nombre;
            } else {
                $valores[$nombre] = $valor;
            }
        }

        if ($faltantes !== []) {
            throw new RuntimeException(sprintf(
                'Faltan variables de entorno: %s. En produccion definilas donde corre PHP '
                . '(ej. env[DB_PASSWORD] = ... en el pool de PHP-FPM); en desarrollo, exporta '
                . 'APP_ENV=dev para usar los valores por defecto.',
                implode(', ', $faltantes)
            ));
        }

        return $valores;
    }

    /** Una variable con un valor por defecto que sirve en cualquier entorno (ej. el puerto estandar de Postgres). */
    public static function variable(string $nombre, string $porDefecto): string
    {
        return self::leer($nombre) ?? $porDefecto;
    }

    /**
     * Zona horaria del negocio: define que dia es "hoy" para vencimientos,
     * rangos de fechas y notas de credito. APP_TIMEZONE con un nombre IANA
     * (ej. America/Argentina/Buenos_Aires); UTC si no se define.
     *
     * Solo se aceptan nombres IANA y no offsets sueltos como "-03:00": en un
     * SET TIME ZONE, Postgres lee los offsets con la convencion POSIX, con el
     * signo al reves, y PHP y la base terminarian 6 horas desfasados.
     */
    public static function zonaHoraria(): string
    {
        $zona = self::variable('APP_TIMEZONE', 'UTC');
        if (!in_array($zona, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new RuntimeException(
                "APP_TIMEZONE={$zona} no es una zona horaria IANA valida (ej. America/Argentina/Buenos_Aires o UTC)."
            );
        }

        return $zona;
    }

    private static function leer(string $nombre): ?string
    {
        $valor = getenv($nombre);
        return $valor === false || $valor === '' ? null : $valor;
    }
}
