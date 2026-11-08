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
            // A persona page also shows her events: when one of the visible ones changes, so did the page.
            '10' => 'SELECT t.id AS clave, CASE WHEN t.de_sucesos > t.propia OR t.propia IS NULL THEN t.de_sucesos ELSE t.propia END AS fecha FROM ('
                . "SELECT q.id, q.actualizado_en AS propia, (SELECT MAX(e.actualizado_en) FROM suceso_participantes sp JOIN {$p->sucesos()} e ON e.id = sp.suceso_id WHERE sp.persona_id = q.id) AS de_sucesos"
                . ' FROM personas q WHERE NOT ' . $p->sqlOculta('q') . ' AND' . $sin('persona', 'q.id') . ') t',
            '9' => "SELECT e.id AS clave, e.actualizado_en AS fecha FROM {$p->sucesos()} e WHERE" . $sin('suceso', 'e.id'),
            '8' => "SELECT g.id AS clave, g.actualizado_en AS fecha FROM {$p->registros()} g WHERE" . $sin('registro', 'g.id'),
            '7' => 'SELECT c.id AS clave, c.actualizado_en AS fecha FROM colecciones c WHERE c.publica AND' . $sin('coleccion', 'c.id'),
            '6' => 'SELECT o.id AS clave, o.actualizado_en AS fecha FROM grupos o WHERE' . $sin('grupo', 'o.id'),
            '5' => 'SELECT o.id AS clave, o.actualizado_en AS fecha FROM organizaciones o WHERE' . $sin('organizacion', 'o.id'),
            'l' => 'SELECT ruta AS clave FROM lugares',
        ];
    }
}
