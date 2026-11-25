<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DomainException;

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

    public function insertar(Exclusion $exclusion): void
    {
        if (isset($this->porId[$exclusion->id()])) {
            throw new DomainException('Ya existe una solicitud con ese id.');
        }
        $this->porId[$exclusion->id()] = $exclusion;
    }

    public function actualizar(Exclusion $exclusion, Estado $estadoEsperado): void
    {
        $actual = $this->porId[$exclusion->id()] ?? null;
        if ($actual === null || $actual->estado() !== $estadoEsperado) {
            throw new DomainException('La solicitud no existe o cambió de estado mientras se trabajaba en ella.');
        }
        // Solo cambia lo que una transición puede cambiar: se conserva lo demás de la solicitud guardada.
        $this->porId[$exclusion->id()] = Exclusion::desde(
            $actual->id(),
            $actual->personaId(),
            $actual->solicitante()->value,
            $actual->motivo()->value,
            $exclusion->prueba(),
            $exclusion->estado()->value,
            $actual->creadaEn(),
            $exclusion->cambiadaEn(),
        );
    }
}
