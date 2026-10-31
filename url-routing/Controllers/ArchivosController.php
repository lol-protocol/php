<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SitemapRepository;
use App\Support\Config;
use App\Support\HostParser;
use App\Support\RespuestaCondicional;
use App\Support\ServiceLocator;
use App\Support\SitemapEstado;

/**
 * /robots.txt, /sitemap.xml (an index) and /sitemap/{tipo}/{n}.xml, where
 * {tipo} is the type's digit width (or the letter its sitemap repository
 * gives a type without one) and {n} the 1-based part: a sitemap file holds
 * at most SitemapRepository::POR_PARTE URLs.
 *
 * Every request asks the database again: a page that is hidden or withdrawn
 * leaves the sitemap at once. What a crawler can skip is the download: each
 * file goes out with an ETag and, when SitemapEstado knows it, a
 * Last-Modified, and a request that already has the current copy gets a 304.
 * The index gives each part the <lastmod> SitemapEstado remembers for it (the
 * last time its list of pages changed); the pages themselves carry no
 * <lastmod>, because the tables keep no modification dates.
 */
abstract class ArchivosController extends BaseController
{
    abstract protected function repo(): SitemapRepository;

    /** The .env key with the site's public address (e.g. https://conce.com). */
    abstract protected function claveUrl(): string;

    /**
     * Paths no crawler should walk: the account area, search results, the purchase flow...
     *
     * @return list<string>
     */
    abstract protected function excluidas(): array;

    /** The page of $clave in sitemap file $tipo, relative to the site's root. */
    protected function enlaceDe(string $tipo, string $clave): string
    {
        $nombre = ServiceLocator::getInstance()->getRouter()->typeForLength((int)$tipo);
        return enlace((string)$nombre, $clave);
    }

    /** Absolute URLs need the site's own address: the configured one, otherwise this request's. */
    private function base(): string
    {
        return rtrim((string)Config::get($this->claveUrl(), ''), '/') ?: HostParser::origen();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function robots(array $params = []): string
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $reglas = array_map(static fn(string $ruta): string => "Disallow: {$ruta}\n", $this->excluidas());
        return "User-agent: *\n" . implode('', $reglas) . "\nSitemap: " . $this->base() . "/sitemap.xml\n";
    }

    /**
     * @param array<string, mixed> $params
     */
    public function indice(array $params = []): string
    {
        $repo = $this->repo();
        $base = $this->base();
        $estado = SitemapEstado::desdeConfig();
        $partes = [];
        foreach ($repo->tipos() as $tipo) {
            $total = (int)ceil($repo->cuantas($tipo) / SitemapRepository::POR_PARTE);
            for ($n = 1; $n <= $total; $n++) {
                $url = "{$base}/sitemap/{$tipo}/{$n}.xml";
                // Known once the part has been served: until then, no date rather than a made-up one.
                $partes[] = [$url, $estado->desde($url)];
            }
        }

        return self::servir($estado, "{$base}/sitemap.xml", self::xml('sitemapindex', 'sitemap', $partes));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function parte(array $params = []): string
    {
        $repo = $this->repo();
        $ruta = $params['ruta'] ?? [];
        if (count($ruta) !== 2 || !in_array($ruta[0], $repo->tipos(), true)
            || preg_match('/^([1-9]\d{0,5})\.xml$/', $ruta[1], $m) !== 1) {
            return $this->handleNotFound();
        }

        $claves = $repo->claves($ruta[0], (int)$m[1]);
        if ($claves === []) {
            return $this->handleNotFound();
        }

        $base = $this->base();
        $xml = self::xml('urlset', 'url', array_map(fn(string $clave): array => [$base . $this->enlaceDe($ruta[0], $clave), null], $claves));

        return self::servir(SitemapEstado::desdeConfig(), "{$base}/sitemap/{$ruta[0]}/{$m[1]}.xml", $xml);
    }

    /**
     * Sends $xml, the file at $url, with its validators; a 304 with no body if the client has it already.
     * no-cache: a copy may be kept, but is asked about every time, so a page that left the list is not
     * listed by anyone's cache either.
     */
    private static function servir(SitemapEstado $estado, string $url, string $xml): string
    {
        $etag = '"' . substr(hash('sha256', $xml), 0, 32) . '"';
        $desde = $estado->registrar($url, $xml);

        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        header("ETag: {$etag}");
        if ($desde !== null) {
            header('Last-Modified: ' . RespuestaCondicional::fechaHttp($desde));
        }

        if (RespuestaCondicional::sinCambios($_SERVER['HTTP_IF_NONE_MATCH'] ?? null, $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null, $etag, $desde)) {
            http_response_code(304);
            return '';
        }

        return $xml;
    }

    /** @param list<array{string, int|null}> $entradas each file or page: its URL and, if known, since when it is what it is */
    private static function xml(string $raiz, string $elemento, array $entradas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . "<{$raiz} xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($entradas as [$url, $desde]) {
            $fecha = $desde === null ? '' : '<lastmod>' . gmdate('Y-m-d\\TH:i:s\\Z', $desde) . '</lastmod>';
            $xml .= "  <{$elemento}><loc>" . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>{$fecha}</{$elemento}>\n";
        }

        return $xml . "</{$raiz}>\n";
    }
}
