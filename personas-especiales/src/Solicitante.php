<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/**
 * Quién pide la exclusión. La lista es corta a propósito: no hay categorías de
 * la persona (religión, origen, pertenencia a un grupo); la pide quien tiene
 * derecho a pedirla, y la plataforma no clasifica a nadie.
 */
enum Solicitante: string
{
    /** La propia persona viva. */
    case Persona = 'persona';

    /** Familiar directo de una persona fallecida. */
    case FamiliarFallecido = 'familiar_fallecido';

    /** Apoderado con prueba de representación. */
    case Apoderado = 'apoderado';
}
