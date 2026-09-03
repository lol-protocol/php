<?php

declare(strict_types=1);

/**
 * Sanea la IP y sus datos derivados (país de la IP, proveedor, hora local) y
 * los escribe en $limpio. La hora local se deriva del huso horario del país de
 * la IP aplicado sobre el timestamp UTC ya saneado — no viene del crudo.
 */
function saneador_aplicar_ip(array &$limpio, array $crudo, string $timestampUtc, array $contexto): void
{
    $ip = saneador_ip($crudo['ip'] ?? null);
    if ($ip === null) {
        return;
    }
    $limpio['ip'] = $ip;
    $limpio['ip_isp'] = saneador_texto($crudo['ip_isp'] ?? null, 80);

    $paisIp = saneador_codigo_pais($crudo['ip_country'] ?? null, $contexto['paises_validos']);
    $limpio['ip_country'] = $paisIp;
    if ($paisIp === null) {
        return;
    }

    $fechaUtc = DateTimeImmutable::createFromFormat(SANEADOR_FORMATO_FECHA, $timestampUtc, new DateTimeZone('UTC'));
    if ($fechaUtc === false) {
        return;
    }
    $offsetMinutos = (int) round(($contexto['offset_por_pais'][$paisIp] ?? 0) * 60);
    $limpio['ip_local_time'] = $fechaUtc->modify(sprintf('%+d minutes', $offsetMinutos))->format('H:i:s');
}
