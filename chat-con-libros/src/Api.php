<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * La API JSON de la página web (web/api.php es una envoltura fina de esta clase, para poder
 * probarla sin servidor).
 *
 *   GET  api.php?accion=libros      → los libros que se pueden elegir y si hay IA disponible
 *   POST api.php?accion=preguntar   → {libro, pregunta, modo: "sin-ia"|"ia", historial: [...]}
 *
 * Está pensada para correr en tu propio computador (php -S 127.0.0.1:8080), no en internet:
 *  - Solo acepta pedidos cuyo Host sea localhost, 127.0.0.1 o [::1] (contra «DNS rebinding»), y si
 *    traen Origin, que sea el mismo sitio (contra páginas ajenas que le escriban a tu servidor).
 *  - El POST exige application/json: un formulario de otra página no puede mandarlo sin pasar por
 *    CORS, y esta API no manda cabeceras CORS.
 *  - El libro se elige de una lista (ejemplos/ y mis-libros/), nunca por una ruta que mande el cliente.
 *  - Todo tiene tope de tamaño: el modo con IA gasta saldo de tu clave.
 */
final class Api
{
    public const MAX_PREGUNTA = 500;
    public const MAX_CUERPO = 65536;
    public const MAX_TURNO = 2000;

    /**
     * @param string $raiz carpeta del proyecto (la que tiene ejemplos/, mis-libros/ y datos/)
     * @param Generador|null $generador lo que redacta en modo IA; null usa GeneradorClaude si está disponible (las pruebas pasan uno falso)
     */
    public function __construct(private readonly string $raiz, private readonly ?Generador $generador = null)
    {
    }

    /**
     * @param array<string,string> $cabeceras con los nombres en minúsculas
     * @return array{codigo:int, cuerpo:array<string,mixed>}
     */
    public function manejar(string $metodo, string $accion, array $cabeceras, string $cuerpo): array
    {
        $rechazo = $this->rechazarSiNoEsLocal($cabeceras);
        if ($rechazo !== null) {
            return $rechazo;
        }
        if ($accion === 'libros') {
            return $metodo === 'GET' ? $this->listar() : self::error(405, 'Usa GET.');
        }
        if ($accion === 'preguntar') {
            return $metodo === 'POST' ? $this->preguntar($cabeceras, $cuerpo) : self::error(405, 'Usa POST.');
        }
        return self::error(404, 'Acción desconocida.');
    }

    /** @return list<string> ids de libro: rutas relativas a la raíz, como «ejemplos/muestra.txt» */
    public function idsDeLibros(): array
    {
        $ids = [];
        foreach (['ejemplos', 'mis-libros'] as $carpeta) {
            $archivos = glob($this->raiz . '/' . $carpeta . '/*.txt') ?: [];
            sort($archivos);
            foreach ($archivos as $archivo) {
                $ids[] = $carpeta . '/' . basename($archivo);
            }
        }
        return $ids;
    }

    /** @return array{codigo:int, cuerpo:array<string,mixed>} */
    private function listar(): array
    {
        $libros = [];
        foreach ($this->idsDeLibros() as $id) {
            $libro = Libro::desdeArchivo($this->raiz . '/' . $id);
            $libros[] = ['id' => $id, 'titulo' => $libro->titulo, 'autor' => $libro->autor, 'pasajes' => count($libro->pasajes)];
        }
        $motivo = $this->generador !== null ? null : GeneradorClaude::motivoNoDisponible();
        return ['codigo' => 200, 'cuerpo' => [
            'libros' => $libros,
            'ia' => ['disponible' => $motivo === null, 'motivo' => $motivo],
        ]];
    }

