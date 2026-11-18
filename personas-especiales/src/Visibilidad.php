<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/**
 * La política: una persona con una exclusión activa no se conecta, no se muestra
 * y no sale en una exportación.
 *
 * Quien llama debe responder igual cuando la persona no existe y cuando está
 * excluida. Este objeto solo dice sí o no; no dice por qué, para que la respuesta
 * no revele que existe un registro.
 *
 * Alcance de esta fase: solo la persona excluida. Sus parientes siguen visibles;
 * qué hacer con los vínculos de una persona excluida queda para decidirlo aparte.
 */
final class Visibilidad
{
    public function __construct(private RegistroExclusiones $registro)
    {
    }

    /** Sin exclusión activa, la persona es visible. Una revocada vuelve a serlo. */
    public function esVisible(string $personaId): bool
    {
        return $this->registro->activasDe($personaId) === [];
    }

    /** Dos personas solo se conectan si las dos son visibles. */
    public function puedeConectar(string $personaA, string $personaB): bool
    {
        return $this->esVisible($personaA) && $this->esVisible($personaB);
    }

    /** Una exportación (GEDCOM u otra) no incluye a la persona excluida. */
    public function puedeExportar(string $personaId): bool
    {
        return $this->esVisible($personaId);
    }

    /**
     * Quita de una lista de personas a las excluidas, sin cambiar el orden.
     *
     * @param list<string> $personaIds
     * @return list<string>
     */
    public function filtrar(array $personaIds): array
    {
        $visibles = [];
        foreach ($personaIds as $personaId) {
            if ($this->esVisible($personaId)) {
                $visibles[] = $personaId;
            }
        }

        return $visibles;
    }
}
