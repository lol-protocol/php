<?php

declare(strict_types=1);

namespace App\Respaldo;

use RuntimeException;

/** El respaldo no se pudo hacer o no se pudo comprobar. Nunca deja un archivo a medias ni borra respaldos anteriores. */
final class RespaldoFallido extends RuntimeException
{
}
