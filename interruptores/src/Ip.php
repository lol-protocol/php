<?php

declare(strict_types=1);

namespace LolProtocol\Interruptores;

/**
 * Dirección IP guardada de forma parcial: lo que sirve para auditar sin
 * identificar a una persona concreta.
 *
 *  - IPv4: se pone a cero el último octeto (203.0.113.87 → 203.0.113.0).
 *  - IPv6: se pone a cero la mitad baja (se queda el prefijo /64).
 */
final class Ip
{
    public static function enmascarar(string $ip): string
    {
        $binario = @inet_pton($ip);
        if ($binario === false) {
            return 'desconocida';
        }
        if (strlen($binario) === 4) {
            $binario[3] = "\0";
        } else {
            for ($k = 8; $k < 16; $k++) {
                $binario[$k] = "\0";
            }
        }
        return (string) inet_ntop($binario);
    }
}
