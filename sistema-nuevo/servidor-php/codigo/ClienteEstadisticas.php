<?php

declare(strict_types=1);

/**
 * Cliente HTTP hacia el microservicio de estadísticas en Java (servicio-estadisticas-java).
 */
final class ClienteEstadisticas
{
    public function __construct(private readonly string $baseUrl = 'http://localhost:8081')
    {
    }

    /**
     * @param string[]|null $countries null = sin filtro de país (todos)
     * @return array{type:string,count:int,avg_duration_ms:float,avg_amount_usd:?float,currency:string}|null
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

        $ch = curl_init($this->baseUrl . '/stats?' . http_build_query($query));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
