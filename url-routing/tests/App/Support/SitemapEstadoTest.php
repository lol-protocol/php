<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\SitemapEstado;
use PHPUnit\Framework\TestCase;

class SitemapEstadoTest extends TestCase
{
    private string $dir;
    private int $ahora = 1_760_000_000;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sitemap-estado-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function estado(): SitemapEstado
    {
        return new SitemapEstado($this->dir, fn(): int => $this->ahora);
    }

    public function testAFileNeverServedHasNoDate(): void
    {
        $this->assertNull($this->estado()->desde('https://conce.com/sitemap/10/1.xml'));
    }

    public function testTheDateStaysWhileTheListIsTheSame(): void
    {
        $url = 'https://conce.com/sitemap/10/1.xml';
        $this->assertSame($this->ahora, $this->estado()->registrar($url, '<a/>'));

        $primera = $this->ahora;
        $this->ahora += 86400;

        $this->assertSame($primera, $this->estado()->registrar($url, '<a/>'), 'served again, same list: same date');
        $this->assertSame($primera, $this->estado()->desde($url));
    }

    public function testTheDateMovesWhenTheListChanges(): void
    {
        $url = 'https://conce.com/sitemap/10/1.xml';
        $this->estado()->registrar($url, '<a/>');
        $this->ahora += 3600;

        $this->assertSame($this->ahora, $this->estado()->registrar($url, '<b/>'));
        $this->assertSame($this->ahora, $this->estado()->desde($url));
    }

    public function testEachUrlHasItsOwnDate(): void
    {
        $this->estado()->registrar('https://conce.com/sitemap/10/1.xml', '<a/>');
        $this->ahora += 60;
        $this->estado()->registrar('https://staging.conce.com/sitemap/10/1.xml', '<a/>');

        $this->assertSame($this->ahora - 60, $this->estado()->desde('https://conce.com/sitemap/10/1.xml'));
        $this->assertSame($this->ahora, $this->estado()->desde('https://staging.conce.com/sitemap/10/1.xml'), 'another site, even with the same list');
    }

    public function testAFolderThatCannotBeWrittenGivesNoDateRatherThanAFalseOne(): void
    {
        file_put_contents($this->dir, 'a file where the folder should be');
        $estado = new SitemapEstado($this->dir . '/sub', fn(): int => $this->ahora);

        $this->assertNull($estado->registrar('https://conce.com/sitemap.xml', '<a/>'));
        $this->assertNull($estado->desde('https://conce.com/sitemap.xml'));

        unlink($this->dir);
    }

    public function testABrokenStateFileIsTreatedAsNeverServed(): void
    {
        $url = 'https://conce.com/sitemap.xml';
        $this->estado()->registrar($url, '<a/>');
        foreach (glob($this->dir . '/*.json') ?: [] as $archivo) {
            file_put_contents($archivo, '{"huella": 3');
        }
        $this->ahora += 10;

        $this->assertNull($this->estado()->desde($url));
        $this->assertSame($this->ahora, $this->estado()->registrar($url, '<a/>'), 'starts over from now');
    }

    public function testNoTemporaryFileIsLeftBehind(): void
    {
        $this->estado()->registrar('https://conce.com/sitemap.xml', '<a/>');
        $this->estado()->registrar('https://conce.com/sitemap.xml', '<b/>');

        $this->assertCount(1, glob($this->dir . '/*') ?: []);
    }
}
