<?php

declare(strict_types=1);

/**
 * Lista los pagos con fecha posterior a hoy que ya estan en la base.
 *
 * Desde que el alta y la edicion rechazan una fecha de pago futura (ver
 * Validacion::fechaNoEsFutura()), no se pueden crear mas; pero una base que ya tenia
 * datos puede traer alguno, y los reportes lo cuentan en un mes que todavia no termino.
 * Esto solo los lista: corregirlos (editar la fecha del pago, o anularlo) lo decide una persona.
 *
 * Uso:
 *   php bin/pagos_futuros.php
 *
 * Termina con 0 si no hay ninguno y con 1 si hay alguno o no se pudo leer la base.
 * Usa las mismas variables DB_* que la app y no escribe nada.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

try {
    // conectar() fija la zona horaria del negocio: "hoy" es el mismo que ve la app.
    $pdo = Database::conectar();
    $hoy = date('Y-m-d');
    $consulta = $pdo->prepare(
        'SELECT p.id, p.fecha_pago, p.monto, p.moneda_codigo, p.cliente_id, p.boleta_id, p.anulada
         FROM pagos p WHERE p.fecha_pago > :hoy ORDER BY p.fecha_pago, p.id'
    );
    $consulta->execute([':hoy' => $hoy]);
    $pagos = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($pagos === []) {
    echo "No hay pagos con fecha posterior a {$hoy}.\n";
    exit(0);
}

echo count($pagos) . " pago(s) con fecha posterior a {$hoy}:\n";
foreach ($pagos as $p) {
    printf(
        "  pago %d: %s, %s %s, cliente %d%s%s\n",
        $p['id'],
        $p['fecha_pago'],
        $p['monto'],
        $p['moneda_codigo'],
        $p['cliente_id'],
        $p['boleta_id'] !== null ? ", boleta {$p['boleta_id']}" : ', sin boleta',
        $p['anulada'] ? ' (anulado)' : ''
    );
}
echo "Corregí la fecha de cada uno desde la pantalla del pago, o anulalo.\n";
exit(1);
