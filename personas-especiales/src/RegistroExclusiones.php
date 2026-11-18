<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/**
 * Dónde se guardan las solicitudes de exclusión. La implementación en PDO es la
 * de producción; la de memoria sirve para probar la política sin base de datos.
 */
interface RegistroExclusiones
{
    /**
     * Las exclusiones activas de una persona (vacío si no tiene ninguna).
     *
     * @return list<Exclusion>
     */
    public function activasDe(string $personaId): array;

    /**
     * Las personas de la lista que tienen al menos una exclusión activa, en una sola consulta
     * (una por persona no aguanta un árbol de miles).
     *
     * @param list<string> $personaIds
     * @return list<string>
     */
    public function conExclusionActiva(array $personaIds): array;

    /** Una solicitud por su id, en cualquier estado; null si no existe. */
    public function porId(string $id): ?Exclusion;

    /** Inserta una solicitud nueva o reemplaza la que tiene el mismo id. */
    public function guardar(Exclusion $exclusion): void;
}
