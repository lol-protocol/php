<?php

declare(strict_types=1);

/**
 * Cliente HTTP hacia el microservicio de estadísticas en Java (servicio-estadisticas-java).
 */
final class ClienteEstadisticas
{
    private const MAX_INTENTOS = 2;
    private const ESPERA_ENTRE_INTENTOS_MS = 150;

    /** Sin respuesta HTTP en el último intento (caído o colgado): corta las llamadas siguientes de esta instancia. */
    private bool $sinRespuesta = false;

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
        if ($this->sinRespuesta) {
            return null;
        }

        $url = $this->construirUrl($type, $countries, $ageMin, $ageMax, $gender, $excludeUserId);

        // Reintento corto (no otro timeout completo): pensado para el caso real
        // de que el servicio Java esté a mitad de recargar el CSV en caliente
        // (CargadorAcciones.iniciarWatcher, cada 5s) y momentáneamente no acepte
        // conexiones -- no para esperar a un servicio que está caído de verdad.
        for ($intento = 1; $intento <= self::MAX_INTENTOS; $intento++) {
            $body = $this->pedir($url);
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

    /**
     * Como stats(), pero para varios tipos a la vez: los pide todos en paralelo
     * (curl_multi) en vez de uno por uno. /api/timeline llama esto una sola vez
     * con los tipos distintos de la página -- así, con el servicio colgado, el
     * límite es un timeout total para toda la página (~3s) en vez de uno por
     * cada tipo distinto (ver README, "Notas / alcance").
     *
     * @param string[] $types
     * @return array<string, array|null> Cohort por tipo (mismo formato que stats()), o null si no se pudo obtener.
     */
    public function statsVarios(
        array $types,
        ?array $countries,
        int $ageMin,
        int $ageMax,
        string $gender,
        ?string $excludeUserId
    ): array {
        if ($types === []) {
            return [];
        }
        if ($this->sinRespuesta) {
            return array_fill_keys($types, null);
        }

        $urls = [];
        foreach ($types as $type) {
            $urls[$type] = $this->construirUrl($type, $countries, $ageMin, $ageMax, $gender, $excludeUserId);
        }

        [$cuerpos, $huboRespuesta] = self::pedirVarios($urls);

        $fallidos = array_keys(array_filter($cuerpos, fn ($body) => $body === null));
        if ($fallidos !== [] && $huboRespuesta) {
            // Al menos un tipo respondió (el servicio no está caído del todo):
            // vale la pena reintentar los que fallaron -- mismo espíritu que
            // el reintento corto de stats(), pero un solo reintento para todo
            // el lote en vez de uno por tipo.
            usleep(self::ESPERA_ENTRE_INTENTOS_MS * 1000);
            [$reintento, $huboRespuestaReintento] = self::pedirVarios(array_intersect_key($urls, array_flip($fallidos)));
            foreach ($reintento as $type => $body) {
                if ($body !== null) {
                    $cuerpos[$type] = $body;
                }
            }
            $huboRespuesta = $huboRespuesta || $huboRespuestaReintento;
        }

        $this->sinRespuesta = !$huboRespuesta;

        return array_map(static function (?string $body) {
            if ($body === null) {
                return null;
            }
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : null;
        }, $cuerpos);
    }

    private function construirUrl(
        string $type,
        ?array $countries,
        int $ageMin,
        int $ageMax,
        string $gender,
        ?string $excludeUserId
    ): string {
        $query = ['type' => $type, 'age_min' => $ageMin, 'age_max' => $ageMax, 'gender' => $gender];
        if ($countries !== null) {
            $query['countries'] = implode(',', $countries);
        }
        if ($excludeUserId !== null) {
            $query['exclude'] = $excludeUserId;
        }
        return $this->baseUrl . '/stats?' . http_build_query($query);
    }

    private function pedir(string $url): ?string
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

        $this->sinRespuesta = $body === false;
        return ($body === false || $httpCode !== 200) ? null : $body;
    }

    /**
     * @param array<string,string> $urlsPorTipo
     * @return array{0: array<string,?string>, 1: bool} Cuerpo por tipo (o null si falló) y si algún
     *         tipo tuvo respuesta HTTP real (aunque no haya sido 200) -- eso es lo que distingue
     *         "el servicio está caído/colgado del todo" de "este tipo puntual falló".
     */
    private static function pedirVarios(array $urlsPorTipo): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urlsPorTipo as $type => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$type] = $ch;
        }

        $activos = null;
        do {
            $estado = curl_multi_exec($mh, $activos);
        } while ($estado === CURLM_CALL_MULTI_PERFORM);
        while ($activos && $estado === CURLM_OK) {
            if (curl_multi_select($mh) === -1) {
                usleep(10_000);
            }
            do {
                $estado = curl_multi_exec($mh, $activos);
            } while ($estado === CURLM_CALL_MULTI_PERFORM);
        }

        $cuerpos = [];
        $huboRespuesta = false;
        foreach ($handles as $type => $ch) {
            $body = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 0) {
                $huboRespuesta = true;
            }
            $cuerpos[$type] = $httpCode === 200 ? $body : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return [$cuerpos, $huboRespuesta];
    }
}
