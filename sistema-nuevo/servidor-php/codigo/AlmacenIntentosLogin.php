<?php

declare(strict_types=1);

/** Rate limiting simple de /api/login por IP: N intentos fallidos → bloqueo temporal. */
final class AlmacenIntentosLogin
{
    private const MAX_INTENTOS = 5;
    private const BLOQUEO_MINUTOS = 15;

    public static function bloqueadaHasta(PDO $pdo, string $ip): ?string
    {
        $stmt = $pdo->prepare('SELECT bloqueado_hasta FROM intentos_login WHERE ip = ? AND bloqueado_hasta > NOW()');
        $stmt->execute([$ip]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : $valor;
    }

    public static function registrarFallo(PDO $pdo, string $ip): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO intentos_login (ip, intentos, ultimo_intento, bloqueado_hasta)
             VALUES (:ip, 1, NOW(), NULL)
             ON CONFLICT (ip) DO UPDATE SET
                intentos = intentos_login.intentos + 1,
                ultimo_intento = NOW(),
                bloqueado_hasta = CASE
                    WHEN intentos_login.intentos + 1 >= :max THEN NOW() + (:min || ' minutes')::interval
                    ELSE intentos_login.bloqueado_hasta
                END"
        );
        $stmt->execute(['ip' => $ip, 'max' => self::MAX_INTENTOS, 'min' => self::BLOQUEO_MINUTOS]);
    }

    public static function limpiar(PDO $pdo, string $ip): void
    {
        $stmt = $pdo->prepare('DELETE FROM intentos_login WHERE ip = ?');
        $stmt->execute([$ip]);
    }
}
