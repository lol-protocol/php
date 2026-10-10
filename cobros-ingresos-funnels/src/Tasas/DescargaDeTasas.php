<?php

declare(strict_types=1);

namespace App\Tasas;

/**
 * Baja el JSON de las tasas de una URL, solo con lo que trae PHP (sin curl ni
 * librerias).
 *
 * Solo https: unas tasas por http se pueden cambiar en el camino, y mover de golpe
 * todos los totales en USD. http se acepta unicamente hacia la propia maquina
 * (localhost, 127.0.0.1), que es lo que usan las pruebas. Tampoco se siguen
 * redirecciones: una que baje de https a http dejaria pasar lo mismo; si la fuente
 * cambia de direccion, se pone la nueva en TASAS_URL.
 */
final class DescargaDeTasas
{
    /** Una respuesta de tasas pesa decenas de KB: de mas de esto no es una. */
    public const MAXIMO_DE_BYTES = 2_000_000;

    /** @throws TasasInvalidas */
    public static function leer(string $url, int $segundosDeEspera = 20): string
    {
        $partes = parse_url($url);
        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        $esLocal = in_array(strtolower((string) ($partes['host'] ?? '')), ['localhost', '127.0.0.1', '[::1]'], true);
        $visible = self::sinSecretos($url);
        if ($esquema !== 'https' && !($esquema === 'http' && $esLocal)) {
            throw new TasasInvalidas("La URL de las tasas tiene que ser https (recibida: {$visible}).");
        }

        $contexto = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $segundosDeEspera,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "Accept: application/json\r\nUser-Agent: cobros-ingresos-funnels\r\n",
        ]]);

        error_clear_last();
        $cuerpo = @file_get_contents($url, false, $contexto, 0, self::MAXIMO_DE_BYTES + 1);
        if ($cuerpo === false) {
            // El aviso de PHP trae la URL entera ("file_get_contents(https://...?app_id=CLAVE): Failed to open stream").
            $motivo = str_replace($url, $visible, error_get_last()['message'] ?? 'sin detalle');
            throw new TasasInvalidas("No se pudo descargar {$visible}: {$motivo}");
        }

        $estado = self::estadoHttp($http_response_header);
        if ($estado !== 200) {
            throw new TasasInvalidas("{$visible} respondió HTTP " . ($estado ?? '?') . '.');
        }
        if (strlen($cuerpo) > self::MAXIMO_DE_BYTES) {
            throw new TasasInvalidas("La respuesta de {$visible} es demasiado grande para ser una lista de tasas.");
        }

        return $cuerpo;
    }

    /**
     * La URL como se puede mostrar: solo esquema y host (y puerto). Algunos
     * servicios piden la clave en la direccion, ya sea en la query
     * (?app_id=CLAVE) o en la ruta (/v6/CLAVE/latest/USD), y los errores
     * terminan en el mail de cron y en los logs.
     */
    private static function sinSecretos(string $url): string
    {
        $partes = parse_url($url);
        if ($partes === false || !isset($partes['host'])) {
            return '(una dirección que no es una URL)';
        }

        return (isset($partes['scheme']) ? $partes['scheme'] . '://' : '') . $partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '');
    }

    /**
     * El codigo de la ultima linea de estado ("HTTP/1.1 200 OK") de las cabeceras.
     *
     * @param array<int, string> $cabeceras
     */
    private static function estadoHttp(array $cabeceras): ?int
    {
        $estado = null;
        foreach ($cabeceras as $linea) {
            if (preg_match('#^HTTP/\d(?:\.\d)? (\d{3})#', $linea, $m) === 1) {
                $estado = (int) $m[1];
            }
        }

        return $estado;
    }
}
