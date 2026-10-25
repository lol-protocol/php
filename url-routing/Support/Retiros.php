<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\Genealogy\RetiroRepository;

/**
 * A page withdrawn after a pedido de retiro (see RetiroRepository): 410 to anyone but the owner,
 * on the resource's page and on every one of its actions. The owner still sees it, to review it.
 * The Router asks here before calling the controller, on the sites whose route config sets
 * `retiros`.
 */
final class Retiros
{
    /** The response that withholds $tipo $id, or null when it may be shown. */
    public static function bloqueo(?string $tipo, int $id): ?string
    {
        if ($tipo === null || !isset(RetiroRepository::TABLAS[$tipo]) || AccessPolicy::esPropietario()) {
            return null;
        }
        // Only what a visitor would see: a page that is a 404 for them anyway stays a 404 (see existe()).
        $repo = new RetiroRepository(ServiceLocator::getInstance()->getDatabase());
        if (!$repo->retirado($tipo, $id) || !$repo->existe($tipo, $id)) {
            return null;
        }

        http_response_code(410);
        return view('genealogy/retirado', []);
    }
}
