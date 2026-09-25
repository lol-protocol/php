<?php

declare(strict_types=1);

/** Utilidades de azar compartidas por el resto del generador. */

function weightedPick(array $items, array $weights)
{
    $total = array_sum($weights);
    $r = mt_rand(1, $total);
    $acc = 0;
    foreach ($items as $i => $item) {
        $acc += $weights[$i];
        if ($r <= $acc) {
            return $item;
        }
    }
    return $items[array_key_last($items)];
}

function tal_vez(int $probabilidadPorc): bool
{
    return mt_rand(1, 100) <= $probabilidadPorc;
}
