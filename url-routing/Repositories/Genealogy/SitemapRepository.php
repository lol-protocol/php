<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * What the sitemap lists: every page a visitor may see, by type. Built on
 * Privacidad like every other query, so a living or private persona, an
 * event or record that would give one away, and a private colección never
 * reach it. The caller passes Privacidad::publica(): a sitemap is for
 * crawlers, whoever asks for it.
 */
final class SitemapRepository extends Repository
{
    /** URLs per sitemap file: the protocol's limit. */
    public const POR_PARTE = 50000;

    /** Sitemap file key => its type's page id: the digit width for an id type, "l" for places. */
    private function fuentes(): array
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

    /** @return list<string> */
    public function tipos(): array
    {
        return array_map('strval', array_keys($this->fuentes()));
    }

    public function cuantas(string $tipo): int
    {
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM (' . $this->fuentes()[$tipo] . ') x');
    }

    /** @return list<string> the page ids in sitemap file $parte (1-based) of $tipo */
    public function claves(string $tipo, int $parte): array
    {
        $filas = $this->db->fetchAll(
            'SELECT clave FROM (' . $this->fuentes()[$tipo] . ') x ORDER BY clave'
                . self::page(self::POR_PARTE, ($parte - 1) * self::POR_PARTE)
        );

        return array_map(static fn(array $f): string => (string)$f['clave'], $filas);
    }
}
