<?php

declare(strict_types=1);

/** Carga las tablas núcleo (usuarios, administradores, acciones). */

function cargar_usuarios(PDO $pdo, array $users): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (id, nombre, pais_codigo, edad, genero) VALUES (:id, :nombre, :pais, :edad, :genero)'
    );
    foreach ($users as $u) {
        $stmt->execute([
            'id' => $u['id'], 'nombre' => $u['name'], 'pais' => $u['country'],
            'edad' => $u['age'], 'genero' => $u['gender'],
        ]);
    }
}

function cargar_administrador(PDO $pdo): void
{
    $credenciales = require __DIR__ . '/../../servidor-php/codigo/credenciales.php';
    $stmt = $pdo->prepare(
        'INSERT INTO administradores (usuario, clave_hash) VALUES (:usuario, :hash)
         ON CONFLICT (usuario) DO NOTHING'
    );
    $stmt->execute(['usuario' => $credenciales['username'], 'hash' => $credenciales['password_hash']]);
}

function cargar_acciones(PDO $pdo, array $acciones): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO acciones (
            id, usuario_id, tipo_clave, marca_temporal, duracion_ms, ruta,
            ip, ip_pais_codigo, ip_hora_local, ip_proveedor,
            monto_local, moneda_codigo, monto_usd, comentario, endpoint, codigo_http, tamano_archivo_kb
        ) VALUES (
            :id, :usuario_id, :tipo, :marca_temporal, :duracion_ms, :ruta,
            :ip, :ip_pais, :ip_hora, :ip_proveedor,
            :monto_local, :moneda, :monto_usd, :comentario, :endpoint, :codigo_http, :tamano
        )'
    );
    foreach ($acciones as $a) {
        $stmt->execute([
            'id' => $a['id'], 'usuario_id' => $a['user_id'], 'tipo' => $a['type'],
            'marca_temporal' => $a['timestamp'], 'duracion_ms' => $a['duration_ms'], 'ruta' => $a['path'],
            'ip' => $a['ip'], 'ip_pais' => $a['ip_country'], 'ip_hora' => $a['ip_local_time'],
            'ip_proveedor' => $a['ip_isp'], 'monto_local' => $a['amount_local'], 'moneda' => $a['currency'],
            'monto_usd' => $a['amount_usd'], 'comentario' => $a['comment'], 'endpoint' => $a['endpoint'],
            'codigo_http' => $a['http_status'], 'tamano' => $a['file_size_kb'],
        ]);
    }
}
