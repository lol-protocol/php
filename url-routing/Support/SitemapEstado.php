<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Remembers, for each sitemap file, a fingerprint of what it lists and since when it lists exactly
 * that: the date a sitemap index gives a file in its <lastmod>, and the Last-Modified the file is
 * served with. The tables keep no modification dates, so this is the only honest date there is: it
 * moves when a page enters or leaves the list, never because the file was generated again.
 *
 * Nothing is cached here: every request still asks the database, so a page that is hidden or
 * withdrawn leaves the sitemap at once. A small JSON file per sitemap file, in SITEMAP_ESTADO_DIR
 * (by default a folder in the system temp dir). If that folder is lost the dates start over, and
 * if it cannot be written there are none: no date is better than a false one.
 */
final class SitemapEstado
{
    /** @param \Closure(): int|null $reloj the current time, for the tests */
    public function __construct(private string $dir, private ?\Closure $reloj = null)
    {
    }

    public static function desdeConfig(): self
    {
        return new self(rtrim((string)Config::get('SITEMAP_ESTADO_DIR', ''), '/') ?: sys_get_temp_dir() . '/url-routing-sitemaps');
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

    /** @param array{huella: string, desde: int} $estado */
    private function escribir(string $archivo, array $estado): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            return false;
        }
        // Written aside and renamed, so a request that reads at the same time never sees half a file.
        $temporal = @tempnam($this->dir, 'tmp');
        if ($temporal === false || @file_put_contents($temporal, json_encode($estado)) === false) {
            return false;
        }

        if (!@rename($temporal, $this->ruta($archivo))) {
            @unlink($temporal);
            return false;
        }

        return true;
    }

    /** One file per sitemap URL: the site's address is part of it, so two sites (or a test copy) never share one. */
    private function ruta(string $archivo): string
    {
        return $this->dir . '/' . hash('sha256', $archivo) . '.json';
    }
}
