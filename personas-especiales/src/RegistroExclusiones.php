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

    /** Inserta una solicitud nueva o reemplaza la que tiene el mismo id. */
    public function guardar(Exclusion $exclusion): void;
}
