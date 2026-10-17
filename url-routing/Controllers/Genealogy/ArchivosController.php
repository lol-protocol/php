<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\SitemapRepository;
use App\Support\Config;
use App\Support\HttpSecurityHeaders;
use App\Support\Privacidad;
use App\Support\ServiceLocator;

/**
 * /robots.txt, /sitemap.xml (an index) and /sitemap/{tipo}/{n}.xml, where
 * {tipo} is the type's digit width ("l" for places) and {n} the 1-based
 * part: a sitemap file holds at most SitemapRepository::POR_PARTE URLs.
 */
class ArchivosController extends BaseController
{
    private function repo(): SitemapRepository
    {
        return new SitemapRepository($this->db(), Privacidad::publica());
    }

    /**
     * Absolute URLs need the site's own address: SITE_URL_GENEALOGY when set,
     * otherwise this request's scheme and host (only a plain host name is
     * trusted from the Host header).
     */
    private static function base(): string
    {
        $configurada = rtrim((string)Config::get('SITE_URL_GENEALOGY', ''), '/');
        if ($configurada !== '') {
            return $configurada;
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host) !== 1) {
            $host = 'localhost';
        }

        return (HttpSecurityHeaders::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    public function robots(array $params = []): string
    {
        header('Content-Type: text/plain; charset=UTF-8');

        // The account area is the owner's; everything else private already answers 404.
        return "User-agent: *\nDisallow: /0/\n\nSitemap: " . self::base() . "/sitemap.xml\n";
    }

    public function indice(array $params = []): string
    {
        $repo = $this->repo();
        $base = self::base();
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
        [$tipo, $archivo] = array_pad($params['ruta'] ?? [], 2, '');
        if (count($params['ruta'] ?? []) !== 2 || !in_array($tipo, $repo->tipos(), true)
            || preg_match('/^([1-9]\d{0,5})\.xml$/', $archivo, $m) !== 1) {
            return $this->handleNotFound();
        }

        $claves = $repo->claves($tipo, (int)$m[1]);
        if ($claves === []) {
            return $this->handleNotFound();
        }

        $base = self::base();
        $nombre = $tipo === 'l' ? null : ServiceLocator::getInstance()->getRouter()->typeForLength((int)$tipo);
        $urls = array_map(
            static fn(string $clave): string => $base . ($nombre === null ? enlaceLugar(explode('/', $clave)) : enlace($nombre, $clave)),
            $claves
        );

        return self::xml('urlset', 'url', $urls);
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
