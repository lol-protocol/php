<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\Backup;
use MarcalibrosApp\BookmarkStore;
use PHPUnit\Framework\TestCase;

final class BackupTest extends TestCase
{
    private string $dir;
    private string $source;
    private string $target;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marcalibros-backup-test-' . bin2hex(random_bytes(6));
        $this->source = "$this->dir/marcalibros";
        $this->target = "$this->dir/copias";
        mkdir($this->source, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->dir);
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $item) {
                if ($item !== '.' && $item !== '..') {
                    $this->remove("$path/$item");
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }

    private function bookmark(string $hex, bool $meta = true, bool $second = true): string
    {
        $id = "mp-$hex";
        file_put_contents("$this->source/$id.jpg", "uno-$hex");
        if ($second) {
            file_put_contents("$this->source/$id@2x.jpg", "dos-$hex");
        }
        if ($meta) {
            file_put_contents("$this->source/$id.json", json_encode(['id' => $id, 'created' => 1_800_000_000]));
        }
        return $id;
    }

    /** @return list<string> */
    private function listArchive(string $file): array
    {
        $names = [];
        $tar = new \PharData($file);
        foreach (new \RecursiveIteratorIterator($tar) as $entry) {
            $names[] = substr((string) $entry->getPathname(), strlen('phar://' . $file . '/'));
        }
        sort($names);
        return $names;
    }

    public function testCopiesEveryCompleteBookmarkAndNothingElse(): void
    {
        $a = $this->bookmark('0123456789abcdef');
        $b = $this->bookmark('fedcba9876543210');
        $this->bookmark('1111111111111111', meta: false);          // imagen suelta: sin datos
        $this->bookmark('2222222222222222', second: false);        // le falta la imagen grande
        file_put_contents("$this->source/.tmp-123", 'a medias');
        file_put_contents("$this->source/otra-cosa.txt", 'no es un marcalibro');

        $result = (new Backup($this->source, $this->target, 7))->create(1_800_000_000);

        $this->assertNotNull($result);
        $this->assertSame(2, $result['bookmarks']);
        $this->assertSame("$this->target/marcalibros-20270115-080000.tar.gz", $result['file']);
        $this->assertSame(
            ["marcalibros/$a.jpg", "marcalibros/$a.json", "marcalibros/$a@2x.jpg", "marcalibros/$b.jpg", "marcalibros/$b.json", "marcalibros/$b@2x.jpg"],
            $this->listArchive($result['file'])
        );
        $this->assertSame([], array_values(array_filter(scandir($this->target) ?: [], fn ($f) => str_starts_with($f, '.tmp-'))), 'No debe quedar ningún temporal.');
    }

    public function testTheArchiveRestoresTheOriginalContent(): void
    {
        $id = $this->bookmark('0123456789abcdef');
        $result = (new Backup($this->source, $this->target, 7))->create(1_800_000_000);
        $this->assertNotNull($result);

        $out = "$this->dir/restaurado";
        mkdir($out);
        (new \PharData($result['file']))->extractTo($out);
        $this->assertSame('uno-0123456789abcdef', file_get_contents("$out/marcalibros/$id.jpg"));
        $this->assertSame('dos-0123456789abcdef', file_get_contents("$out/marcalibros/$id@2x.jpg"));
        $this->assertTrue(BookmarkStore::isValidId($id));
    }

    public function testTheArchiveIsPrivate(): void
    {
        $this->bookmark('0123456789abcdef');
        $result = (new Backup($this->source, $this->target, 7))->create(1_800_000_000);
        $this->assertNotNull($result);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($result['file'])), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->target)), -4));
    }

    public function testDoesNotWriteAnEmptyBackup(): void
    {
        $this->assertNull((new Backup($this->source, $this->target, 7))->create(1_800_000_000));
        $this->bookmark('1111111111111111', meta: false);
        $this->assertNull((new Backup($this->source, $this->target, 7))->create(1_800_000_000));
        $this->assertFalse(is_dir($this->target), 'Sin nada que copiar no se crea ni la carpeta.');
    }

    public function testKeepsOnlyTheLatestCopiesAndNeverTouchesOtherFiles(): void
    {
        $this->bookmark('0123456789abcdef');
        $backup = new Backup($this->source, $this->target, 3);
        for ($day = 0; $day < 5; $day++) {
            $backup->create(1_800_000_000 + $day * 86400);
        }
        file_put_contents("$this->target/notas.txt", 'mías');
        file_put_contents("$this->target/marcalibros-20270101-000000.tar", 'sin .gz: no es una copia');

        $this->assertSame(2, $backup->rotate());
        $this->assertSame(
            ["$this->target/marcalibros-20270117-080000.tar.gz", "$this->target/marcalibros-20270118-080000.tar.gz", "$this->target/marcalibros-20270119-080000.tar.gz"],
            $backup->copies()
        );
        $this->assertFileExists("$this->target/notas.txt");
        $this->assertFileExists("$this->target/marcalibros-20270101-000000.tar");
        $this->assertSame(0, $backup->rotate(), 'Rotar de nuevo no borra nada.');
    }

    public function testRejectsKeepingNoCopies(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Backup($this->source, $this->target, 0);
    }
}
