<?php

namespace Marcalibros;

/**
 * Se lanza cuando algo que eligió o subió el usuario no se puede aceptar.
 * Es un error esperable, no un fallo del servidor: la interfaz debe mostrar
 * userMessage() y dejar que la persona lo intente de nuevo.
 */
final class BookmarkRejectedException extends \RuntimeException
{
    public function __construct(public readonly RejectionReason $reason, ?\Throwable $previous = null)
    {
        parent::__construct($reason->value, 0, $previous);
    }

    public function userMessage(): string
    {
        return $this->reason->userMessage();
    }
}