    /**
     * @param array<string,string> $cabeceras
     * @return array{codigo:int, cuerpo:array<string,mixed>}
     */
    private function preguntar(array $cabeceras, string $cuerpo): array
    {
        if (!str_starts_with(strtolower($cabeceras['content-type'] ?? ''), 'application/json')) {
            return self::error(415, 'El cuerpo debe ser application/json.');
        }
        if (strlen($cuerpo) > self::MAX_CUERPO) {
            return self::error(413, 'El pedido es demasiado grande.');
        }
        $datos = json_decode($cuerpo, true);
        if (!is_array($datos)) {
            return self::error(400, 'El cuerpo no es un JSON válido.');
        }

        $id = $datos['libro'] ?? null;
        if (!is_string($id) || !in_array($id, $this->idsDeLibros(), true)) {
            return self::error(404, 'Ese libro no está disponible.');
        }
        $pregunta = $datos['pregunta'] ?? null;
        if (!is_string($pregunta) || trim($pregunta) === '') {
            return self::error(400, 'Escribe una pregunta.');
        }
        if (mb_strlen($pregunta) > self::MAX_PREGUNTA) {
            return self::error(400, 'La pregunta es demasiado larga (máximo ' . self::MAX_PREGUNTA . ' caracteres).');
        }
        $modo = $datos['modo'] ?? 'sin-ia';
        if ($modo !== 'sin-ia' && $modo !== 'ia') {
            return self::error(400, 'El modo debe ser «sin-ia» o «ia».');
        }
        $historial = self::historial($datos['historial'] ?? []);
        if ($historial === null) {
            return self::error(400, 'El historial no tiene el formato esperado.');
        }

        $generador = null;
        if ($modo === 'ia') {
            $generador = $this->generador;
            if ($generador === null) {
                $motivo = GeneradorClaude::motivoNoDisponible();
                if ($motivo !== null) {
                    return self::error(409, 'El modo con IA no está disponible. ' . $motivo);
                }
                $generador = new GeneradorClaude();
            }
        }

        $ruta = $this->raiz . '/' . $id;
        $sinonimos = Sinonimos::desdeArchivos([
            $this->raiz . '/datos/sinonimos.tsv',
            (string) preg_replace('/\.txt$/i', '', $ruta) . '.sinonimos.tsv',
        ]);
        $dialogo = new Dialogo(new Conversador(Libro::desdeArchivo($ruta), $sinonimos), $generador);
        return ['codigo' => 200, 'cuerpo' => $dialogo->responder(trim($pregunta), $historial)];
    }

    /**
     * @return list<array{pregunta:string, respuesta:string}>|null null si no tiene el formato esperado
     */
    private static function historial(mixed $crudo): ?array
    {
        if (!is_array($crudo) || count($crudo) > 2 * Dialogo::MAX_HISTORIAL) {
            return null;
        }
        $turnos = [];
        foreach ($crudo as $turno) {
            if (!is_array($turno) || !is_string($turno['pregunta'] ?? null) || !is_string($turno['respuesta'] ?? null)
                || mb_strlen($turno['pregunta']) > self::MAX_TURNO || mb_strlen($turno['respuesta']) > self::MAX_TURNO) {
                return null;
            }
            $turnos[] = ['pregunta' => $turno['pregunta'], 'respuesta' => $turno['respuesta']];
        }
        return array_slice($turnos, -Dialogo::MAX_HISTORIAL);
    }

    /**
     * @param array<string,string> $cabeceras
     * @return array{codigo:int, cuerpo:array<string,mixed>}|null
     */
    private function rechazarSiNoEsLocal(array $cabeceras): ?array
    {
        $host = strtolower($cabeceras['host'] ?? '');
        $nombre = (string) preg_replace('/:\d+$/', '', $host);
        if (!in_array($nombre, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return self::error(403, 'Solo se atiende desde localhost.');
        }
        $origen = $cabeceras['origin'] ?? null;
        if ($origen !== null && $origen !== '') {
            $autoridad = parse_url($origen, PHP_URL_HOST);
            $puerto = parse_url($origen, PHP_URL_PORT);
            $deOrigen = strtolower((is_string($autoridad) ? $autoridad : '') . ($puerto !== null && $puerto !== false ? ':' . $puerto : ''));
            if ($deOrigen !== $host) {
                return self::error(403, 'El pedido viene de otro sitio.');
            }
        }
        return null;
    }

    /** @return array{codigo:int, cuerpo:array<string,mixed>} */
    private static function error(int $codigo, string $mensaje): array
    {
        return ['codigo' => $codigo, 'cuerpo' => ['error' => $mensaje]];
    }
}
