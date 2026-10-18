<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SitemapRepository;
use App\Support\Config;
use App\Support\HostParser;
use App\Support\ServiceLocator;

/**
 * /robots.txt, /sitemap.xml (an index) and /sitemap/{tipo}/{n}.xml, where
 * {tipo} is the type's digit width (or the letter its sitemap repository
 * gives a type without one) and {n} the 1-based part: a sitemap file holds
 * at most SitemapRepository::POR_PARTE URLs.
 */
abstract class ArchivosController extends BaseController
{
    abstract protected function repo(): SitemapRepository;

    /** The .env key with the site's public address (e.g. https://conce.com). */
    abstract protected function claveUrl(): string;

    /** Paths no crawler should walk: the account area, search results, the purchase flow... */
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

    public function robots(array $params = []): string
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $reglas = array_map(static fn(string $ruta): string => "Disallow: {$ruta}\n", $this->excluidas());
        return "User-agent: *\n" . implode('', $reglas) . "\nSitemap: " . $this->base() . "/sitemap.xml\n";
    }

    public function indice(array $params = []): string
    {
        $repo = $this->repo();
        $base = $this->base();
        $partes = [];
        foreach ($repo->tipos() as $tipo) {
            $total = (int)ceil($repo->cuantas($tipo) / SitemapRepository::POR_PARTE);
            for ($n = 1; $n <= $total; $n++) {
                $partes[] = "{$base}/sitemap/{$tipo}/{$n}.xml";
            }
        }

        return self::xml('sitemapindex', 'sitemap', $partes);
    }

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
        return self::xml('urlset', 'url', array_map(fn(string $clave): string => $base . $this->enlaceDe($ruta[0], $clave), $claves));
    }

    /** @param list<string> $urls */
    private static function xml(string $raiz, string $elemento, array $urls): string
    {
        header('Content-Type: application/xml; charset=UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . "<{$raiz} xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= "  <{$elemento}><loc>" . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></{$elemento}>\n";
        }

        return $xml . "</{$raiz}>\n";
    }
}
