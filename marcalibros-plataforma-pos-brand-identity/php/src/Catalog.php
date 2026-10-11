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
     * @return list<array<string, mixed>>
     */
    public function byMovement(string $movement): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => ($entry['movimiento'] ?? null) === $movement
        ));
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
