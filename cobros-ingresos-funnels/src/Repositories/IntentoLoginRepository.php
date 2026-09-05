<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class IntentoLoginRepository
{
    private const MAX_INTENTOS = 5;
    private const BLOQUEO_MINUTOS = 15;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Minutos restantes de bloqueo, o null si no esta bloqueado. */
    public function minutosDeBloqueo(string $email): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT CEIL(EXTRACT(EPOCH FROM (bloqueado_hasta - now())) / 60) AS minutos
             FROM intentos_login WHERE email = :email AND bloqueado_hasta > now()"
        );
        $stmt->execute([':email' => $email]);
        $minutos = $stmt->fetchColumn();
        return $minutos !== false ? max(1, (int) $minutos) : null;
    }

    /** Suma un intento fallido; a partir del quinto seguido, bloquea 15 minutos. */
    public function registrarFallo(string $email): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO intentos_login (email, intentos, ultimo_intento, bloqueado_hasta)
             VALUES (:email, 1, now(), NULL)
             ON CONFLICT (email) DO UPDATE SET
                intentos = intentos_login.intentos + 1,
                ultimo_intento = now(),
                bloqueado_hasta = CASE
                    WHEN intentos_login.intentos + 1 >= :maximo
                    THEN now() + make_interval(mins => :bloqueo)
                    ELSE intentos_login.bloqueado_hasta
                END'
        );
        $stmt->execute([
            ':email' => $email,
            ':maximo' => self::MAX_INTENTOS,
            ':bloqueo' => self::BLOQUEO_MINUTOS,
        ]);
    }

    public function limpiar(string $email): void
    {
        $stmt = $this->db->prepare('DELETE FROM intentos_login WHERE email = :email');
        $stmt->execute([':email' => $email]);
    }
}
