<?php

namespace Tests;

use Marcalibros\AtomicFile;
use PHPUnit\Framework\TestCase;

final class AtomicFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/atomic-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        rmdir($this->dir);
    }

    public function testWritesTheBytesWithTheGivenMode(): void
    {
        $this->assertTrue(AtomicFile::write("$this->dir/a.bin", 'datos', 0600));
        $this->assertSame('datos', file_get_contents("$this->dir/a.bin"));
        $this->assertSame('600', substr(sprintf('%o', fileperms("$this->dir/a.bin")), -3));
    }

    public function testReplacesAnExistingFileAndLeavesNoTemporary(): void
    {
        file_put_contents("$this->dir/a.bin", 'viejo');
        $this->assertTrue(AtomicFile::write("$this->dir/a.bin", 'nuevo'));
        $this->assertSame('nuevo', file_get_contents("$this->dir/a.bin"));
        $this->assertSame([], glob("$this->dir/.tmp-*") ?: []);
    }

    public function testFailsWithoutLeavingATemporaryWhenTheTargetCannotBeReplaced(): void
    {
        mkdir("$this->dir/es-carpeta");
        $this->assertFalse(@AtomicFile::write("$this->dir/es-carpeta", 'x'));
        $this->assertSame([], glob("$this->dir/.tmp-*") ?: []);
        rmdir("$this->dir/es-carpeta");
    }

    public function testFailsWhenTheFolderDoesNotExist(): void
    {
        $this->assertFalse(@AtomicFile::write("$this->dir/no-existe/a.bin", 'x'));
    }
}
