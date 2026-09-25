<?php

declare(strict_types=1);

require_once __DIR__ . '/ayudantes.php';

/** 30% de los nombres salen en su escritura nativa (nombresNativos), no romanizada. */
function generar_nombre(array $nombres, array $nombresNativos): string
{
    if (tal_vez(30)) {
        $escrituras = array_keys($nombresNativos);
        $pila = $nombresNativos[$escrituras[array_rand($escrituras)]];
        return $pila['first'][array_rand($pila['first'])] . ' ' . $pila['last'][array_rand($pila['last'])];
    }
    return $nombres['firstNames'][array_rand($nombres['firstNames'])] . ' ' . $nombres['lastNames'][array_rand($nombres['lastNames'])];
}

/**
 * Genera el padrón de usuarios sintéticos (esto no es un "log", sale limpio).
 *
 * @param string[] $paises Códigos de país entre los que elegir
 * @param array{firstNames: string[], lastNames: string[], genders: string[], genderWeights: int[]} $nombres
 * @param array<string, array{first: string[], last: string[]}> $nombresNativos
 */
function generar_usuarios(int $cantidad, array $paises, array $nombres, array $nombresNativos): array
{
    $users = [];
    for ($i = 1; $i <= $cantidad; $i++) {
        $users[] = [
            'id' => sprintf('u%03d', $i),
            'name' => generar_nombre($nombres, $nombresNativos),
            'country' => $paises[array_rand($paises)],
            'age' => mt_rand(18, 65),
            'gender' => weightedPick($nombres['genders'], $nombres['genderWeights']),
        ];
    }
    return $users;
}
