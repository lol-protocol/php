<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/** Qué le pasó a una solicitud. Uno por transición de `Exclusion`. */
enum TipoEvento: string
{
    case Solicitada = 'solicitada';
    case Verificada = 'verificada';
    case Rechazada = 'rechazada';
    case Revocada = 'revocada';
}
