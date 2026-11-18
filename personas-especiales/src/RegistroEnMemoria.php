<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/** Registro sin base de datos, para pruebas y para usar la política sin PostgreSQL. */
final class RegistroEnMemoria implements RegistroExclusiones
{
    /** @var array<string, Exclusion> */
    private array $porId = [];

    public function activasDe(string $personaId): array
    {
        $activas = [];
        foreach ($this->porId as $exclusion) {
            if ($exclusion->personaId() === $personaId && $exclusion->estaActiva()) {
                $activas[] = $exclusion;
            }
        }

        return $activas;
    }

    public function guardar(Exclusion $exclusion): void
    {
        $this->porId[$exclusion->id()] = $exclusion;
    }
}
