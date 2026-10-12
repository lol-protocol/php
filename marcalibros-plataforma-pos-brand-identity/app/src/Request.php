<?php

namespace MarcalibrosApp;

/** Lo que la aplicación necesita saber de una petición HTTP. Se arma desde las variables globales o a mano en las pruebas. */
final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $post
     * @param array<string, mixed>  $files   Igual que $_FILES.
     * @param array<string, string> $headers Nombres en minúsculas.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly string $clientIp = '0.0.0.0',
        public readonly array $headers = [],
        /** La petición superó post_max_size: PHP descartó el cuerpo entero. */
        public readonly bool $bodyTooLarge = false,
    ) {
    }

    /** @param array<string, mixed> $server */
    public static function fromGlobals(Config $config, array $server, array $get, array $post, array $files): self
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $uriPath = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($uriPath) ? rawurldecode($uriPath) : '/';
        if (!mb_check_encoding($path, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            $path = '/ruta-invalida';
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        if (strlen($path) > 1) {
            $path = rtrim($path, '/') ?: '/';
        }

        $headers = [];
        foreach ($server as $key => $value) {
            if (is_string($value) && str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = $value;
            }
        }

        $ip = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($config->trustForwardedFor && isset($headers['x-forwarded-for'])) {
            // La última dirección la agregó nuestro proxy; las anteriores las puede escribir cualquiera.
            $parts = array_map('trim', explode(',', $headers['x-forwarded-for']));
            $last = end($parts);
            if ($last !== false && filter_var($last, FILTER_VALIDATE_IP) !== false) {
                $ip = $last;
            }
        }

        $declared = (int) ($server['CONTENT_LENGTH'] ?? 0);
        // Si el cuerpo supera post_max_size, PHP lo descarta entero y $_POST y $_FILES llegan vacíos.
        $limit = self::iniBytes((string) ini_get('post_max_size'));

        return new self(
            $method,
            $path,
            $get,
            $post,
            $files,
            filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0',
            $headers,
            $method === 'POST' && $declared > 0 && $limit > 0 && $declared > $limit && $post === [] && $files === [],
        );
    }

    /** Convierte un valor de php.ini como "8M" o "512K" a bytes. 0 significa sin límite. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^(\d+)\s*([KMG]?)$/i', $value, $m) !== 1) {
            return 0;
        }
        $units = ['' => 1, 'K' => 1024, 'M' => 1_048_576, 'G' => 1_073_741_824];
        return (int) $m[1] * $units[strtoupper($m[2])];
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Texto de un campo de formulario; null si falta o si no es texto (por ejemplo, un arreglo). */
    public function field(string $name): ?string
    {
        $value = $this->post[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    /** Texto de un parámetro de la dirección; null si falta o si no es texto. */
    public function param(string $name): ?string
    {
        $value = $this->query[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Un archivo subido. Devuelve null si no hay campo; un campo con varios archivos
     * (`imagen[]`) se trata como un archivo con error, porque aquí se espera uno solo.
     *
     * @return array{name: string, tmp_name: string, error: int, size: int}|null
     */
    public function upload(string $name): ?array
    {
        $file = $this->files[$name] ?? null;
        if (!is_array($file)) {
            return null;
        }
        if (!isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error']) || is_array($file['tmp_name'])) {
            return ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];
        }
        return [
            'name' => is_string($file['name'] ?? null) ? $file['name'] : '',
            'tmp_name' => (string) $file['tmp_name'],
            'error' => (int) $file['error'],
            'size' => (int) $file['size'],
        ];
    }
}
