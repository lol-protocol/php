<?php

declare(strict_types=1);

require_once __DIR__ . '/ayudantes.php';

/**
 * Genera el padrón de usuarios sintéticos (esto no es un "log", sale limpio).
 *
 * @param string[] $paises Códigos de país entre los que elegir
 * @param array{firstNames: string[], lastNames: string[], genders: string[], genderWeights: int[]} $nombres
 */
function generar_usuarios(int $cantidad, array $paises, array $nombres): array
{
    $users = [];
    for ($i = 1; $i <= $cantidad; $i++) {
        $users[] = [
            'id' => sprintf('u%03d', $i),
            'name' => $nombres['firstNames'][array_rand($nombres['firstNames'])] . ' ' . $nombres['lastNames'][array_rand($nombres['lastNames'])],
            'country' => $paises[array_rand($paises)],
            'age' => mt_rand(18, 65),
            'gender' => weightedPick($nombres['genders'], $nombres['genderWeights']),
        ];
    }
    return $users;
}
