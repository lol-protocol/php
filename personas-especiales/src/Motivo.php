<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/**
 * Por qué se pide la exclusión. Una lista cerrada y sin texto libre, a propósito: un campo abierto
 * invita a escribir justo lo que el módulo no debe guardar («es judío», «hijo de tal político»).
 *
 * Los motivos los elige quien pide; la plataforma no clasifica a nadie. Ninguno nombra una religión,
 * un origen ni una organización.
 */
enum Motivo: string
{
    /** Quiere que su historia familiar no se conecte ni se muestre, sin dar más razones. */
    case PrivacidadPersonal = 'privacidad_personal';

    /** Teme por su seguridad o la de su familia. */
    case Seguridad = 'seguridad';

    /** No quiere que lo asocien con un familiar (por su cargo, su fama o sus actos). */
    case AsociacionConFamiliar = 'asociacion_con_familiar';

    /** Otra razón; no se pide ni se guarda cuál. */
    case Otro = 'otro';
}
