<?php

declare(strict_types=1);

namespace App;

/**
 * Se intento dar de alta a alguien que no cumplio la edad de mayoria de su pais. Desde la migracion 021 la base no guarda la
 * fecha de nacimiento y no puede comprobarlo: lo comprueba ClienteRepository::crear, con la fecha exacta, antes de guardar.
 * El mensaje ya dice la edad y el pais (MayoriaDeEdad::mensaje).
 */
final class ClienteMenorDeEdad extends \RuntimeException
{
}
