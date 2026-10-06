<?php

declare(strict_types=1);

require_once __DIR__ . '/insertar-filas.php';

/** Carga las tablas núcleo (usuarios, administradores, acciones). */

function cargar_usuarios(PDO $pdo, array $users): void
{
    $filas = [];
    foreach ($users as $u) {
        $filas[] = [
            'id' => $u['id'], 'nombre' => $u['name'], 'pais_codigo' => $u['country'],
            'edad' => $u['age'], 'genero' => $u['gender'],
        ];
    }
    insertar_filas($pdo, 'usuarios', $filas);
}

function cargar_administrador(PDO $pdo): void
{
    $credenciales = require __DIR__ . '/../../servidor-php/codigo/credenciales.php';
    insertar_filas(
        $pdo,
        'administradores',
        [['usuario' => $credenciales['username'], 'clave_hash' => $credenciales['password_hash']]],
        'ON CONFLICT (usuario) DO NOTHING'
    );
}

function cargar_acciones(PDO $pdo, array $acciones): void
{
    $filas = [];
    foreach ($acciones as $a) {
        $filas[] = [
            'id' => $a['id'], 'usuario_id' => $a['user_id'], 'tipo_clave' => $a['type'],
            'marca_temporal' => $a['timestamp'], 'duracion_ms' => $a['duration_ms'], 'ruta' => $a['path'],
            'ip' => $a['ip'], 'ip_pais_codigo' => $a['ip_country'], 'ip_hora_local' => $a['ip_local_time'],
            'ip_proveedor' => $a['ip_isp'], 'monto_local' => $a['amount_local'], 'moneda_codigo' => $a['currency'],
            'monto_usd' => $a['amount_usd'], 'comentario' => $a['comment'], 'endpoint' => $a['endpoint'],
            'codigo_http' => $a['http_status'], 'tamano_archivo_kb' => $a['file_size_kb'],
        ];
    }
    insertar_filas($pdo, 'acciones', $filas);
}
