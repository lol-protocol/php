<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private function view(): View
    {
        return new View(dirname(__DIR__) . '/templates');
    }

    public function testEscapesHtmlAndQuotes(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &#039;y&#039;', $this->view()->e('<script>alert("x")</script> & \'y\''));
        $this->assertSame('', $this->view()->e(null));
    }

    public function testInvalidUtf8IsReplacedNotDropped(): void
    {
        $this->assertSame("a\u{FFFD}b", $this->view()->e("a\xFFb"));
    }

    public function testPaletteColoursHaveBrandNamesAndClasses(): void
    {
        $view = $this->view();
        $this->assertSame('Tinta', $view->colorName('#1e2a38'));
        $this->assertSame('Papel', $view->colorName('#F4EEE1'));
        $this->assertSame('#123456', $view->colorName('#123456'));
        $this->assertSame('muestra--ocre', $view->colorClass('#C98B3A'));
        $this->assertSame('muestra--niebla', $view->colorClass('#7a9cb0'));
        $this->assertSame('muestra--otro', $view->colorClass('#123456'));
    }

    public function testDateIsInSpanish(): void
    {
        $this->assertSame('1 de enero de 2027', $this->view()->date(gmmktime(12, 0, 0, 1, 1, 2027)));
        $this->assertSame('25 de diciembre de 2026', $this->view()->date(gmmktime(0, 0, 0, 12, 25, 2026)));
    }

    public function testUrlDropsEmptyParametersAndEncodesTheRest(): void
    {
        $view = $this->view();
        $this->assertSame('/', $view->url('/'));
        $this->assertSame('/', $view->url('/', ['categoria' => null, 'pagina' => '']));
        $this->assertSame('/?categoria=leyes&pagina=2', $view->url('/', ['categoria' => 'leyes', 'pagina' => 2]));
        $this->assertSame('/?q=a%20b%26c', $view->url('/', ['q' => 'a b&c']));
    }

    public function testAssetAddsTheModificationTimeWhenTheFileExists(): void
    {
        $this->assertMatchesRegularExpression('#^/assets/app\.css\?v=\d+$#', $this->view()->asset('app.css'));
        $this->assertSame('/assets/no-existe.js', $this->view()->asset('no-existe.js'));
    }

    public function testPageWrapsTheTemplateInTheLayoutAndEscapesTheTitle(): void
    {
        $html = $this->view()->page('error', ['status' => 404, 'heading' => 'Título <b>', 'message' => 'Mensaje & más'], 'Hola <i>');
        $this->assertStringContainsString('<title>Hola &lt;i&gt;</title>', $html);
        $this->assertStringContainsString('<h1>Título &lt;b&gt;</h1>', $html);
        $this->assertStringContainsString('Mensaje &amp; más', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function testPrivatePagesAskNotToBeIndexed(): void
    {
        $html = $this->view()->page('error', ['status' => 404, 'heading' => 'x', 'message' => 'y'], 'T', ['private' => true]);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
    }

    public function testSharedDataIsAvailableToEveryTemplate(): void
    {
        $view = new View(dirname(__DIR__) . '/templates', ['retentionDays' => 45]);
        $this->assertStringContainsString('se guardan 45 días', $view->page('error', ['status' => 404, 'heading' => 'x', 'message' => 'y'], 'T'));
    }

    public function testPartialRendersAnItemCellWithEscapedData(): void
    {
        $html = $this->view()->partial('_celda', ['item' => ['id' => 'mb-x-01', 'alt' => 'Con "comillas" & <b>', 'motif' => 'Ondas <i>']]);
        $this->assertStringContainsString('<li class="celda">', $html);
        $this->assertStringContainsString('href="/marcalibro/mb-x-01"', $html);
        $this->assertStringContainsString('src="/catalogo/mb-x-01.svg"', $html);
        $this->assertStringContainsString('alt="Con &quot;comillas&quot; &amp; &lt;b&gt;"', $html);
        $this->assertStringContainsString('>Ondas &lt;i&gt;</span>', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    /** La portada (celdas por categoría) y las listas filtradas pintan la misma celda: está en un solo parcial. */
    public function testTheCatalogPageUsesThePartialInBothLayouts(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/templates/catalogo.php');
        $this->assertSame(2, substr_count($source, "partial('_celda'"));
        $this->assertStringNotContainsString('class="celda"', $source, 'la celda no se vuelve a escribir en la plantilla');
    }

    public function testNoInlineStylesOrScriptsAreEverEmitted(): void
    {
        // La política de seguridad (style-src 'self') los bloquearía; esta prueba avisa antes de que alguien los agregue.
        foreach (glob(dirname(__DIR__) . '/templates/*.php') ?: [] as $template) {
            $source = (string) file_get_contents($template);
            $this->assertSame(0, preg_match('/\sstyle\s*=/i', $source), basename($template) . ' tiene un atributo style.');
            $this->assertSame(0, preg_match('/<style[\s>]/i', $source), basename($template) . ' tiene una etiqueta <style>.');
            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $source), basename($template) . ' tiene un manejador de eventos en línea.');
            $this->assertSame(0, preg_match('/<script(?![^>]*\ssrc=)/i', $source), basename($template) . ' tiene un script en línea.');
        }
    }
}
