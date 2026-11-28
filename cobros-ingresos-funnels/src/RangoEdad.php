<?php

declare(strict_types=1);

namespace App;

/**
 * El tramo de edad de una persona, como expresion SQL. Lo usan la
 * segmentacion de clientes y el funnel, que antes tenian cada uno su copia
 * del mismo CASE.
 *
 * La edad se calcula con age(), que cuenta anios cumplidos. Las copias viejas
 * dividian los dias vividos por 365.25, y el dia del cumpleanios eso da menos
 * que el numero redondo (quien cumple 25 hoy tiene 9131 dias, que / 365.25 da
 * 24.9993): la persona quedaba en el tramo anterior.
 *
 * El primer tramo de adultos empieza en 18, asi que quien todavia no los
 * cumplio tiene su propio tramo, y una fecha de nacimiento posterior a hoy
 * (no es una edad) se separa como invalida: antes los dos caian en "18-24"
 * porque ese tramo no tenia cota inferior, y la segmentacion mostraba a
 * menores y a datos imposibles como adultos jovenes.
 */
final class RangoEdad
{
    /** Los tramos de un adulto, de menor a mayor: los unicos que puede tener un cliente (el CHECK de la migracion 021). */
    public const ADULTOS = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

    public const MENOR = 'Menor de 18';
    public const FECHA_INVALIDA = 'Fecha inválida';

    /**
     * El tramo de quien nacio en $fechaNacimiento (Y-m-d), visto en $hoy: lo mismo que expresionSql(), en PHP. Desde la
     * migracion 021 la base ya no guarda la fecha de nacimiento sino solo este tramo, asi que se calcula aca, una vez, al
     * cargar a la persona (ClienteRepository::crear); no se recalcula despues, y por eso es el tramo AL ALTA. Una fecha que
     * no existe o posterior a hoy da FECHA_INVALIDA.
     */
    public static function deFecha(string $fechaNacimiento, \DateTimeImmutable $hoy): string
    {
        $nacimiento = \DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento);
        $hoy = $hoy->setTime(0, 0);
        if ($nacimiento === false || $nacimiento->format('Y-m-d') !== $fechaNacimiento || $nacimiento > $hoy) {
            return self::FECHA_INVALIDA;
        }

        $edad = $nacimiento->diff($hoy)->y;

        return match (true) {
            $edad < 18 => self::MENOR,
            $edad < 25 => '18-24',
            $edad < 35 => '25-34',
            $edad < 45 => '35-44',
            $edad < 55 => '45-54',
            $edad < 65 => '55-64',
            default => '65+',
        };
    }

    /**
     * @deprecated Desde la migracion 021 (2026-11) no hay columna fecha_nacimiento: la segmentacion y el funnel agrupan por
     *             la columna rango_de_edad. Se conserva porque RangoEdadTest la compara con deFecha(), y como referencia.
     *
     * $columna es siempre una columna fija del codigo (ej. 'c.fecha_nacimiento'), nunca entrada de usuario. */
    public static function expresionSql(string $columna): string
    {
        $edad = "date_part('year', age({$columna}))";

        return "CASE
                WHEN {$columna} > CURRENT_DATE THEN 'Fecha inválida'
                WHEN {$edad} < 18 THEN 'Menor de 18'
                WHEN {$edad} < 25 THEN '18-24'
                WHEN {$edad} < 35 THEN '25-34'
                WHEN {$edad} < 45 THEN '35-44'
                WHEN {$edad} < 55 THEN '45-54'
                WHEN {$edad} < 65 THEN '55-64'
                ELSE '65+'
             END";
    }
}
