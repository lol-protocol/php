<?php

declare(strict_types=1);

/**
 * Catálogo de tipos de acción y los datos de referencia para "api_call".
 *
 * @return array{tiposAccion: array, tiposConMonto: string[], apiEndpoints: string[], httpStatusPool: int[]}
 */

$tiposAccion = [
    'login'           => ['label' => 'Inicio de sesión', 'base_ms' => 1500],
    'password_reset'  => ['label' => 'Restablecer contraseña', 'base_ms' => 6000],
    'search'          => ['label' => 'Búsqueda', 'base_ms' => 4000],
    'view_product'    => ['label' => 'Visualización de producto', 'base_ms' => 8000],
    'api_call'        => ['label' => 'Llamada a la API', 'base_ms' => 300],
    'profile_update'  => ['label' => 'Actualización de perfil', 'base_ms' => 9000],
    'add_to_cart'     => ['label' => 'Agregar al carrito', 'base_ms' => 2000],
    'checkout_start'  => ['label' => 'Inicio de checkout', 'base_ms' => 5000],
    'payment'         => ['label' => 'Pago', 'base_ms' => 12000, 'monto_base' => 45.0],
    'refund'          => ['label' => 'Reembolso', 'base_ms' => 7000, 'monto_base' => 45.0],
    'review_submit'   => ['label' => 'Reseña enviada', 'base_ms' => 15000],
    'support_ticket'  => ['label' => 'Ticket de soporte', 'base_ms' => 20000],
    'file_upload'     => ['label' => 'Subida de archivo', 'base_ms' => 4000],
    'logout'          => ['label' => 'Cierre de sesión', 'base_ms' => 800],
];

return [
    'tiposAccion' => $tiposAccion,
    'tiposConMonto' => ['payment', 'refund'],
    'apiEndpoints' => ['/api/pedidos', '/api/perfil', '/api/pagos', '/api/productos', '/api/carrito'],
    'httpStatusPool' => [200, 200, 200, 200, 201, 204, 400, 401, 404, 500],
];
