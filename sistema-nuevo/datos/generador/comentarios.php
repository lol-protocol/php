<?php

declare(strict_types=1);

/**
 * Pila de comentarios sintéticos (reseñas/tickets) + fragmentos inseguros que se
 * cuelan a propósito, para poder mostrar que el saneador los neutraliza.
 *
 * @return array{base: string[], inseguros: string[]}
 */

$base = [
    'Excelente atención, todo perfecto.',
    'El proceso fue más lento de lo esperado.',
    'Muy buena relación precio-calidad.',
    'Tuve problemas para completar el pago.',
    'Volvería a comprar sin dudarlo.',
    'El soporte tardó bastante en responder.',
    'Interfaz clara y fácil de usar.',
    'No era lo que esperaba, pero el reembolso fue rápido.',
    'Todo llegó a tiempo, sin inconvenientes.',
    'La aplicación se sintió lenta durante el pago.',
];

$inseguros = [
    '<script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    '&lt;script&gt;document.cookie&lt;/script&gt;',
    '<b onmouseover=alert(1)>saludos</b>',
];

return ['base' => $base, 'inseguros' => $inseguros];
