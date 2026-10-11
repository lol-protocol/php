<?php

namespace MarcalibrosApp\Tests;

use Marcalibros\Bookmark;
use MarcalibrosApp\BookmarkStore;

final class BookmarkStoreTest extends AppTestCase
{
    private string $bookmarks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookmarks = $this->dir . '/marcalibros';
        mkdir($this->bookmarks, 0755, true);
    }

    private function store(): BookmarkStore
    {
        return new BookmarkStore($this->bookmarks);
    }

    /** Escribe las dos imágenes de un marcalibro y devuelve su objeto. */
    private function bookmark(string $hex = '0123456789abcdef', ?string $label = 'Mi marcalibro'): Bookmark
    {
        $id = "mp-$hex";
        file_put_contents("$this->bookmarks/$id.jpg", 'uno');
        file_put_contents("$this->bookmarks/$id@2x.jpg", 'dos');
        return new Bookmark($id, Bookmark::SOURCE_UPLOAD, $label, ['1x' => "$id.jpg", '2x' => "$id@2x.jpg"], 1400, 200);
    }

    public function testSaveAndFind(): void
    {
        $store = $this->store();
        $store->save($this->bookmark(), $this->now);
        $found = $store->find('mp-0123456789abcdef');
        $this->assertNotNull($found);
        $this->assertSame('Mi marcalibro', $found['label']);
        $this->assertSame($this->now, $found['created']);
        $this->assertSame(['1x' => 'mp-0123456789abcdef.jpg', '2x' => 'mp-0123456789abcdef@2x.jpg'], $found['files']);
        $this->assertSame(1, $store->count());
    }

    public function testLabelCanBeNull(): void
    {
        $store = $this->store();
        $store->save($this->bookmark(label: null), $this->now);
        $found = $store->find('mp-0123456789abcdef');
        $this->assertNotNull($found);
        $this->assertSame(null, $found['label']);
    }

    /** @return array<string, array{string}> */
    public static function badIds(): array
    {
        return [
            'recorrido' => ['../etc/passwd'],
            'con barra' => ['mp-0123456789abcde/'],
            'mayúsculas' => ['mp-0123456789ABCDEF'],
            'corto' => ['mp-0123'],
            'largo' => ['mp-0123456789abcdef0'],
            'otro prefijo' => ['xx-0123456789abcdef'],
            'vacío' => [''],
            'salto de línea' => ["mp-0123456789abcdef\n"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badIds')]
    public function testInvalidIdsNeverReachTheDisk(string $id): void
    {
        $store = $this->store();
        $this->assertFalse(BookmarkStore::isValidId($id));
        $this->assertSame(null, $store->find($id));
        $this->assertSame(null, $store->imagePath($id, '1x'));
        $this->assertFalse($store->delete($id));
    }

    public function testFindFailsWhenAnImageIsMissing(): void
    {
        $store = $this->store();
        $store->save($this->bookmark(), $this->now);
        unlink("$this->bookmarks/mp-0123456789abcdef@2x.jpg");
        $this->assertSame(null, $store->find('mp-0123456789abcdef'));
    }

    public function testFindIgnoresCorruptOrMismatchedMetadata(): void
    {
        $store = $this->store();
        $this->bookmark();
        file_put_contents("$this->bookmarks/mp-0123456789abcdef.json", '{no es json');
        $this->assertSame(null, $store->find('mp-0123456789abcdef'));

        file_put_contents("$this->bookmarks/mp-0123456789abcdef.json", json_encode(['id' => 'mp-ffffffffffffffff', 'created' => 1, 'files' => ['1x' => 'mp-0123456789abcdef.jpg', '2x' => 'mp-0123456789abcdef@2x.jpg']]));
        $this->assertSame(null, $store->find('mp-0123456789abcdef'));

        // Los nombres de archivo guardados nunca se usan tal cual: deben coincidir con el identificador.
        file_put_contents("$this->bookmarks/mp-0123456789abcdef.json", json_encode(['id' => 'mp-0123456789abcdef', 'created' => 1, 'files' => ['1x' => '../../secreto.jpg', '2x' => 'mp-0123456789abcdef@2x.jpg']]));
        $this->assertSame(null, $store->find('mp-0123456789abcdef'));
    }

    public function testImagePathOnlyForKnownSizes(): void
    {
        $store = $this->store();
        $store->save($this->bookmark(), $this->now);
        $this->assertSame("$this->bookmarks/mp-0123456789abcdef.jpg", $store->imagePath('mp-0123456789abcdef', '1x'));
        $this->assertSame("$this->bookmarks/mp-0123456789abcdef@2x.jpg", $store->imagePath('mp-0123456789abcdef', '2x'));
        $this->assertSame(null, $store->imagePath('mp-0123456789abcdef', '3x'));
        $this->assertSame(null, $store->imagePath('mp-0123456789abcdef', '../x'));
    }

    public function testDeleteRemovesAllFiles(): void
    {
        $store = $this->store();
        $store->save($this->bookmark(), $this->now);
        $this->assertTrue($store->delete('mp-0123456789abcdef'));
        $this->assertSame([], array_values(array_diff(scandir($this->bookmarks) ?: [], ['.', '..'])));
        $this->assertFalse($store->delete('mp-0123456789abcdef'));
    }

    public function testSaveFailureLeavesNoImagesBehind(): void
    {
        $store = new BookmarkStore($this->bookmarks);
        $bookmark = $this->bookmark();
        // Una carpeta en el lugar del JSON hace fallar el rename, también como root.
        mkdir("$this->bookmarks/mp-0123456789abcdef.json");
        try {
            $store->save($bookmark, $this->now);
            $this->fail('Se esperaba un error al guardar.');
        } catch (\RuntimeException) {
        }
        rmdir("$this->bookmarks/mp-0123456789abcdef.json");
        $this->assertSame([], array_values(array_diff(scandir($this->bookmarks) ?: [], ['.', '..'])));
    }

    public function testPurgeDeletesOnlyExpiredBookmarks(): void
    {
        $store = $this->store();
        $store->save($this->bookmark('aaaaaaaaaaaaaaaa'), $this->now - 31 * 86400);
        $store->save($this->bookmark('bbbbbbbbbbbbbbbb'), $this->now - 29 * 86400);
        $store->save($this->bookmark('cccccccccccccccc'), $this->now);

        $this->assertSame(1, $store->purgeOlderThan(30 * 86400, $this->now));
        $this->assertSame(null, $store->find('mp-aaaaaaaaaaaaaaaa'));
        $this->assertNotNull($store->find('mp-bbbbbbbbbbbbbbbb'));
        $this->assertNotNull($store->find('mp-cccccccccccccccc'));
        $this->assertFileExists("$this->bookmarks/mp-bbbbbbbbbbbbbbbb@2x.jpg");
        $this->assertFalse(file_exists("$this->bookmarks/mp-aaaaaaaaaaaaaaaa.jpg"));
    }

    public function testPurgeTreatsUnreadableMetadataAsExpired(): void
    {
        $store = $this->store();
        $this->bookmark();
        file_put_contents("$this->bookmarks/mp-0123456789abcdef.json", 'basura');
        $this->assertSame(1, $store->purgeOlderThan(86400, $this->now));
        $this->assertSame([], array_values(array_diff(scandir($this->bookmarks) ?: [], ['.', '..'])));
    }

    public function testPurgeCleansOrphansButKeepsRecentOnes(): void
    {
        $store = $this->store();
        file_put_contents("$this->bookmarks/mp-aaaaaaaaaaaaaaaa.jpg", 'x');
        file_put_contents("$this->bookmarks/mp-aaaaaaaaaaaaaaaa@2x.jpg", 'x');
        file_put_contents("$this->bookmarks/mp-bbbbbbbbbbbbbbbb.jpg", 'x');
        file_put_contents("$this->bookmarks/.tmp-viejo", 'x');
        file_put_contents("$this->bookmarks/.tmp-nuevo", 'x');
        file_put_contents("$this->bookmarks/otro-archivo.txt", 'x');
        touch("$this->bookmarks/mp-aaaaaaaaaaaaaaaa.jpg", $this->now - 7200);
        touch("$this->bookmarks/mp-aaaaaaaaaaaaaaaa@2x.jpg", $this->now - 7200);
        touch("$this->bookmarks/mp-bbbbbbbbbbbbbbbb.jpg", $this->now - 60);
        touch("$this->bookmarks/.tmp-viejo", $this->now - 7200);
        touch("$this->bookmarks/.tmp-nuevo", $this->now - 60);

        $store->purgeOlderThan(30 * 86400, $this->now);
        $left = array_values(array_diff(scandir($this->bookmarks) ?: [], ['.', '..']));
        sort($left);
        $this->assertSame(['.tmp-nuevo', 'mp-bbbbbbbbbbbbbbbb.jpg', 'otro-archivo.txt'], $left);
    }
}
