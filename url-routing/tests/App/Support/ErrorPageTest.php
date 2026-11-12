<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\AccessPolicy;
use App\Support\ErrorPage;
use PHPUnit\Framework\TestCase;

/** The one place that writes the body of an error response, and the guard that keeps it the only one. */
class ErrorPageTest extends TestCase
{
    public function testTheBodyIsATitleWithItsStatus(): void
    {
        $this->assertSame('<h1>403 - Prohibido</h1>', ErrorPage::body(403, 'Prohibido'));
    }

    public function testTheTitleIsEscapedAndTheDetailIsTheCallersHtml(): void
    {
        $this->assertSame('<h1>400 - &lt;b&gt;x&lt;/b&gt;</h1>', ErrorPage::body(400, '<b>x</b>'));
        $this->assertSame(
            "<h1>404 - Página no encontrada</h1>\n<p>Quisiste <a href=\"/a/\">/a/</a></p>",
            ErrorPage::body(404, ErrorPage::NOT_FOUND, 'Quisiste <a href="/a/">/a/</a>')
        );
    }

    public function testAHiddenPageIsAMissingPageByteForByte(): void
    {
        $this->assertSame(AccessPolicy::CUERPO_404, ErrorPage::body(404, ErrorPage::NOT_FOUND), 'the deprecated constant is still the bare body');
        $this->assertSame(AccessPolicy::ocultar(), ErrorPage::page(404, ErrorPage::NOT_FOUND), 'and the page of a hidden one is the page of a missing one');
    }

    public function testNoOtherFileWritesAnErrorTitleByHand(): void
    {
        $raiz = dirname(__DIR__, 3);
        $a_mano = [];
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        foreach ($archivos as $archivo) {
            $ruta = substr($archivo->getPathname(), strlen($raiz) + 1);
            if ($archivo->getExtension() !== 'php' || preg_match('#^(vendor|tests|views|var)/#', $ruta) || $ruta === 'Support/ErrorPage.php') {
                continue;
            }
            if (preg_match('#<h1>[1-5]\d\d\b#', (string)file_get_contents($archivo->getPathname()))) {
                $a_mano[] = ltrim($ruta, '/');
            }
        }

        $this->assertSame(
            ['Support/AccessPolicy.php'],
            $a_mano,
            'build it with ErrorPage::page() (or body()): only the deprecated CUERPO_404 constant writes the tag'
        );
    }

    public function testAPageThatCannotBeDrawnIsTheBareBodyAndNotAnError(): void
    {
        if (function_exists('esc')) {
            $this->markTestSkipped('the helpers of public/index.php are loaded: the layout can be drawn here');
        }

        // Outside public/index.php the layout has no esc(): the page must still answer, and with the same text.
        $this->assertSame(ErrorPage::body(418, 'Soy una tetera', 'con <b>detalle</b>'), ErrorPage::page(418, 'Soy una tetera', 'con <b>detalle</b>'));
        $this->assertSame(418, http_response_code(), 'and the status is the one it was asked for');
    }
}
