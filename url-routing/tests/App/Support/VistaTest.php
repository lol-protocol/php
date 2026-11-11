<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Vista;
use PHPUnit\Framework\TestCase;

class VistaTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vista-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function vista(string $contenido): string
    {
        $archivo = $this->dir . '/v' . md5($contenido) . '.php';
        file_put_contents($archivo, $contenido);

        return $archivo;
    }

    public function testDrawsTheViewWithItsDataInScope(): void
    {
        $archivo = $this->vista('<?= $saludo ?>, <?= $quien ?>');

        $this->assertSame('hola, mundo', Vista::dibujar($archivo, ['saludo' => 'hola', 'quien' => 'mundo']));
    }

    public function testADataKeyCannotReplaceTheFileThatIsIncluded(): void
    {
        $otra = $this->vista('otra vista');
        $archivo = $this->vista('la vista de verdad');

        $this->assertSame('la vista de verdad', Vista::dibujar($archivo, ['archivo' => $otra, 'datos' => []]));
    }

    public function testAFailingViewThrowsAndLeavesNoBufferOpen(): void
    {
        // The layout pattern: the view opens a buffer of its own and fails before it closes it.
        $archivo = $this->vista('<?php ob_start(); echo "a medias"; throw new \RuntimeException("falló");');
        $antes = ob_get_level();

        try {
            Vista::dibujar($archivo, []);
            $this->fail('the exception must reach the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('falló', $e->getMessage());
        }

        $this->assertSame($antes, ob_get_level(), 'every buffer it opened was closed');
    }
}
