<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * What a site's sitemap lists, by type: each subclass says which rows of each
 * type a visitor may see (fuentes()), and this pages them out.
 */
abstract class SitemapRepository extends Repository
{
    /** URLs per sitemap file: the protocol's limit. */
    public const POR_PARTE = 50000;

    /**
     * Sitemap file key => SQL whose `clave` column is each page's id. The key
     * is the type's digit width, or a letter for a type without one. A source
     * may also have a `fecha` column: when that page's content last changed
     * (its <lastmod>); without one, or with NULL, the page carries no date.
     *
     * @return array<int|string, string>
     */
    abstract protected function fuentes(): array;

    /** @return list<string> */
    public function tipos(): array
    {
        return array_map('strval', array_keys($this->fuentes()));
    }

    public function cuantas(string $tipo): int
    {
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM (' . $this->fuentes()[$tipo] . ') x');
    }

    /**
     * The pages in sitemap file $parte (1-based) of $tipo: each page's id and, when known, the day its
     * content last changed (YYYY-MM-DD).
     *
     * @return list<array{clave: string, fecha: string|null}>
     */
    public function entradas(string $tipo, int $parte): array
    {
        $filas = $this->db->fetchAll(
            'SELECT * FROM (' . $this->fuentes()[$tipo] . ') x ORDER BY clave'
                . self::page(self::POR_PARTE, ($parte - 1) * self::POR_PARTE)
        );

        return array_map(static fn(array $f): array => [
            'clave' => (string)$f['clave'],
            'fecha' => is_string($f['fecha'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}/', $f['fecha'], $m) === 1 ? $m[0] : null,
        ], $filas);
    }

    /** @return list<string> the page ids in sitemap file $parte (1-based) of $tipo */
    public function claves(string $tipo, int $parte): array
    {
        return array_column($this->entradas($tipo, $parte), 'clave');
    }
}
