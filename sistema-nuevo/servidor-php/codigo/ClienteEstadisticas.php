<?php

declare(strict_types=1);

/**
 * Cliente HTTP hacia el microservicio de estadísticas en Java (servicio-estadisticas-java).
 */
final class ClienteEstadisticas
{
    private const MAX_INTENTOS = 2;
    private const ESPERA_ENTRE_INTENTOS_MS = 150;

    public function __construct(private readonly string $baseUrl = 'http://localhost:8081')
    {
    }

    /**
     * @param string[]|null $countries null = sin filtro de país (todos)
     * @return array{type:string,count:int,avg_duration_ms:float,median_duration_ms:?float,
     *         p90_duration_ms:?float,avg_amount_usd:?float,median_amount_usd:?float,
     *         p90_amount_usd:?float,currency:string}|null
     *         null si el servicio de estadísticas no respondió (p. ej. no está corriendo).
     */
    public function stats(
        string $type,
        ?array $countries,
        int $ageMin,
        int $ageMax,
        string $gender,
        ?string $excludeUserId
    ): ?array {
        $query = ['type' => $type, 'age_min' => $ageMin, 'age_max' => $ageMax, 'gender' => $gender];
        if ($countries !== null) {
            $query['countries'] = implode(',', $countries);
        }
        if ($excludeUserId !== null) {
            $query['exclude'] = $excludeUserId;
        }

        $url = $this->baseUrl . '/stats?' . http_build_query($query);

        // Reintento corto (no otro timeout completo): pensado para el caso real
        // de que el servicio Java esté a mitad de recargar el CSV en caliente
        // (CargadorAcciones.iniciarWatcher, cada 5s) y momentáneamente no acepte
        // conexiones -- no para esperar a un servicio que está caído de verdad.
        for ($intento = 1; $intento <= self::MAX_INTENTOS; $intento++) {
            $body = self::pedir($url);
            if ($body !== null) {
                $decoded = json_decode($body, true);
                return is_array($decoded) ? $decoded : null;
            }
            if ($intento < self::MAX_INTENTOS) {
                usleep(self::ESPERA_ENTRE_INTENTOS_MS * 1000);
            }
        }

        return null;
    }

    private static function pedir(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($body === false || $httpCode !== 200) ? null : $body;
    }
}
