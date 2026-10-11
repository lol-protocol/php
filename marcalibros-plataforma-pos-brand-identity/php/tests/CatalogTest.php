<?php

namespace Tests;

use Marcalibros\Catalog;
use Marcalibros\RejectionReason;

/** Se prueba contra el catálogo real del proyecto, así también se comprueba que el manifiesto y los archivos coinciden. */
final class CatalogTest extends ImageTestCase
{
    private string $root;
    private Catalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
        $this->catalog = new Catalog("$this->root/catalog/manifest.json");
    }

    public function testLoadsEveryBookmarkOfTheManifest(): void
    {
        $manifest = json_decode((string) file_get_contents("$this->root/catalog/manifest.json"), true);

        $this->assertGreaterThan(0, $this->catalog->count());
        $this->assertSame($manifest['total'], $this->catalog->count());
        $this->assertCount($this->catalog->count(), glob("$this->root/catalog/svg/*.svg") ?: []);
    }

    public function testEveryEntryPointsToFilesThatExist(): void
    {
        foreach ($this->catalog->ids() as $id) {
            $entry = $this->catalog->get($id);

            $this->assertFileExists("$this->root/{$entry['svg']}", $id);
            $this->assertFileExists("$this->root/{$entry['vista_previa']}", $id);
        }
    }

    public function testFindsAKnownBookmark(): void
    {
        $id = $this->catalog->ids()[0];

        $this->assertTrue($this->catalog->has($id));
        $this->assertSame($id, $this->catalog->get($id)['id']);
    }

    public function testRejectsAnUnknownId(): void
    {
        $this->assertFalse($this->catalog->has('mb-no-existe-01'));
        $this->assertRejected(RejectionReason::UnknownCatalogId, fn () => $this->catalog->get('mb-no-existe-01'));
    }

    public function testNeverTreatsTheIdAsAPath(): void
    {
        foreach (['../../etc/passwd', '../catalog/manifest.json', '', "mb-gotico-01\0"] as $id) {
            $this->assertFalse($this->catalog->has($id));
            $this->assertRejected(RejectionReason::UnknownCatalogId, fn () => $this->catalog->get($id));
        }
    }

    public function testFiltersByCategory(): void
    {
        $gothic = $this->catalog->byCategory('gotico');

        $this->assertNotEmpty($gothic);
        foreach ($gothic as $entry) {
            $this->assertSame('Gótico', $entry['categoria']);
            $this->assertSame('movimiento literario', $entry['familia']);
        }
        $this->assertSame([], $this->catalog->byCategory('no-existe'));
    }

    public function testKeepsMagicalRealismAmongTheCategories(): void
    {
        $this->assertTrue($this->catalog->hasCategory('realismo-magico'));
        $this->assertNotEmpty($this->catalog->byCategory('realismo-magico'));
    }

    public function testHasBothFamiliesOfCategories(): void
    {
        $this->assertNotEmpty($this->catalog->byFamily('movimiento literario'));
        $this->assertNotEmpty($this->catalog->byFamily('tipo de texto'));
        $this->assertSame($this->catalog->count(), count($this->catalog->byFamily('movimiento literario')) + count($this->catalog->byFamily('tipo de texto')));
        foreach (['manuales', 'libros-sagrados', 'leyes'] as $slug) {
            $this->assertTrue($this->catalog->hasCategory($slug), $slug);
        }
    }

    public function testEveryEntryBelongsToAListedCategoryAndTotalsMatch(): void
    {
        $counts = [];
        foreach ($this->catalog->all() as $entry) {
            $this->assertTrue($this->catalog->hasCategory($entry['categoria_slug']), $entry['id']);
            $counts[$entry['categoria_slug']] = ($counts[$entry['categoria_slug']] ?? 0) + 1;
        }
        foreach ($this->catalog->categories() as $category) {
            $this->assertSame($category['total'], $counts[$category['slug']] ?? 0, $category['slug']);
        }
    }

    public function testEveryMotifHasADisplayNameWithAccents(): void
    {
        foreach ($this->catalog->all() as $entry) {
            $name = $this->catalog->motifName($entry['motivo']);
            $this->assertNotSame('', $name, $entry['id']);
            $this->assertStringNotContainsString('_', $name, $entry['id']);
        }
        $this->assertSame('Árbol solitario', $this->catalog->motifName('arbol_solitario'));
        $this->assertSame('Motivo nuevo', $this->catalog->motifName('motivo_nuevo'));
    }

    public function testOnlyLiteraryMovementsListInspirationAuthors(): void
    {
        foreach ($this->catalog->all() as $entry) {
            if ($entry['familia'] === 'movimiento literario') {
                $this->assertNotEmpty($entry['autores_inspiracion'], $entry['id']);
            } else {
                $this->assertArrayNotHasKey('autores_inspiracion', $entry, $entry['id']);
            }
        }
    }

    public function testFailsClearlyOnABrokenManifest(): void
    {
        $duplicated = $this->put('repetido.json', json_encode(['marcalibros' => [['id' => 'a'], ['id' => 'a']]]));
        $withoutList = $this->put('sin-lista.json', '{"otra":1}');
        $withoutId = $this->put('sin-id.json', json_encode(['marcalibros' => [['nombre' => 'x']]]));
        $categoryWithoutSlug = $this->put('categoria-sin-slug.json', json_encode(['marcalibros' => [['id' => 'a']], 'categorias' => [['nombre' => 'x']]]));

        foreach ([$duplicated, $withoutList, $withoutId, $categoryWithoutSlug, "$this->dir/no-existe.json"] as $path) {
            $message = null;
            try {
                new Catalog($path);
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
            }
            // La comprobación va fuera del try: fail() lanza una RuntimeException y se confundiría con el error buscado.
            $this->assertNotNull($message, "Debía fallar con $path");
            $this->assertNotSame('', $message);
        }

        $this->expectException(\JsonException::class);
        new Catalog($this->put('roto.json', '{no es json'));
    }
}
