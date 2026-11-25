<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/**
 * Estado de una solicitud de exclusión.
 *
 * pendiente  -> activa (verificada) | rechazada
 * activa     -> revocada (la persona o su familia pide volver a aparecer)
 * rechazada y revocada son finales.
 */
enum Estado: string
{
    case Pendiente = 'pendiente';
    case Activa = 'activa';
    case Rechazada = 'rechazada';
    case Revocada = 'revocada';
}
