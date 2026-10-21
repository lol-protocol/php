<?php

declare(strict_types=1);

namespace App\Repositories\POS;

/** A cart line that can't be sold anymore: its product was withdrawn, or someone bought the stock first. */
final class SinStock extends \RuntimeException
{
    public function __construct(public readonly string $nombre)
    {
        parent::__construct("Sin stock suficiente: {$nombre}");
    }
}
