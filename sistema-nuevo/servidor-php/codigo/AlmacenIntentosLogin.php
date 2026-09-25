<?php

declare(strict_types=1);

/** Rate limiting simple de /api/login por IP: N intentos fallidos → bloqueo temporal. */
final class AlmacenIntentosLogin
{
    private const MAX_INTENTOS = 5;
    private const BLOQUEO_MINUTOS = 15;
    private const RETENCION_HORAS = 24;
    private const PROBABILIDAD_PODA = 20; // 1 de cada 20 llamadas a registrarFallo poda filas viejas

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function bloqueadaHasta(string $ip): ?string
    {
        $stmt = $this->pdo->prepare('SELECT bloqueado_hasta FROM intentos_login WHERE ip = ? AND bloqueado_hasta > NOW()');
        $stmt->execute([$ip]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : $valor;
    }

    /**
     * @return ?string bloqueado_hasta si ESTE fallo llega después de que la
     *   IP ya había cruzado el umbral; null si todavía no (incluido el fallo
     *   que recién lo cruza -- ese termina de bloquear para la PRÓXIMA vez,
     *   pero sigue siendo "contraseña incorrecta" para sí mismo).
     *
     *   bloqueadaHasta() es una lectura aparte de este UPDATE, y bajo
     *   requests concurrentes puede quedar stale: varias pueden leerla en
     *   "todavía no bloqueada" antes de que cualquiera de sus propios fallos
     *   se escriba (verificado con 15 logins en paralelo). El contador nunca
     *   pierde un update -- Postgres serializa el UPSERT por fila -- pero esa
     *   lectura previa sí puede quedar vieja. El RETURNING de acá da el
     *   "intentos" ya serializado por Postgres, que refleja la posición real
     *   de ESTE fallo entre todos los concurrentes (no el orden en que
     *   llegaron las requests): por encima de MAX_INTENTOS, el umbral ya
     *   estaba cruzado antes de este fallo puntual.
     */
    public function registrarFallo(string $ip): ?string
    {
        // No hay cron en este proyecto (todo corre con scripts simples), así
        // que la poda de filas viejas viaja "gratis" sobre la única operación
        // que hace crecer la tabla -- con probabilidad baja para no pagar un
        // DELETE de más en cada intento fallido de login.
        if (random_int(1, self::PROBABILIDAD_PODA) === 1) {
            $this->podarViejos();
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO intentos_login (ip, intentos, ultimo_intento, bloqueado_hasta)
             VALUES (:ip, 1, NOW(), NULL)
             ON CONFLICT (ip) DO UPDATE SET
                intentos = intentos_login.intentos + 1,
                ultimo_intento = NOW(),
                bloqueado_hasta = CASE
                    WHEN intentos_login.intentos + 1 >= :max THEN NOW() + (:min || ' minutes')::interval
                    ELSE intentos_login.bloqueado_hasta
                END
             RETURNING intentos, bloqueado_hasta"
        );
        $stmt->execute(['ip' => $ip, 'max' => self::MAX_INTENTOS, 'min' => self::BLOQUEO_MINUTOS]);
        $fila = $stmt->fetch();
        return ($fila['intentos'] > self::MAX_INTENTOS) ? $fila['bloqueado_hasta'] : null;
    }

    public function limpiar(string $ip): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM intentos_login WHERE ip = ?');
        $stmt->execute([$ip]);
    }

    /** Filas de IPs que no volvieron a fallar en self::RETENCION_HORAS: ya no aportan nada al rate limiting. */
    public function podarViejos(): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM intentos_login WHERE ultimo_intento < NOW() - (:horas || ' hours')::interval");
        $stmt->execute(['horas' => self::RETENCION_HORAS]);
    }
}
