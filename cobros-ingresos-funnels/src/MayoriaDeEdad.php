<?php

declare(strict_types=1);

namespace App;

use DateInterval;
use DateTimeImmutable;

/**
 * La empresa no atiende a menores de edad: un cliente tiene que haber cumplido la
 * edad de mayoria de SU pais (paises.mayoria_de_edad: 18 casi en todos, 19 en
 * Canada, 20 en Tailandia, 21 en Singapur...). Tampoco atiende a personas privadas
 * de libertad o interdictas, pero eso no es un dato que la app tenga y no se puede
 * validar. La regla vive en tres lugares que tienen que coincidir en el borde
 * -quien cumple la edad hoy ya es mayor, quien la cumple manana todavia no-: esta
 * clase, que valida el alta; el trigger de las migraciones 007 y 010, que la exige
 * en la base para cualquier otro camino; y el primer tramo de adultos de RangoEdad
 * (solo para los 18). ClientesMayoresDeEdadTest compara los tres.
 *
 * La edad cuenta anios cumplidos, como age() de Postgres: quien nacio un 29 de
 * febrero cumple en un anio comun el 1 de marzo.
 */
final class MayoriaDeEdad
{
    /** La edad de los paises que no tienen otra; es el DEFAULT de la columna paises.mayoria_de_edad. */
    public const POR_DEFECTO = 18;

    /**
     * Si alguien nacido en $fechaNacimiento (Y-m-d) ya cumplio $edad anios en
     * $hoy. Una fecha que no existe (2026-02-30), o posterior a hoy, no es de
     * nadie: da false.
     */
    public static function cumplida(string $fechaNacimiento, DateTimeImmutable $hoy, int $edad = self::POR_DEFECTO): bool
    {
        $nacimiento = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento);
        $hoy = $hoy->setTime(0, 0);
        if ($nacimiento === false || $nacimiento->format('Y-m-d') !== $fechaNacimiento || $nacimiento > $hoy) {
            return false;
        }

        return $nacimiento->diff($hoy)->y >= $edad;
    }

    /**
     * La fecha de nacimiento mas reciente que todavia es de alguien que cumplio
     * $edad anios en $hoy: el tope del campo de fecha del formulario de alta. Sale
     * de cumplida() y no de restarle $edad anios a hoy, porque un 29 de febrero
     * esa resta cae en el 1 de marzo (2028-02-29 menos 18 anios da 2010-03-01) y
     * dejaria elegir a alguien que todavia tiene 17.
     */
    public static function nacimientoMasReciente(DateTimeImmutable $hoy, int $edad = self::POR_DEFECTO): string
    {
        $candidata = $hoy->setTime(0, 0)->sub(new DateInterval('P' . $edad . 'Y'));
        while (!self::cumplida($candidata->format('Y-m-d'), $hoy, $edad)) {
            $candidata = $candidata->modify('-1 day');
        }

        return $candidata->format('Y-m-d');
    }

    /**
     * Lo que se le dice a quien intenta dar de alta a un menor. Cuando la edad es
     * la general no nombra el pais; cuando es otra si, porque quien carga un
     * cliente de 19 anios en Tailandia no sabe por que se le rechaza.
     */
    public static function mensaje(int $edad = self::POR_DEFECTO, ?string $pais = null): string
    {
        if ($edad === self::POR_DEFECTO || $pais === null) {
            return sprintf('Solo se admiten clientes mayores de edad (%d años cumplidos).', $edad);
        }

        return sprintf('Solo se admiten clientes mayores de edad (en %s, %d años cumplidos).', $pais, $edad);
    }

    /**
     * Para cuando no se sabe de que pais era el cliente: un rechazo de la base que
     * llego hasta el catch del controller, sin pasar por la validacion.
     */
    public static function mensajeGeneral(): string
    {
        return 'Solo se admiten clientes mayores de edad: la edad que se pide depende del país.';
    }
}
