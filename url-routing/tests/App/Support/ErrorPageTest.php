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
        $this->assertSame(AccessPolicy::CUERPO_404, ErrorPage::body(404, ErrorPage::NOT_FOUND));
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
            'build it with ErrorPage::body(): only the CUERPO_404 constant, which needs a constant expression, writes the tag'
        );
    }
}
