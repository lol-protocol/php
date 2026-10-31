<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\ArchivosController as Base;
use App\Repositories\POS\SitemapRepository;

/** The POS's robots.txt and sitemaps: the catalog only. */
class ArchivosController extends Base
{
    protected function repo(): SitemapRepository
    {
        return new SitemapRepository($this->db());
    }

    protected function claveUrl(): string
    {
        return 'SITE_URL_POS';
    }

    /**
     * @return list<string>
     */
    protected function excluidas(): array
    {
        return ['/0/', '/b/', '/c/', '/p/'];
    }
}
