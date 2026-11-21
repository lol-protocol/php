<?php
declare(strict_types=1);

namespace ChatLibros;

/** Falló el modelo de lenguaje. El mensaje es apto para mostrárselo a la persona (no trae la clave). */
final class GeneradorError extends \RuntimeException
{
}
