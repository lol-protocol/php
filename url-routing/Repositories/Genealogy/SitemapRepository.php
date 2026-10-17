<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\SitemapRepository as Base;

/**
 * Every genealogy page a visitor may see. Built on Privacidad like every other
 * query, so a living or private persona, an event or record that would give
 * one away, and a private colección never reach it. The caller passes
 * Privacidad::publica(): a sitemap is for crawlers, whoever asks for it.
 */
final class SitemapRepository extends Base
{
    protected function fuentes(): array
    {
        $p = $this->privacidad;

        return [
            '10' => 'SELECT q.id AS clave FROM personas q WHERE NOT ' . $p->sqlOculta('q'),
            '9' => "SELECT e.id AS clave FROM {$p->sucesos()} e",
            '8' => "SELECT g.id AS clave FROM {$p->registros()} g",
            '7' => 'SELECT c.id AS clave FROM colecciones c WHERE c.publica',
            '6' => 'SELECT id AS clave FROM grupos',
            '5' => 'SELECT id AS clave FROM organizaciones',
            'l' => 'SELECT ruta AS clave FROM lugares',
        ];
    }
}
