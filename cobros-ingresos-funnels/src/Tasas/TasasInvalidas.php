<?php

declare(strict_types=1);

namespace App\Tasas;

use RuntimeException;

/**
 * Las tasas que llegaron (o que no llegaron) no sirven para actualizar la base: la
 * respuesta no se puede leer, esta incompleta, es vieja o no es respecto de USD. El
 * mensaje dice por que, y quien la lanza no escribio nada.
 */
final class TasasInvalidas extends RuntimeException
{
}
