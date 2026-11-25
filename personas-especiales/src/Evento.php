<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un hecho de la auditoría: quién (un identificador opaco, no un nombre), qué y cuándo.
 * No lleva la razón ni la prueba: eso está en la solicitud. Así el historial no acumula datos personales.
 */
final class Evento
{
    public const ACTOR_MAX = 64;

    public readonly string $exclusionId;
    public readonly string $actor;

    public function __construct(
        string $exclusionId,
        public readonly TipoEvento $tipo,
        string $actor,
        public readonly DateTimeImmutable $ocurridoEn,
    ) {
        $this->exclusionId = self::texto($exclusionId, 'exclusión');
        $this->actor = self::texto($actor, 'actor');
    }

    private static function texto(string $valor, string $campo): string
    {
        $limpio = trim($valor);
        if ($limpio === '' || mb_strlen($limpio) > self::ACTOR_MAX) {
            throw new InvalidArgumentException("El campo {$campo} debe tener entre 1 y " . self::ACTOR_MAX . ' caracteres.');
        }

        return $limpio;
    }
}
