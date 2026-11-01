<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\ArchivosController as Base;
use App\Repositories\Genealogy\SitemapRepository;
use App\Support\Privacidad;

/** The genealogy site's robots.txt and sitemaps; "l" is the places' sitemap. */
class ArchivosController extends Base
{
    protected function repo(): SitemapRepository
    {
        return new SitemapRepository($this->db(), Privacidad::publica());
    }

    protected function claveUrl(): string
    {
        return 'SITE_URL_GENEALOGY';
    }

    /**
     * @return list<string>
     */
    protected function excluidas(): array
    {
        return ['/0/', '/b/'];
    }

    protected function enlaceDe(string $tipo, string $clave): string
    {
        return $tipo === 'l' ? enlaceLugar(explode('/', $clave)) : parent::enlaceDe($tipo, $clave);
    }
}
