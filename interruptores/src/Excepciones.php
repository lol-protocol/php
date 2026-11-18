<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

/** El id pedido no existe en catalogo.json. */
final class UnknownInterruptor extends \InvalidArgumentException
{
}

/** El cambio dejaría una función encendida sin lo que necesita. */
final class DependenciaRota extends \RuntimeException
{
}
