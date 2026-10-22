<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\SitemapRepository as Base;

/**
 * Every genealogy page a visitor may see. Built on Privacidad like every other
 * query, so a living or private persona, an event or record that would give
 * one away, and a private colección never reach it; nor does a page withdrawn
 * after a pedido de retiro (see RetiroRepository). The caller passes
 * Privacidad::publica(): a sitemap is for crawlers, whoever asks for it.
 */
final class SitemapRepository extends Base
{
    protected function fuentes(): array
    {
        $p = $this->privacidad;
        // A page withdrawn after a pedido de retiro answers 410: it is no page to index.
        $sin = static fn(string $tipo, string $columna): string => ' NOT ' . RetiroRepository::sqlRetirado($tipo, $columna);

        return [
            '10' => 'SELECT q.id AS clave FROM personas q WHERE NOT ' . $p->sqlOculta('q') . ' AND' . $sin('persona', 'q.id'),
            '9' => "SELECT e.id AS clave FROM {$p->sucesos()} e WHERE" . $sin('suceso', 'e.id'),
            '8' => "SELECT g.id AS clave FROM {$p->registros()} g WHERE" . $sin('registro', 'g.id'),
            '7' => 'SELECT c.id AS clave FROM colecciones c WHERE c.publica AND' . $sin('coleccion', 'c.id'),
            '6' => 'SELECT o.id AS clave FROM grupos o WHERE' . $sin('grupo', 'o.id'),
            '5' => 'SELECT o.id AS clave FROM organizaciones o WHERE' . $sin('organizacion', 'o.id'),
            'l' => 'SELECT ruta AS clave FROM lugares',
        ];
    }
}
