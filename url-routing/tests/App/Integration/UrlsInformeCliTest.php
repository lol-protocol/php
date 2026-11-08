<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\RegistroDeUrls;
use PHPUnit\Framework\TestCase;
use Tests\Support\ComandoDePrueba;

/** bin/urls-informe.php as the owner runs it: a subprocess reading the day files of a folder. */
class UrlsInformeCliTest extends TestCase
{
    use ComandoDePrueba;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/urls-informe-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    /** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr */
    private function correr(string ...$args): array
    {
        return $this->ejecutarComando('urls-informe', $args, ['URL_LOG_DIR' => $this->dir]);
    }

    public function testWithoutAnythingRecordedItSaysSo(): void
    {
        [$codigo, $salida] = $this->correr();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('Sin 404 ni 301 en los últimos 7 días', $salida);
    }

    public function testItListsEachKindMostFrequentFirst(): void
    {
        $registro = new RegistroDeUrls($this->dir);
        foreach (['/6128473991/', '/6128473992/', '/6128473993/'] as $uri) {
            $registro->registrar(404, $uri);
        }
        $registro->registrar(404, '/wp-login.php');
        $registro->registrar(301, '/cart/', '/c/', ['cart', 'c']);

        [$codigo, $salida] = $this->correr('--dias=3');

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('404: no existe', $salida);
        $this->assertStringContainsString('últimos 3 días: 4', $salida);
        $this->assertMatchesRegularExpression('#\s3  /\#10/\n\s+1  /\*\.php#', $salida, 'most frequent first');
        $this->assertStringContainsString('301: se redirigió', $salida);
        $this->assertStringContainsString('/cart/  →  /c/', $salida);
    }

    public function testMaximoCutsTheListAndSaysHowManyAreLeft(): void
    {
        $registro = new RegistroDeUrls($this->dir);
        foreach (['/a1/', '/b2/', '/c3/', '/d4/'] as $uri) {
            $registro->registrar(404, $uri . 'x');
        }
        $registro->registrar(404, '/wp-login.php');
        $registro->registrar(404, '/6128473991/');

        [, $salida] = $this->correr('--maximo=1');

        $this->assertMatchesRegularExpression('/… y \d+ formas más/', $salida);
    }

    public function testBadUsageExitsWithTwo(): void
    {
        foreach ([['--dias=0'], ['--dias=abc'], ['--maximo=0'], ['otra']] as $args) {
            [$codigo, , $error] = $this->correr(...$args);

            $this->assertSame(2, $codigo, implode(' ', $args));
            $this->assertStringContainsString('Uso:', $error);
        }
    }
}
