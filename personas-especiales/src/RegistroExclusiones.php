<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DomainException;

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

    /** Una solicitud por su id, en cualquier estado; null si no existe. */
    public function porId(string $id): ?Exclusion;

    /**
     * Agrega una solicitud nueva. Nunca reemplaza una existente.
     *
     * @throws DomainException si ya hay una solicitud con ese id (también si dos llegan a la vez)
     */
    public function insertar(Exclusion $exclusion): void;

    /**
     * Guarda el resultado de una transición. Solo cambia lo que una transición puede cambiar
     * (estado, prueba y fecha del cambio): la persona, quién pide, el motivo y la fecha de alta no.
     * Y solo si la solicitud sigue en el estado que se leyó: si otro la cambió antes, falla en vez de
     * pisarle el cambio.
     *
     * @throws DomainException si la solicitud no existe o ya no está en $estadoEsperado
     */
    public function actualizar(Exclusion $exclusion, Estado $estadoEsperado): void;
}
