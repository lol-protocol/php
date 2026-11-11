<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\RegistroDeUrls;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistroDeUrlsTest extends TestCase
{
    private const CONOCIDAS = ['robots.txt', 'sitemap.xml', 'sitemap', 'b', 'c', 'p', 'r', '0', 'cart', 'checkout', 'shipping'];

    private string $dir;
    private int $ahora = 1_761_900_000; // 2025-10-31

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/registro-urls-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function registro(): RegistroDeUrls
    {
        return new RegistroDeUrls($this->dir, fn(): int => $this->ahora);
    }

    /** @return array<string, array{string, string}> */
    public static function formas(): array
    {
        return [
            'inicio' => ['/', '/'],
            'una persona' => ['/6128473105/', '/#10/'],
            'una acción' => ['/6128473105/9/', '/#10/#1/'],
            'una cuenta' => ['/0/3/', '/0/#1/'],
            'un lugar' => ['/mx/jal/gdl/1/', '/aa/aaa/aaa/#1/'],
            'una búsqueda con un nombre' => ['/b/10/maria/', '/b/#2/a4+/'],
            'una búsqueda con tilde y espacio' => ['/b/10/mar%c3%ada%20garc%c3%ada/', '/b/#2/?/'],
            'un apellido con guion' => ['/maria-garcia/', '/?/'],
            'un nombre solo' => ['/wikipedia/', '/a4+/'],
            'un archivo que se escanea' => ['/wp-login.php', '/*.php'],
            'un archivo sin guion' => ['/shell.php', '/*.php'],
            'un dotfile que se escanea' => ['/.env', '/*.env'],
            'un apellido con punto no es una extensión' => ['/juan.perez/', '/?/'],
            'iniciales con punto' => ['/p.e.', '/?'],
            'un apellido que coincide con una extensión' => ['/maria.log', '/*.log'],
            'una palabra que el sitio enruta' => ['/cart/', '/cart/'],
            'un archivo que el sitio sirve' => ['/robots.txt', '/robots.txt'],
            'la query no cuenta' => ['/6128473105/?q=maria+garcia', '/#10/'],
            'mayúsculas' => ['/MX/JAL/', '/aa/aaa/'],
            'sin la barra final' => ['/6128473105', '/#10'],
            'dobles barras' => ['//6128473105//', '/#10/'],
            'la carga de un ataque' => ['/..%2f..%2fetc%2fpasswd', '/?'],
            'demasiados segmentos' => ['/1/2/3/4/5/6/7/8/', '/#1/#1/#1/#1/#1/#1/…/'],
            'una cadena con puntos y guiones' => ['/a.b-c.d', '/?'],
        ];
    }

    #[DataProvider('formas')]
    public function testForma(string $uri, string $esperada): void
    {
        $this->assertSame($esperada, RegistroDeUrls::forma($uri, self::CONOCIDAS));
    }

    /** The point of keeping only a shape: whatever somebody typed into a URL, none of it survives. */
    public function testNingunTextoDeLaRutaSobrevive(): void
    {
        foreach (['/maria-garcia/', '/b/10/maria%20garcia/', '/juan.perez/', '/b/10/rosa elena/', '/6128473105/', '/b/8/garcia/'] as $uri) {
            $forma = RegistroDeUrls::forma($uri, ['b']);

            $this->assertDoesNotMatchRegularExpression('/maria|garcia|juan|perez|rosa|elena|6128473105/i', $forma, $uri);
        }
    }

    public function testSoloSeRegistran404Y301(): void
    {
        $registro = $this->registro();
        foreach ([200, 302, 303, 400, 403, 410, 500] as $estado) {
            $registro->registrar($estado, '/x/');
        }

        $this->assertSame([], $registro->resumen(1));
        $this->assertFalse(is_dir($this->dir), 'nothing to record, nothing created');
    }

    public function testCuentaPorFormaYPorDestino(): void
    {
        $registro = $this->registro();
        foreach (['/6128473999/', '/6128473998/', '/6128473997/'] as $uri) {
            $registro->registrar(404, $uri);
        }
        $registro->registrar(404, '/wp-login.php');
        $registro->registrar(301, '/6128473105', '/6128473105/');
        $registro->registrar(301, '/cart/', '/c/', self::CONOCIDAS);
        $registro->registrar(301, '/cart/', '/c/', self::CONOCIDAS);
        $registro->registrar(301, '/582399/', '/582317/');

        $this->assertSame([
            ['estado' => 404, 'forma' => '/#10/', 'destino' => '-', 'total' => 3],
            ['estado' => 301, 'forma' => '/cart/', 'destino' => '/c/', 'total' => 2],
            ['estado' => 301, 'forma' => '/#10', 'destino' => '/#10/', 'total' => 1],
            ['estado' => 301, 'forma' => '/#6/', 'destino' => '/#6/', 'total' => 1],
            ['estado' => 404, 'forma' => '/*.php', 'destino' => '-', 'total' => 1],
        ], $this->registro()->resumen(1));
    }

    public function testElArchivoNoGuardaLaRutaNiLaQueryNiNingunDatoDePersona(): void
    {
        $this->registro()->registrar(404, '/b/10/maria%20garcia/?token=secreto&email=a@b.c');

        $contenido = (string)file_get_contents($this->dir . '/urls-2025-10-31.tsv');
        $this->assertSame("404\t/b/#2/?/\t-\n", $contenido);
    }

    public function testElResumenAbarcaLosDiasPedidos(): void
    {
        $registro = $this->registro();
        $registro->registrar(404, '/hoy/');
        $this->ahora -= 86400;
        $registro->registrar(404, '/ayer/');
        $this->ahora -= 86400 * 5;
        $registro->registrar(404, '/hace-seis-dias/');
        $this->ahora += 86400 * 6;

        $this->assertSame(['/aaa/'], array_column($this->registro()->resumen(1), 'forma'), 'today only');
        $this->assertSame(['/a4+/', '/aaa/'], array_column($this->registro()->resumen(2), 'forma'), 'today and yesterday');
        $this->assertSame(['/?/', '/a4+/', '/aaa/'], array_column($this->registro()->resumen(7), 'forma'), 'a week');
        $this->assertSame(2, count($this->registro()->resumen(6)), 'six days back does not reach the sixth day before today');
    }

    public function testUnDiaDeInundacionNoLlenaElDisco(): void
    {
        $archivo = $this->dir . '/urls-2025-10-31.tsv';
        mkdir($this->dir, 0700, true);
        file_put_contents($archivo, str_repeat('x', RegistroDeUrls::MAX_BYTES_POR_DIA));

        $motivo = $this->registro()->registrar(404, '/otra/');

        $this->assertSame(RegistroDeUrls::MAX_BYTES_POR_DIA, filesize($archivo), 'the day file stopped growing');
        $this->assertStringContainsString('tope', (string)$motivo, 'and the caller is told why');
    }

    public function testAlEmpezarUnDiaSeBorranLosArchivosViejosYSoloLosSuyos(): void
    {
        mkdir($this->dir, 0700, true);
        $viejo = $this->dir . '/urls-2025-08-01.tsv';
        $reciente = $this->dir . '/urls-2025-10-20.tsv';
        $ajeno = $this->dir . '/notas-2025-08-01.txt';
        $otroNombre = $this->dir . '/urls-viejo.tsv';
        foreach ([$viejo, $reciente, $ajeno, $otroNombre] as $f) {
            file_put_contents($f, "404\t/x/\t-\n");
        }

        $this->registro()->registrar(404, '/nuevo/');

        $this->assertFileDoesNotExist($viejo, 'older than ' . RegistroDeUrls::DIAS_QUE_SE_GUARDAN . ' days');
        $this->assertFileExists($reciente);
        $this->assertFileExists($ajeno, 'not one of its files');
        $this->assertFileExists($otroNombre, 'not named like one of its files');
    }

    public function testUnaLineaMedioEscritaSeIgnora(): void
    {
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir . '/urls-2025-10-31.tsv', "404\t/a4+/\t-\n404\t/media");

        $this->assertSame([['estado' => 404, 'forma' => '/a4+/', 'destino' => '-', 'total' => 1]], $this->registro()->resumen(1));
    }

    public function testApagadoNoHaceNada(): void
    {
        $registro = new RegistroDeUrls(null);
        $this->assertNull($registro->registrar(404, '/x/'), 'turned off is not a failure');

        $this->assertSame([], $registro->resumen(7));
    }

    public function testUnaCarpetaQueNoSePuedeCrearNoRompeNada(): void
    {
        file_put_contents($this->dir, 'un archivo donde iría la carpeta');
        $motivo = (new RegistroDeUrls($this->dir . '/sub'))->registrar(404, '/x/');
        unlink($this->dir);

        $this->assertStringContainsString('carpeta', (string)$motivo);
    }

    public function testUnArchivoDelDiaQueNoSePuedeEscribirDiceElMotivo(): void
    {
        // The day's file is a directory: it exists, it is not full, and nothing can be appended to it.
        mkdir($this->dir . '/urls-2025-10-31.tsv', 0700, true);

        $this->assertStringContainsString('escribir', (string)$this->registro()->registrar(404, '/x/'));
    }

    public function testRegistrarBienNoDevuelveNada(): void
    {
        $this->assertNull($this->registro()->registrar(404, '/x/'));
        $this->assertNull($this->registro()->registrar(200, '/x/'), 'a 200 is not counted, and it is not an error');
    }

    public function testLaConfiguracion(): void
    {
        putenv('URL_LOG_DIR=off');
        $apagado = RegistroDeUrls::desdeConfig();
        $apagado->registrar(404, '/x/');
        $this->assertSame([], $apagado->resumen(1));

        putenv('URL_LOG_DIR=' . $this->dir . '/');
        RegistroDeUrls::desdeConfig()->registrar(404, '/x/');
        $this->assertDirectoryExists($this->dir);
        putenv('URL_LOG_DIR');
    }
}
