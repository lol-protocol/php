<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * El tramo de edad de una persona, como expresion SQL. Lo usan la
 * segmentacion de clientes y el funnel, que antes tenian cada uno su copia
 * del mismo CASE.
 *
 * La edad se calcula con age(), que cuenta anios cumplidos. Las copias viejas
 * dividian los dias vividos por 365.25, y el dia del cumpleanios eso da menos
 * que el numero redondo (quien cumple 25 hoy tiene 9131 dias, que / 365.25 da
 * 24.9993): la persona quedaba en el tramo anterior.
 */
final class RangoEdad
{
    /** $columna es siempre una columna fija del codigo (ej. 'c.fecha_nacimiento'), nunca entrada de usuario. */
    public static function expresionSql(string $columna): string
    {
        $edad = "date_part('year', age({$columna}))";

        return "CASE
                WHEN {$edad} < 25 THEN '18-24'
                WHEN {$edad} < 35 THEN '25-34'
                WHEN {$edad} < 45 THEN '35-44'
                WHEN {$edad} < 55 THEN '45-54'
                WHEN {$edad} < 65 THEN '55-64'
                ELSE '65+'
             END";
    }
}
