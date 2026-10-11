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

    public function testFiltersByMovement(): void
    {
        $gothic = $this->catalog->byMovement('Gótico');

        $this->assertNotEmpty($gothic);
        foreach ($gothic as $entry) {
            $this->assertSame('Gótico', $entry['movimiento']);
        }
        $this->assertSame([], $this->catalog->byMovement('No existe'));
    }

    public function testEveryEntryListsItsInspirationAuthorsOnlyAsMetadata(): void
    {
        foreach ($this->catalog->ids() as $id) {
            $this->assertNotEmpty($this->catalog->get($id)['autores_inspiracion'], $id);
        }
    }

    public function testFailsClearlyOnABrokenManifest(): void
    {
        $duplicated = $this->put('repetido.json', json_encode(['marcalibros' => [['id' => 'a'], ['id' => 'a']]]));
        $withoutList = $this->put('sin-lista.json', '{"otra":1}');
        $withoutId = $this->put('sin-id.json', json_encode(['marcalibros' => [['nombre' => 'x']]]));

        foreach ([$duplicated, $withoutList, $withoutId, "$this->dir/no-existe.json"] as $path) {
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
