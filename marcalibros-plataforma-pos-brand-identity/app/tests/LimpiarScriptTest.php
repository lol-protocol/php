<?php

namespace MarcalibrosApp\Tests;

use PHPUnit\Framework\TestCase;

/** bin/limpiar.php, que corre el cron todos los días: sin plazo de conservación no borra marcalibros, pero sí los restos. */
final class LimpiarScriptTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir() . '/marcalibros-limpiar-' . bin2hex(random_bytes(6));
        mkdir("$this->storage/marcalibros", 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob("$this->storage/marcalibros/{,.}*", GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        @rmdir("$this->storage/marcalibros");
        foreach (glob("$this->storage/{,.}*", GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        @rmdir("$this->storage/intentos");
        @rmdir($this->storage);
    }

    /** @return array{int, string} */
    private function runCleanup(string $retentionDays): array
    {
        $command = sprintf(
            'MARCALIBROS_STORAGE=%s MARCALIBROS_RETENTION_DAYS=%s %s %s 2>&1',
            escapeshellarg($this->storage),
            escapeshellarg($retentionDays),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__) . '/bin/limpiar.php')
        );
        exec($command, $lines, $code);
        return [$code, implode("\n", $lines)];
    }

    private function seed(): void
    {
        $dir = "$this->storage/marcalibros";
        $id = 'mp-aaaaaaaaaaaaaaaa';
        file_put_contents("$dir/$id.jpg", 'x');
        file_put_contents("$dir/$id@2x.jpg", 'x');
        file_put_contents("$dir/$id.json", json_encode([
            'id' => $id, 'source' => 'upload', 'label' => null,
            'files' => ['1x' => "$id.jpg", '2x' => "$id@2x.jpg"], 'width' => 1400, 'height' => 200, 'created' => 1_000_000_000,
        ]));
        file_put_contents("$dir/mp-bbbbbbbbbbbbbbbb.jpg", 'x');
        file_put_contents("$dir/.tmp-viejo", 'x');
        touch("$dir/mp-bbbbbbbbbbbbbbbb.jpg", time() - 7200);
        touch("$dir/.tmp-viejo", time() - 7200);
    }

    public function testWithoutRetentionItKeepsBookmarksButCleansLeftovers(): void
    {
        $this->seed();
        [$code, $output] = $this->runCleanup('0');
        $this->assertSame(0, $code, $output);
        $this->assertFileExists("$this->storage/marcalibros/mp-aaaaaaaaaaaaaaaa.json");
        $this->assertFileDoesNotExist("$this->storage/marcalibros/mp-bbbbbbbbbbbbbbbb.jpg");
        $this->assertFileDoesNotExist("$this->storage/marcalibros/.tmp-viejo");
    }

    public function testWithRetentionItAlsoDeletesExpiredBookmarks(): void
    {
        $this->seed();
        [$code, $output] = $this->runCleanup('30');
        $this->assertSame(0, $code, $output);
        $this->assertFileDoesNotExist("$this->storage/marcalibros/mp-aaaaaaaaaaaaaaaa.json");
        $this->assertStringContainsString('Marcalibros borrados: 1', $output);
    }

    public function testNegativeRetentionIsRejected(): void
    {
        [$code] = $this->runCleanup('-1');
        $this->assertSame(2, $code);
    }
}
