<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Remembers, for each sitemap file, a fingerprint of what it lists and since when it lists exactly
 * that: the date a sitemap index gives a file in its <lastmod>, and the Last-Modified the file is
 * served with. The tables keep no modification dates, so this is the only honest date there is: it
 * moves when a page enters or leaves the list, never because the file was generated again.
 *
 * The pages themselves are never cached here: every request still asks the database, so a page that is
 * hidden or withdrawn leaves the sitemap at once. The one thing kept for a few minutes is how many pages a
 * type has (cuenta()), which only decides how many files the index announces. A small JSON file per sitemap file, in SITEMAP_ESTADO_DIR
 * (by default a folder in the system temp dir). If that folder is lost the dates start over, and
 * if it cannot be written there are none: no date is better than a false one. That silence is for the
 * visitor only: the reason it could not be written (a full disk, a folder PHP may not write) goes to the
 * log, once per process, or the dates would be missing for good with nobody knowing why.
 */
final class SitemapEstado
{
    private bool $avisado = false;

    /**
     * @param \Closure(): int|null $reloj the current time, for the tests
     * @param \Closure(string, array<string, mixed>): void|null $avisar where to say a write failed (the log); nowhere by default
     */
    public function __construct(private string $dir, private ?\Closure $reloj = null, private ?\Closure $avisar = null)
    {
    }

    public static function desdeConfig(): self
    {
        return new self(
            rtrim((string)Config::get('SITEMAP_ESTADO_DIR', ''), '/') ?: sys_get_temp_dir() . '/url-routing-sitemaps',
            null,
            static fn(string $mensaje, array $contexto) => log_error($mensaje, $contexto)
        );
    }

    /**
     * Notes that $archivo (its absolute URL) lists $contenido now.
     *
     * @return int|null since when it lists exactly this (a Unix time), or null if it cannot be remembered
     */
    public function registrar(string $archivo, string $contenido): ?int
    {
        $huella = hash('sha256', $contenido);
        $previo = $this->leer($archivo);
        if ($previo !== null && $previo['huella'] === $huella) {
            return $previo['desde'];
        }

        $desde = $this->reloj !== null ? ($this->reloj)() : time();
        return $this->escribir($archivo, ['huella' => $huella, 'desde' => $desde]) ? $desde : null;
    }

    /**
     * A number that is costly to work out and may be a few minutes old (how many rows a sitemap type has, which
     * only decides how many files the index announces): what $calcular gives, kept for $segundos. When it cannot
     * be kept (a folder that cannot be written), it is worked out every time, as if there were no cache.
     *
     * @param \Closure(): int $calcular
     */
    public function cuenta(string $clave, int $segundos, \Closure $calcular): int
    {
        $ahora = $this->reloj !== null ? ($this->reloj)() : time();
        $json = @file_get_contents($this->ruta('cuenta:' . $clave));
        $guardada = $json === false ? null : json_decode($json, true);
        if (is_array($guardada) && is_int($guardada['n'] ?? null) && is_int($guardada['hasta'] ?? null) && $guardada['hasta'] > $ahora) {
            return $guardada['n'];
        }

        $n = $calcular();
        $this->escribir('cuenta:' . $clave, ['n' => $n, 'hasta' => $ahora + $segundos]);

        return $n;
    }

    /** @return int|null since when $archivo lists what it lists, or null if it was never served (or its state was lost) */
    public function desde(string $archivo): ?int
    {
        return $this->leer($archivo)['desde'] ?? null;
    }

    /** @return array{huella: string, desde: int}|null */
    private function leer(string $archivo): ?array
    {
        $json = @file_get_contents($this->ruta($archivo));
        $estado = $json === false ? null : json_decode($json, true);

        return is_array($estado) && is_string($estado['huella'] ?? null) && is_int($estado['desde'] ?? null) ? $estado : null;
    }

    /** @param array<string, string|int> $estado */
    private function escribir(string $archivo, array $estado): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            return $this->fallo('no se pudo crear la carpeta');
        }
        // Written aside and renamed, so a request that reads at the same time never sees half a file.
        $temporal = @tempnam($this->dir, 'tmp');
        if ($temporal === false || @file_put_contents($temporal, json_encode($estado)) === false) {
            return $this->fallo('no se pudo escribir el archivo de estado');
        }

        if (!@rename($temporal, $this->ruta($archivo))) {
            @unlink($temporal);
            return $this->fallo('no se pudo reemplazar el archivo de estado');
        }

        return true;
    }

    /** Says once per process why the state cannot be kept (PHP's own message, no sitemap URL) and returns false. */
    private function fallo(string $que): bool
    {
        if (!$this->avisado && $this->avisar !== null) {
            $this->avisado = true;
            ($this->avisar)("sitemap sin fechas: {$que}", ['dir' => $this->dir, 'php' => error_get_last()['message'] ?? '']);
        }

        return false;
    }

    /** One file per sitemap URL: the site's address is part of it, so two sites (or a test copy) never share one. */
    private function ruta(string $archivo): string
    {
        return $this->dir . '/' . hash('sha256', $archivo) . '.json';
    }
}
