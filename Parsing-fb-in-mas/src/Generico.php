<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Para cualquier sitio que declare una `Person` de schema.org en JSON-LD (un blog,
 * una página personal, un perfil de otra red). Es el punto de partida para agregar
 * una red nueva: ver «Cómo agregar una red» en el README.
 */
final class Generico extends Analizador
{
    public function red(): string
    {
        return 'generico';
    }

    public function dominios(): array
    {
        return [];
    }

    public function reconoce(array $d): bool
    {
        return $d['persona'] !== null;
    }

    protected function extraer(array $d, ?string $url): array
    {
        return ['usuario' => null] + self::camposDePersona($d['persona']);
    }
}
