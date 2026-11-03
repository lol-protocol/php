<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which URLs answer 404 or 301, as a count per day, to find broken inbound links and to know when an old URL
 * (the purchase flow's /cart/, /checkout/...) no longer gets visits and its redirect can go.
 *
 * It keeps the SHAPE of a path and never the path: a 404 is exactly where somebody's name ends up in a URL
 * (/maria-garcia/, /b/10/maria%20garcia/). The shape says what kind of segment each one was and nothing more:
 *
 *   an id of 10 digits   -> #10        a letter       -> itself (b, c, p, r)
 *   0 (the account)      -> 0          2 / 3 letters  -> aa / aaa
 *   4 or more letters    -> a4+        anything else  -> ?
 *   a file name          -> *.php      a word the site itself routes (robots.txt, cart...) -> itself
 *
 * No IP, no query string, no user agent. One line per request, appended to a file per day:
 * "status<TAB>shape<TAB>where it went" (the target's shape for a 301, "-" for a 404). A day's file stops growing
 * at MAX_BYTES_POR_DIA, so a flood of 404s cannot fill the disk, and files older than DIAS_QUE_SE_GUARDAN are
 * removed when a new day's file is made. Writing never fails a request.
 */
final class RegistroDeUrls
{
    public const MAX_BYTES_POR_DIA = 5_000_000;
    public const DIAS_QUE_SE_GUARDAN = 60;
    /** More segments than this are cut: «…» (a path of 200 segments is not a page). */
    private const MAX_SEGMENTOS = 6;
    private const PREFIJO = 'urls-';
    /**
     * The only extensions kept: a closed list of what a scanner asks for (/wp-login.php, /.env, /backup.sql). Any
     * other dot is part of a name («juan.perez»), so it is a «?»: a surname must not pass for an extension.
     */
    private const EXTENSIONES = 'php|phtml|asp|aspx|jsp|cgi|html|htm|txt|xml|json|js|css|ico|map|zip|gz|tar|sql|bak|env|ini|conf|yml|yaml|log|git|svn|htaccess|htpasswd|png|jpg|jpeg|gif|svg|webp|pdf';

    /** @param string|null $dir where the day files go; null: nothing is recorded */
    public function __construct(private ?string $dir, private ?\Closure $reloj = null)
    {
    }

    /** URL_LOG_DIR in .env: a folder, "off" to record nothing, empty for a folder in the system temp dir. */
    public static function desdeConfig(): self
    {
        $valor = trim((string)Config::get('URL_LOG_DIR', ''));
        if ($valor === 'off') {
            return new self(null);
        }

        return new self($valor === '' ? sys_get_temp_dir() . '/url-routing-urls' : rtrim($valor, '/'));
    }

    /**
     * The shape of $uri (its path; the query string is dropped). The case is not kept either, and a final slash is.
     *
     * @param list<string> $conocidas words the site routes itself (Router::palabras()), which are safe to keep
     */
    public static function forma(string $uri, array $conocidas = []): string
    {
        $ruta = (string)strtok($uri, '?');
        $segmentos = array_values(array_filter(explode('/', strtolower($ruta)), static fn(string $s): bool => $s !== ''));
        if ($segmentos === []) {
            return '/';
        }

        $corto = count($segmentos) > self::MAX_SEGMENTOS;
        $tokens = array_map(
            static fn(string $s): string => self::token($s, $conocidas),
            array_slice($segmentos, 0, self::MAX_SEGMENTOS)
        );
        if ($corto) {
            $tokens[] = '…';
        }

        // The final slash stays as it came: it is what tells «/6128473105» from «/6128473105/» in a 301.
        return '/' . implode('/', $tokens) . (str_ends_with($ruta, '/') ? '/' : '');
    }

    /** @param list<string> $conocidas */
    private static function token(string $segmento, array $conocidas): string
    {
        return match (true) {
            in_array($segmento, $conocidas, true) => $segmento,
            ctype_digit($segmento) => '#' . strlen($segmento),
            preg_match('/^[a-z]$/', $segmento) === 1 => $segmento,
            preg_match('/^[a-z]{2}$/', $segmento) === 1 => 'aa',
            preg_match('/^[a-z]{3}$/', $segmento) === 1 => 'aaa',
            preg_match('/^[a-z]{4,}$/', $segmento) === 1 => 'a4+',
            preg_match('/\.(' . self::EXTENSIONES . ')$/', $segmento, $m) === 1 && preg_match('/^[a-z0-9._-]+$/', $segmento) === 1 => '*.' . $m[1],
            default => '?',
        };
    }

    /**
     * Notes a request that answered $estado. Only 404 and 301 count; anything else, and any failure to write, is ignored.
     *
     * @param list<string> $conocidas see forma()
     * @param string|null $destino the Location of a 301
     */
    public function registrar(int $estado, string $uri, ?string $destino = null, array $conocidas = []): void
    {
        if ($this->dir === null || !in_array($estado, [301, 404], true)) {
            return;
        }

        $archivo = $this->archivo($this->hoy());
        if (!is_file($archivo)) {
            if (!@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
                return;
            }
            $this->depurar();
        } elseif ((int)@filesize($archivo) >= self::MAX_BYTES_POR_DIA) {
            return;
        }

        $donde = $estado === 301 && $destino !== null ? self::forma($destino, $conocidas) : '-';
        @file_put_contents($archivo, $estado . "\t" . self::forma($uri, $conocidas) . "\t" . $donde . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * What happened in the last $dias days (today included), most frequent first.
     *
     * @return list<array{estado: int, forma: string, destino: string, total: int}>
     */
    public function resumen(int $dias): array
    {
        $cuentas = [];
        for ($atras = 0; $atras < $dias; $atras++) {
            $lineas = $this->dir === null ? false : @file($this->archivo($this->hoy() - $atras * 86400), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lineas ?: [] as $linea) {
                if (substr_count($linea, "\t") !== 2) {
                    continue; // half-written line: ignore it
                }
                $cuentas[$linea] = ($cuentas[$linea] ?? 0) + 1;
            }
        }

        $filas = [];
        foreach ($cuentas as $linea => $total) {
            [$estado, $forma, $destino] = explode("\t", (string)$linea);
            $filas[] = ['estado' => (int)$estado, 'forma' => $forma, 'destino' => $destino, 'total' => $total];
        }
        usort($filas, static fn(array $a, array $b): int => [$b['total'], $a['forma']] <=> [$a['total'], $b['forma']]);

        return $filas;
    }

    private function hoy(): int
    {
        return $this->reloj !== null ? ($this->reloj)() : time();
    }

    private function archivo(int $momento): string
    {
        return $this->dir . '/' . self::PREFIJO . gmdate('Y-m-d', $momento) . '.tsv';
    }

    /** Removes this class's day files older than DIAS_QUE_SE_GUARDAN, and nothing else. */
    private function depurar(): void
    {
        $limite = gmdate('Y-m-d', $this->hoy() - self::DIAS_QUE_SE_GUARDAN * 86400);
        foreach (glob($this->dir . '/' . self::PREFIJO . '*.tsv') ?: [] as $archivo) {
            if (preg_match('/^' . self::PREFIJO . '(\d{4}-\d{2}-\d{2})\.tsv$/', basename($archivo), $m) === 1 && $m[1] < $limite) {
                @unlink($archivo);
            }
        }
    }
}
