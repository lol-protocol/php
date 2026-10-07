<?php

declare(strict_types=1);

namespace App;

use DateInterval;
use DateTimeImmutable;

/**
 * La empresa no atiende a menores de edad: un cliente tiene que haber cumplido
 * EDAD anios. (Tampoco a personas privadas de libertad o interdictas, pero eso
 * no es un dato que la app tenga y no se puede validar.) La regla vive en tres
 * lugares que tienen que coincidir en el borde -quien cumple 18 hoy ya es mayor,
 * quien los cumple manana todavia no-: esta clase, que valida el alta; el trigger
 * de la migracion 007, que la exige en la base para cualquier otro camino; y el
 * primer tramo de adultos de RangoEdad. MayoriaDeEdadTest compara los tres.
 *
 * La edad cuenta anios cumplidos, como age() de Postgres: quien nacio un 29 de
 * febrero cumple en un anio comun el 1 de marzo.
 */
final class MayoriaDeEdad
{
    public const EDAD = 18;

    /**
     * Si alguien nacido en $fechaNacimiento (Y-m-d) ya cumplio la mayoria de edad
     * en $hoy. Una fecha que no existe (2026-02-30), o posterior a hoy, no es de
     * nadie: da false.
     */
    public static function cumplida(string $fechaNacimiento, DateTimeImmutable $hoy): bool
    {
        $nacimiento = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento);
        $hoy = $hoy->setTime(0, 0);
        if ($nacimiento === false || $nacimiento->format('Y-m-d') !== $fechaNacimiento || $nacimiento > $hoy) {
            return false;
        }

        return $nacimiento->diff($hoy)->y >= self::EDAD;
    }

    /**
     * La fecha de nacimiento mas reciente que todavia es de un mayor de edad en
     * $hoy: el tope del campo de fecha del formulario de alta. Sale de cumplida()
     * y no de restarle 18 anios a hoy, porque un 29 de febrero esa resta cae en
     * el 1 de marzo (2028-02-29 menos 18 anios da 2010-03-01) y dejaria elegir a
     * alguien que todavia tiene 17.
     */
    public static function nacimientoMasReciente(DateTimeImmutable $hoy): string
    {
        $candidata = $hoy->setTime(0, 0)->sub(new DateInterval('P' . self::EDAD . 'Y'));
        while (!self::cumplida($candidata->format('Y-m-d'), $hoy)) {
            $candidata = $candidata->modify('-1 day');
        }

        return $candidata->format('Y-m-d');
    }

    public static function mensaje(): string
    {
        return sprintf('Solo se admiten clientes mayores de edad (%d años cumplidos).', self::EDAD);
    }
}
