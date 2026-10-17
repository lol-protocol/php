<?php

namespace Marcalibros;

/**
 * Los marcalibros predefinidos, leídos de catalog/manifest.json.
 *
 * Solo se puede elegir un identificador que esté en el manifiesto: el
 * identificador del usuario nunca se usa para armar rutas de archivos.
 */
final class Catalog
{
    /** @var array<string, array<string, mixed>> */
    private array $entries = [];

    /** @var array<string, array<string, mixed>> */
    private array $categories = [];

    /** @var array<string, string> */
    private array $motifNames = [];

    public function __construct(string $manifestPath)
    {
        $json = @file_get_contents($manifestPath);
        if ($json === false) {
            throw new \RuntimeException("No se pudo leer el manifiesto: $manifestPath");
        }

        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !isset($manifest['marcalibros']) || !is_array($manifest['marcalibros'])) {
            throw new \RuntimeException('El manifiesto no tiene la lista "marcalibros".');
        }

        foreach ($manifest['marcalibros'] as $entry) {
            if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id'])) {
                throw new \RuntimeException('Hay una entrada del manifiesto sin "id".');
            }
            if (isset($this->entries[$entry['id']])) {
                throw new \RuntimeException('El identificador está repetido en el manifiesto: ' . $entry['id']);
            }
            $this->entries[$entry['id']] = $entry;
        }

        foreach ($manifest['motivos'] ?? [] as $id => $name) {
            if (is_string($name)) {
                $this->motifNames[(string) $id] = $name;
            }
        }

        foreach ($manifest['categorias'] ?? [] as $category) {
            if (!is_array($category) || !isset($category['slug']) || !is_string($category['slug'])) {
                throw new \RuntimeException('Hay una categoría del manifiesto sin "slug".');
            }
            $this->categories[$category['slug']] = $category;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * @return array<string, mixed>
     * @throws BookmarkRejectedException si el identificador no existe
     */
    public function get(string $id): array
    {
        if (!isset($this->entries[$id])) {
            throw new BookmarkRejectedException(RejectionReason::UnknownCatalogId);
        }
        return $this->entries[$id];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_map('strval', array_keys($this->entries));
    }

    /**
     * Las categorías del catálogo (movimientos literarios y tipos de texto), en el orden del manifiesto.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return array_values($this->categories);
    }

    /**
     * Todos los motivos con su nombre para mostrar, en el orden del manifiesto.
     *
     * @return array<string, string>
     */
    public function motifs(): array
    {
        return $this->motifNames;
    }

    public function hasMotif(string $motif): bool
    {
        return isset($this->motifNames[$motif]);
    }

    /**
     * Los marcalibros que cumplen a la vez los filtros dados; un filtro en null no se aplica.
     *
     * @param string|null $color Hex de la paleta ("#C98B3A"): el marcalibro lo usa de fondo o de acento.
     * @return list<array<string, mixed>>
     */
    public function search(?string $category = null, ?string $motif = null, ?string $color = null): array
    {
        $color = $color === null ? null : strtoupper($color);
        return array_values(array_filter(
            $this->entries,
            static function (array $entry) use ($category, $motif, $color): bool {
                if ($category !== null && ($entry['categoria_slug'] ?? null) !== $category) {
                    return false;
                }
                if ($motif !== null && ($entry['motivo'] ?? null) !== $motif) {
                    return false;
                }
                if ($color === null) {
                    return true;
                }
                $used = array_merge([(string) ($entry['fondo'] ?? '')], array_map('strval', (array) ($entry['colores'] ?? [])));
                return in_array($color, array_map('strtoupper', $used), true);
            }
        ));
    }

    /** Nombre de un motivo para mostrarlo ("arbol_solitario" da "Árbol solitario"). */
    public function motifName(string $motif): string
    {
        return $this->motifNames[$motif] ?? ucfirst(str_replace('_', ' ', $motif));
    }

    public function hasCategory(string $slug): bool
    {
        return isset($this->categories[$slug]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byCategory(string $slug): array
    {
        return $this->filter('categoria_slug', $slug);
    }

    /**
     * @param string $family "movimiento literario" o "tipo de texto"
     * @return list<array<string, mixed>>
     */
    public function byFamily(string $family): array
    {
        return $this->filter('familia', $family);
    }

    /**
     * Todos los marcalibros, en el orden del manifiesto.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->entries);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filter(string $field, string $value): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => ($entry[$field] ?? null) === $value
        ));
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
