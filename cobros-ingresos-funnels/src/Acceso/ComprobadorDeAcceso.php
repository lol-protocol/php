<?php

declare(strict_types=1);

namespace App\Acceso;

use InvalidArgumentException;

/**
 * Comprueba, desde afuera, que el panel no responde a quien llega sin credenciales.
 *
 * El panel no tiene login (ver README): quien llegue a la URL ve los datos de los clientes
 * y puede crear y anular cosas. Lo que lo cubre es el servidor web (autenticacion basica,
 * una lista de IPs, una VPN), y un error de configuracion ahi no se nota hasta que alguien
 * entra. Esto pide varias pantallas sin enviar ninguna credencial y dice si alguna contesta.
 *
 * - Una pantalla que contesta 2xx esta ABIERTA: no hay proteccion (o esta puesta solo sobre
 *   parte de las rutas).
 * - 401 o 403 en todas: protegido.
 * - Cualquier otra cosa (una redireccion, un 404, un 500, que no se pueda conectar) no
 *   prueba nada en ningun sentido: el resultado es inconcluso. Una redireccion de http a
 *   https es lo mas comun: hay que probar con la direccion https.
 *
 * Si el acceso se limita por IP, hay que correrlo desde una direccion que NO este permitida:
 * desde una permitida el panel contesta y parece abierto.
 */
final class ComprobadorDeAcceso
{
    public const PROTEGIDO = 'protegido';
    public const ABIERTO = 'abierto';
    public const INCONCLUSO = 'inconcluso';

    /** Pantallas con datos que se piden sin credenciales (una de cada tipo: listado, ficha, auditoria...). */
    public const RUTAS = ['/', '/?page=dashboard', '/?page=clientes', '/?page=cliente&id=1', '/?page=pagos', '/?page=auditoria', '/?page=cliente-nuevo'];

    /** @var callable(string): array{estado: ?int, destino: ?string, error: ?string} */
    private $pedir;

    /** @param (callable(string): array{estado: ?int, destino: ?string, error: ?string})|null $pedir como pedir una URL (para probarlo sin red) */
    public function __construct(?callable $pedir = null)
    {
        $this->pedir = $pedir ?? [self::class, 'pedirPorHttp'];
    }

    /**
     * @return array{
     *     resultado: string,
     *     pantallas: list<array{ruta: string, url: string, estado: ?int, veredicto: string, detalle: ?string}>
     * }
     * @throws InvalidArgumentException si $base no es una direccion http(s)
     */
    public function comprobar(string $base): array
    {
        $base = self::normalizar($base);

        $pantallas = [];
        $hayAbiertas = false;
        $todasProtegidas = true;
        foreach (self::RUTAS as $ruta) {
            $url = $base . $ruta;
            $respuesta = ($this->pedir)($url);
            $estado = $respuesta['estado'];

            if ($estado === 401 || $estado === 403) {
                $veredicto = self::PROTEGIDO;
                $detalle = null;
            } elseif ($estado !== null && $estado >= 200 && $estado < 300) {
                $veredicto = self::ABIERTO;
                $detalle = 'contesta sin pedir credenciales';
                $hayAbiertas = true;
                $todasProtegidas = false;
            } else {
                $veredicto = self::INCONCLUSO;
                $detalle = match (true) {
                    $estado === null => $respuesta['error'] ?? 'no se pudo conectar',
                    $estado >= 300 && $estado < 400 => 'redirige a ' . ($respuesta['destino'] ?? '(sin destino)') . ': probá con esa dirección',
                    default => "contesta {$estado}: no prueba que esté protegida",
                };
                $todasProtegidas = false;
            }

            $pantallas[] = ['ruta' => $ruta, 'url' => $url, 'estado' => $estado, 'veredicto' => $veredicto, 'detalle' => $detalle];
        }

        return [
            'resultado' => $hayAbiertas ? self::ABIERTO : ($todasProtegidas ? self::PROTEGIDO : self::INCONCLUSO),
            'pantallas' => $pantallas,
        ];
    }

    /**
     * La direccion sin barra final, con esquema y servidor. Puede llevar una ruta (el panel
     * colgado de /panel) pero no una consulta ni un fragmento: las pantallas la agregan ellas.
     *
     * @throws InvalidArgumentException
     */
    public static function normalizar(string $base): string
    {
        $partes = parse_url(trim($base));
        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        if ($partes === false || !in_array($esquema, ['http', 'https'], true) || ($partes['host'] ?? '') === '') {
            throw new InvalidArgumentException('La dirección tiene que empezar con http:// o https:// y llevar el servidor (ej. https://panel.ejemplo.com).');
        }
        if (isset($partes['query']) || isset($partes['fragment'])) {
            throw new InvalidArgumentException('La dirección no lleva consulta ni fragmento: se agregan solas las pantallas.');
        }
        if (isset($partes['user']) || isset($partes['pass'])) {
            throw new InvalidArgumentException('La dirección no lleva usuario ni clave: la idea es comprobar qué pasa sin credenciales.');
        }

        return $esquema . '://' . $partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '') . rtrim((string) ($partes['path'] ?? ''), '/');
    }

    /**
     * Un GET sin credenciales, sin seguir redirecciones y con la verificacion del certificado
     * de siempre. Solo importa el estado: se lee un poco del cuerpo y nada mas.
     *
     * @return array{estado: ?int, destino: ?string, error: ?string}
     */
    public static function pedirPorHttp(string $url): array
    {
        $contexto = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "User-Agent: cobros-ingresos-funnels/comprobar-acceso\r\n",
        ]]);

        error_clear_last();
        $cuerpo = @file_get_contents($url, false, $contexto, 0, 1024);
        if ($cuerpo === false) {
            return ['estado' => null, 'destino' => null, 'error' => 'no se pudo conectar: ' . (error_get_last()['message'] ?? 'sin detalle')];
        }

        $estado = null;
        $destino = null;
        foreach ($http_response_header as $linea) {
            if (preg_match('#^HTTP/\d(?:\.\d)? (\d{3})#', $linea, $m) === 1) {
                $estado = (int) $m[1];
                $destino = null;   // cada redireccion trae su linea de estado: vale el Location de la ultima
            } elseif (stripos($linea, 'Location:') === 0) {
                $destino = trim(substr($linea, 9));
            }
        }

        return ['estado' => $estado, 'destino' => $destino, 'error' => null];
    }
}
