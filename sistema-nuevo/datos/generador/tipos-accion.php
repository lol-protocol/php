<?php

declare(strict_types=1);

/**
 * Catálogo de tipos de acción: etiqueta, duración base, y la ruta/archivo del
 * backend que "atendería" esa acción (se muestra en cada tarjeta del timeline).
 *
 * @return array{tiposAccion: array, tiposConMonto: string[], apiEndpoints: string[], httpStatusPool: int[]}
 */

$tiposAccion = [
    'login'           => ['label' => 'Inicio de sesión', 'base_ms' => 1500, 'ruta' => '/app/auth/iniciar-sesion.php'],
    'password_reset'  => ['label' => 'Restablecer contraseña', 'base_ms' => 6000, 'ruta' => '/app/auth/restablecer-clave.php'],
    'search'          => ['label' => 'Búsqueda', 'base_ms' => 4000, 'ruta' => '/app/catalogo/buscar.php'],
    'view_product'    => ['label' => 'Visualización de producto', 'base_ms' => 8000, 'ruta' => '/app/catalogo/producto.php'],
    'api_call'        => ['label' => 'Llamada a la API', 'base_ms' => 300, 'ruta' => '/app/api/index.php'],
    'profile_update'  => ['label' => 'Actualización de perfil', 'base_ms' => 9000, 'ruta' => '/app/perfil/actualizar.php'],
    'add_to_cart'     => ['label' => 'Agregar al carrito', 'base_ms' => 2000, 'ruta' => '/app/carrito/agregar.php'],
    'checkout_start'  => ['label' => 'Inicio de checkout', 'base_ms' => 5000, 'ruta' => '/app/checkout/iniciar.php'],
    'payment'         => ['label' => 'Pago', 'base_ms' => 12000, 'monto_base' => 45.0, 'ruta' => '/app/checkout/pago.php'],
    'refund'          => ['label' => 'Reembolso', 'base_ms' => 7000, 'monto_base' => 45.0, 'ruta' => '/app/pagos/reembolso.php'],
    'review_submit'   => ['label' => 'Reseña enviada', 'base_ms' => 15000, 'ruta' => '/app/resenas/enviar.php'],
    'support_ticket'  => ['label' => 'Ticket de soporte', 'base_ms' => 20000, 'ruta' => '/app/soporte/ticket.php'],
    'file_upload'     => ['label' => 'Subida de archivo', 'base_ms' => 4000, 'ruta' => '/app/soporte/adjuntos.php'],
    'logout'          => ['label' => 'Cierre de sesión', 'base_ms' => 800, 'ruta' => '/app/auth/cerrar-sesion.php'],
];

return [
    'tiposAccion' => $tiposAccion,
    'tiposConMonto' => ['payment', 'refund'],
    'apiEndpoints' => ['/api/pedidos', '/api/perfil', '/api/pagos', '/api/productos', '/api/carrito'],
    'httpStatusPool' => [200, 200, 200, 200, 201, 204, 400, 401, 404, 500],
];
