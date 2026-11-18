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

    public function conExclusionActiva(array $personaIds): array
    {
        $buscadas = array_flip($personaIds);
        $excluidas = [];
        foreach ($this->porId as $exclusion) {
            $persona = $exclusion->personaId();
            if ($exclusion->estaActiva() && isset($buscadas[$persona])) {
                $excluidas[$persona] = $persona;
            }
        }

        return array_values($excluidas);
    }

    public function porId(string $id): ?Exclusion
    {
        return $this->porId[$id] ?? null;
    }

    public function guardar(Exclusion $exclusion): void
    {
        $this->porId[$exclusion->id()] = $exclusion;
    }
}
