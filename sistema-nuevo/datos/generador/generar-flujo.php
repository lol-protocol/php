<?php

declare(strict_types=1);

require_once __DIR__ . '/ayudantes.php';

/**
 * Arma la secuencia de tipos de acción de una sesión (login -> navegar -> quizás
 * comprar -> quizás soporte -> logout), con probabilidades realistas.
 *
 * @return string[]
 */
function generar_flujo_sesion(): array
{
    $flow = [];
    if (tal_vez(6)) {
        $flow[] = 'password_reset';
    }
    $flow[] = 'login';

    $browsing = mt_rand(2, 5);
    for ($b = 0; $b < $browsing; $b++) {
        $flow[] = mt_rand(0, 1) ? 'view_product' : 'search';
        if (tal_vez(25)) {
            $flow[] = 'api_call';
        }
    }
    if (tal_vez(12)) {
        $flow[] = 'profile_update';
    }
    if (tal_vez(70)) {
        $flow[] = 'add_to_cart';
        if (tal_vez(60)) {
            $flow[] = 'checkout_start';
            $flow[] = 'payment';
            if (tal_vez(35)) {
                $flow[] = 'review_submit';
            }
            if (tal_vez(10)) {
                $flow[] = 'refund';
            }
        }
    }
    if (tal_vez(15)) {
        $flow[] = 'support_ticket';
        if (tal_vez(50)) {
            $flow[] = 'file_upload';
        }
    }
    $flow[] = 'logout';

    return $flow;
}
