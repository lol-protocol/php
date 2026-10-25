<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\Application;
use MarcalibrosApp\BookmarkStore;
use MarcalibrosApp\Request;
use MarcalibrosApp\Response;
use MarcalibrosApp\View;

final class ApplicationTest extends AppTestCase
{
    // ------------------------------------------------------------ cabeceras y rutas

    public function testTheBrandFontsAreAllowedByThePolicyAndExist(): void
    {
        $csp = $this->get($this->app(), '/')->headers['Content-Security-Policy'] ?? '';
        $this->assertStringContainsString("font-src 'self'", $csp);

        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/app.css');
        $this->assertSame(3, preg_match_all('/url\("(fuentes\/[^"]+\.woff2)"\)/', $css, $m));
        foreach ($m[1] as $file) {
            $this->assertFileExists(dirname(__DIR__) . '/public/assets/' . $file);
        }
    }

    public function testEveryResponseCarriesTheSecurityHeaders(): void
    {
        $app = $this->app();
        foreach (['/', '/crear', '/no-existe', '/marca.svg'] as $path) {
            $headers = $this->get($app, $path)->headers;
            $this->assertStringContainsString("default-src 'none'", $headers['Content-Security-Policy'] ?? '', $path);
            $this->assertStringNotContainsString("'unsafe-inline'", $headers['Content-Security-Policy'] ?? '', $path);
            $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null, $path);
            $this->assertSame('DENY', $headers['X-Frame-Options'] ?? null, $path);
            $this->assertSame('no-referrer', $headers['Referrer-Policy'] ?? null, $path);
        }
    }

    public function testASpecificHeaderIsNotOverwrittenByTheDefault(): void
    {
        $response = $this->get($this->app(), '/catalogo/mb-leyes-01.svg');
        $this->assertSame("default-src 'none'; sandbox", $response->headers['Content-Security-Policy']);
    }

    public function testUnknownPathsAre404(): void
    {
        $app = $this->app();
        foreach (['/nada', '/crear/extra', '/marcalibro', '/mio', '/mio/a/b/c', '/storage/marcalibros', '/bootstrap.php', '/ruta-invalida'] as $path) {
            $this->assertSame(404, $this->get($app, $path)->status, $path);
        }
    }

    public function testWrongMethodsAre405WithAllowHeader(): void
    {
        $app = $this->app();
        $this->assertSame(405, $this->post($app, '/')->status);
        $this->assertSame('GET, HEAD', $this->post($app, '/')->headers['Allow']);
        $this->assertSame(405, $app->handle(new Request('DELETE', '/crear'))->status);
        $this->assertSame('GET, HEAD, POST', $app->handle(new Request('PUT', '/crear'))->headers['Allow']);
        $this->assertSame(405, $this->get($app, '/mio/mp-0123456789abcdef/eliminar')->status);
        $this->assertSame('POST', $this->get($app, '/mio/mp-0123456789abcdef/eliminar')->headers['Allow']);
    }

    public function testHeadBehavesLikeGet(): void
    {
        $this->assertSame(200, $this->app()->handle(new Request('HEAD', '/'))->status);
    }

    public function testAnExceptionBecomesACleanServerErrorWithoutLeakingDetails(): void
    {
        $app = $this->app($this->config());
        $broken = new Application(
            $this->config(),
            new \Marcalibros\Catalog($this->projectRoot() . '/catalog/manifest.json'),
            new \Marcalibros\LandscapeLibrary($this->projectRoot() . '/images'),
            new \Marcalibros\BookmarkMaker($this->dir . '/x', new \Marcalibros\LandscapeLibrary($this->projectRoot() . '/images'), new \Marcalibros\Limits()),
            new BookmarkStore($this->dir . '/x'),
            new \MarcalibrosApp\RateLimiter($this->dir . '/y'),
            new View($this->dir . '/sin-plantillas'),
            new \Marcalibros\Limits(),
            fn (): int => $this->now,
            fn (string $p): bool => false,
        );
        $previous = ini_set('error_log', $this->dir . '/errores.log');
        try {
            $response = $this->get($broken, '/');
        } finally {
            ini_set('error_log', (string) $previous);
        }
        $this->assertSame(500, $response->status);
        $this->assertStringNotContainsString($this->dir, $response->body);
        $this->assertStringNotContainsString('Exception', $response->body);
        $this->assertSame($app->handle(new Request('GET', '/'))->status, 200);
    }

    // ------------------------------------------------------------------ catálogo

    public function testHomeListsAllCategoriesWithTheirTotals(): void
    {
        $response = $this->get($this->app(), '/');
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Elige un marcalibro', $response->body);
        $this->assertSame(27, preg_match_all('#href="/\?categoria=[a-z-]+"><span>#', $response->body), 'El índice debe tener las 27 categorías.');
        $this->assertStringContainsString('Realismo mágico', $response->body);
        $this->assertStringContainsString('Libros sagrados', $response->body);
        $this->assertStringContainsString('Movimientos literarios', $response->body);
        $this->assertStringContainsString('Tipos de texto', $response->body);
        $this->assertSame(54, preg_match_all('#<img class="tira" #', $response->body), 'Dos muestras por categoría.');
    }

    public function testHomeShowsTheNoticeAfterDeleting(): void
    {
        $this->assertStringContainsString('Se eliminó tu marcalibro.', $this->get($this->app(), '/', ['aviso' => 'borrado'])->body);
        $this->assertStringNotContainsString('Se eliminó', $this->get($this->app(), '/', ['aviso' => '<script>'])->body);
    }

    public function testACategoryPageShowsItsFirst25AndTheCreateCell(): void
    {
        $response = $this->get($this->app(), '/', ['categoria' => 'leyes']);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<h1>Leyes y reglamentos</h1>', $response->body);
        $this->assertSame(25, preg_match_all('#<img class="tira" #', $response->body));
        $this->assertStringContainsString('Crea el tuyo', $response->body);
        $this->assertStringContainsString('Página 1 de 2', $response->body);
        $this->assertStringContainsString('balanza', $response->body);
    }

    public function testSecondPageHasTheRestAndNoCreateCell(): void
    {
        $response = $this->get($this->app(), '/', ['categoria' => 'leyes', 'pagina' => '2']);
        $this->assertSame(25, preg_match_all('#<img class="tira" #', $response->body));
        $this->assertStringNotContainsString('tira--nueva', $response->body);
        $this->assertStringContainsString('Página 2 de 2', $response->body);
    }

    public function testPageNumbersAreClamped(): void
    {
        $app = $this->app();
        $this->assertStringContainsString('Página 2 de 2', $this->get($app, '/', ['categoria' => 'leyes', 'pagina' => '99'])->body);
        $this->assertStringContainsString('Página 1 de 2', $this->get($app, '/', ['categoria' => 'leyes', 'pagina' => '0'])->body);
        $this->assertStringContainsString('Página 1 de 2', $this->get($app, '/', ['categoria' => 'leyes', 'pagina' => '-3'])->body);
        $this->assertStringContainsString('Página 1 de 2', $this->get($app, '/', ['categoria' => 'leyes', 'pagina' => 'abc'])->body);
        $this->assertStringContainsString('Página 1 de 2', $this->get($app, '/', ['categoria' => 'leyes', 'pagina' => ['x']])->body);
    }

    public function testUnknownOrMalformedCategoryIs404(): void
    {
        $app = $this->app();
        $this->assertSame(404, $this->get($app, '/', ['categoria' => 'no-existe'])->status);
        $this->assertSame(404, $this->get($app, '/', ['categoria' => ['leyes']])->status);
        $this->assertSame(404, $this->get($app, '/', ['categoria' => ''])->status);
        $this->assertSame(404, $this->get($app, '/', ['categoria' => '../x'])->status);
    }

    public function testDetailPageShowsTheMotifColoursAndNeighbours(): void
    {
        $response = $this->get($this->app(), '/marcalibro/mb-leyes-07');
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Número 7 de 50 en Leyes y reglamentos', $response->body);
        $this->assertStringContainsString('href="/marcalibro/mb-leyes-06"', $response->body);
        $this->assertStringContainsString('href="/marcalibro/mb-leyes-08"', $response->body);
        $this->assertStringContainsString('/catalogo/mb-leyes-07.svg?descargar=1', $response->body);
        $this->assertMatchesRegularExpression('#(Tinta|Papel|Ocre|Verde|Niebla) <code>#', $response->body);
    }

    public function testDetailOfTheFirstAndLastHasNoPreviousOrNext(): void
    {
        $app = $this->app();
        $first = $this->get($app, '/marcalibro/mb-leyes-01')->body;
        $this->assertStringNotContainsString('rel="prev"', $first);
        $this->assertStringContainsString('rel="next"', $first);
        $last = $this->get($app, '/marcalibro/mb-leyes-50')->body;
        $this->assertStringContainsString('rel="prev"', $last);
        $this->assertStringNotContainsString('rel="next"', $last);
    }

    public function testDetailGoesBackToTheRightListPage(): void
    {
        $this->assertStringContainsString('href="/?categoria=leyes&amp;pagina=2"', $this->get($this->app(), '/marcalibro/mb-leyes-30')->body);
        $this->assertStringContainsString('href="/?categoria=leyes"', $this->get($this->app(), '/marcalibro/mb-leyes-03')->body);
    }

    public function testMovementsShowTheirInspirationAuthors(): void
    {
        $this->assertStringContainsString('Inspirado en', $this->get($this->app(), '/marcalibro/mb-romanticismo-01')->body);
        $this->assertStringNotContainsString('Inspirado en', $this->get($this->app(), '/marcalibro/mb-leyes-01')->body);
    }

    public function testDetailOfUnknownIdsIs404(): void
    {
        $app = $this->app();
        foreach (['mb-leyes-99', 'mb-nada-01', '..%2f..%2fetc%2fpasswd', 'MB-LEYES-01', 'mb-leyes-01.svg'] as $id) {
            $this->assertSame(404, $this->get($app, "/marcalibro/$id")->status, $id);
        }
    }

    public function testCatalogSvgIsServedAsAFileWithCaching(): void
    {
        $response = $this->get($this->app(), '/catalogo/mb-gotico-04.svg');
        $this->assertSame(200, $response->status);
        $this->assertSame('image/svg+xml', $response->headers['Content-Type']);
        $this->assertSame($this->projectRoot() . '/catalog/svg/mb-gotico-04.svg', $response->file);
        $this->assertArrayHasKey('ETag', $response->headers);
        $this->assertArrayNotHasKey('Content-Disposition', $response->headers);

        $cached = $this->get($this->app(), '/catalogo/mb-gotico-04.svg', [], ['if-none-match' => $response->headers['ETag']]);
        $this->assertSame(304, $cached->status);
        $this->assertSame(null, $cached->file);
    }

    public function testCatalogSvgCanBeDownloaded(): void
    {
        $response = $this->get($this->app(), '/catalogo/mb-gotico-04.svg', ['descargar' => '1']);
        $this->assertSame('attachment; filename="mb-gotico-04.svg"', $response->headers['Content-Disposition']);
    }

    public function testEveryCatalogSvgIsReachableAndSmall(): void
    {
        $app = $this->app();
        $catalog = new \Marcalibros\Catalog($this->projectRoot() . '/catalog/manifest.json');
        $this->assertSame(1350, $catalog->count());
        foreach ($catalog->ids() as $id) {
            $response = $app->handle(new Request('GET', "/catalogo/$id.svg"));
            $this->assertSame(200, $response->status, $id);
            $this->assertLessThan(10 * 1024, (int) filesize((string) $response->file), "$id pesa más de 10 KB");
        }
    }

    // ---------------------------------------------------------- filtro del catálogo

    public function testFiltersTheCatalogByMotif(): void
    {
        $response = $this->get($this->app(), '/', ['motivo' => 'faro']);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Resultados', $response->body);
        $this->assertStringContainsString('marcalibros con el motivo «Faro»', $response->body);
        preg_match_all('#href="/marcalibro/(mb-[a-z-]+-\d+)"#', $response->body, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $id) {
            $this->assertStringStartsWith('mb-modernismo-anglosajon-', $id);
        }
        $this->assertStringContainsString('<option value="faro" selected>', $response->body);
    }

    public function testFiltersByColorAndCombinesWithTheCategory(): void
    {
        $app = $this->app();
        $catalog = new \Marcalibros\Catalog($this->projectRoot() . '/catalog/manifest.json');
        $expected = $catalog->search('leyes', null, '#C98B3A');
        $this->assertNotEmpty($expected);

        $response = $this->get($app, '/', ['categoria' => 'leyes', 'color' => 'ocre']);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(count($expected) . ' marcalibros que usan el color ocre en Leyes y reglamentos.', $response->body);
        $this->assertStringContainsString('<input type="hidden" name="categoria" value="leyes">', $response->body);
        $this->assertStringContainsString('href="/?categoria=leyes">Quitar filtros', $response->body);
    }

    public function testEmptyFilterValuesMeanNoFilter(): void
    {
        $response = $this->get($this->app(), '/', ['motivo' => '', 'color' => '']);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Elige un marcalibro', $response->body);
    }

    public function testUnknownFiltersAreNotFound(): void
    {
        $app = $this->app();
        $this->assertSame(404, $this->get($app, '/', ['motivo' => 'no-existe'])->status);
        $this->assertSame(404, $this->get($app, '/', ['color' => 'rojo'])->status);
        $this->assertSame(404, $this->get($app, '/', ['motivo' => ['faro']])->status);
    }

    public function testAFilterWithNoResultsSaysSo(): void
    {
        // Las Torres son del Gótico: dentro de Teatro no hay ninguna.
        $response = $this->get($this->app(), '/', ['categoria' => 'teatro', 'motivo' => 'torres']);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('No hay marcalibros con el motivo «Torres» en Teatro.', $response->body);
        $this->assertStringNotContainsString('class="rejilla"', $response->body);
    }

    public function testPaginationKeepsTheFilters(): void
    {
        $response = $this->get($this->app(), '/', ['color' => 'tinta']);
        $this->assertStringContainsString('href="/?color=tinta&amp;pagina=2"', $response->body);
    }

    // ------------------------------------------------------ hoja para imprimir

    /** @return list<string> */
    private function bookmarkIds(string $html, string $prefix): array
    {
        preg_match_all('#' . preg_quote($prefix, '#') . '(mb-[a-z-]+-\d+)(?:\.svg)?"#', $html, $m);
        return $m[1];
    }

    public function testPrintSheetShowsTheSameBookmarksAsTheCatalogPage(): void
    {
        $app = $this->app();
        foreach ([['categoria' => 'leyes'], ['motivo' => 'faro'], ['categoria' => 'poesia', 'color' => 'ocre', 'pagina' => '2']] as $query) {
            $catalog = $this->get($app, '/', $query);
            $sheet = $this->get($app, '/imprimir', $query);
            $this->assertSame(200, $sheet->status);
            $this->assertNotEmpty($this->bookmarkIds($sheet->body, '/catalogo/'));
            $this->assertSame($this->bookmarkIds($catalog->body, '/marcalibro/'), $this->bookmarkIds($sheet->body, '/catalogo/'), json_encode($query));
        }
    }

    public function testPrintSheetIsPrivateAndPaged(): void
    {
        $app = $this->app();
        $sheet = $this->get($app, '/imprimir', ['categoria' => 'leyes']);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $sheet->body);
        $this->assertStringNotContainsString('og:', $sheet->body);
        $this->assertStringContainsString('Leyes y reglamentos: 50 marcalibros, página 1 de 2 (25 en esta hoja).', $sheet->body);
        $this->assertStringContainsString('href="/imprimir?categoria=leyes&amp;pagina=2"', $sheet->body);
        $this->assertStringContainsString('href="/?categoria=leyes">Volver al catálogo', $sheet->body);
        $this->assertCount(25, $this->bookmarkIds($sheet->body, '/catalogo/'));

        $second = $this->get($app, '/imprimir', ['categoria' => 'leyes', 'pagina' => '2']);
        $this->assertStringContainsString('href="/imprimir?categoria=leyes"', $second->body, 'La hoja 2 vuelve a la 1 sin pagina=1.');
        $this->assertStringContainsString('href="/?categoria=leyes&amp;pagina=2">Volver al catálogo', $second->body);
    }

    public function testPrintSheetRejectsTheSameInvalidFiltersAsTheCatalog(): void
    {
        $app = $this->app();
        $this->assertSame(404, $this->get($app, '/imprimir', ['categoria' => 'no-existe'])->status);
        $this->assertSame(404, $this->get($app, '/imprimir', ['motivo' => 'no-existe'])->status);
        $this->assertSame(404, $this->get($app, '/imprimir', ['color' => 'rojo'])->status);
        $this->assertSame(404, $this->get($app, '/imprimir', ['motivo' => ['faro']])->status);
        $this->assertSame(405, $this->post($app, '/imprimir')->status);
    }

    public function testCatalogListLinksToItsPrintSheetButTheHomeDoesNot(): void
    {
        $app = $this->app();
        $this->assertStringContainsString('href="/imprimir?categoria=teatro&amp;color=verde">Imprimir esta página', $this->get($app, '/', ['categoria' => 'teatro', 'color' => 'verde'])->body);
        $this->assertStringNotContainsString('/imprimir', $this->get($app, '/')->body);
        $this->assertStringNotContainsString('Imprimir esta página', $this->get($app, '/', ['categoria' => 'teatro', 'motivo' => 'torres'])->body, 'Sin resultados no hay nada que imprimir.');
    }

    // ------------------------------------------------------- PNG y vista previa

    public function testPngIsOfferedAndServedOnlyWhenItWasGenerated(): void
    {
        $withPng = $this->app($this->config(projectRoot: $this->projectWithPng(['mb-poesia-03'])));

        $detail = $this->get($withPng, '/marcalibro/mb-poesia-03');
        $this->assertStringContainsString('href="/catalogo/mb-poesia-03.png?descargar=1"', $detail->body);
        $this->assertStringContainsString('href="/catalogo/mb-poesia-03@2x.png?descargar=1"', $detail->body);
        $png = $this->get($withPng, '/catalogo/mb-poesia-03@2x.png', ['descargar' => '1']);
        $this->assertSame(200, $png->status);
        $this->assertSame('image/png', $png->headers['Content-Type']);
        $this->assertSame('attachment; filename="mb-poesia-03@2x.png"', $png->headers['Content-Disposition']);

        $this->assertStringNotContainsString('.png?descargar=1', $this->get($withPng, '/marcalibro/mb-poesia-04')->body);
        $this->assertSame(404, $this->get($withPng, '/catalogo/mb-poesia-04.png')->status);
        $this->assertSame(404, $this->get($withPng, '/catalogo/mb-no-existe-01.png')->status);
    }

    public function testServesThePreviewJpeg(): void
    {
        $response = $this->get($this->app(), '/catalogo/mb-historia-02.jpg');
        $this->assertSame(200, $response->status);
        $this->assertSame('image/jpeg', $response->headers['Content-Type']);
        $this->assertSame(404, $this->get($this->app(), '/catalogo/mb-no-existe-01.jpg')->status);
    }

    // ------------------------------------------------- vista previa al compartir

    public function testShareTagsUseAbsoluteUrlsOnlyWhenThePublicUrlIsSet(): void
    {
        $plain = $this->get($this->app(), '/marcalibro/mb-historia-02')->body;
        $this->assertStringContainsString('<meta property="og:title" content="', $plain);
        $this->assertStringNotContainsString('og:image', $plain);

        $app = $this->app($this->config(publicUrl: 'https://marcalibros.example.com'));
        $detail = $this->get($app, '/marcalibro/mb-historia-02')->body;
        $this->assertStringContainsString('<meta property="og:url" content="https://marcalibros.example.com/marcalibro/mb-historia-02">', $detail);
        $this->assertStringContainsString('<meta property="og:image" content="https://marcalibros.example.com/catalogo/mb-historia-02.jpg">', $detail);
        $home = $this->get($app, '/')->body;
        $this->assertStringContainsString('<meta property="og:image" content="https://marcalibros.example.com/paisajes/paisaje-bosque-1200x630.jpg">', $home);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $home);
    }

    public function testShareImageIsThePngWhenItExists(): void
    {
        $app = $this->app($this->config(publicUrl: 'https://marcalibros.example.com', projectRoot: $this->projectWithPng(['mb-poesia-03'])));
        $this->assertStringContainsString('content="https://marcalibros.example.com/catalogo/mb-poesia-03.png"', $this->get($app, '/marcalibro/mb-poesia-03')->body);
    }

    public function testPrivatePagesHaveNoShareTags(): void
    {
        $app = $this->app($this->config(publicUrl: 'https://marcalibros.example.com'));
        $this->assertStringNotContainsString('og:', $this->get($app, '/crear')->body);
    }

    public function testLandscapesOnlyServeLibraryFiles(): void
    {
        $app = $this->app();
        $this->assertSame(200, $this->get($app, '/paisajes/paisaje-costa-1200x630.jpg')->status);
        foreach (['../README.md', '..%2fREADME.md', 'paisaje-costa-1200x630.jpg%00.png', 'otro.jpg', 'paisaje-costa-1200x630.JPG'] as $name) {
            $this->assertSame(404, $this->get($app, "/paisajes/$name")->status, $name);
        }
    }

    public function testBrandLogoIsServed(): void
    {
        $response = $this->get($this->app(), '/marca.svg');
        $this->assertSame(200, $response->status);
        $this->assertSame('image/svg+xml', $response->headers['Content-Type']);
    }

    // ------------------------------------------------------------------- crear

    public function testCreateFormListsTheBrandLandscapesWithTheirNames(): void
    {
        $body = $this->get($this->app(), '/crear')->body;
        foreach (['Amanecer', 'Bosque', 'Costa', 'Montaña'] as $name) {
            $this->assertStringContainsString("<span>$name</span>", $body);
        }
        $this->assertStringContainsString('value="paisaje-costa-1920x1080.jpg"', $body);
        $this->assertStringContainsString('data-ratio="7"', $body);
        $this->assertStringContainsString('data-min-width="700"', $body);
        $this->assertStringContainsString('maxlength="40"', $body);
        $this->assertStringContainsString('<noscript>', $body);
    }

    public function testCreateFromAnUploadedImageRedirectsToTheBookmark(): void
    {
        $app = $this->app();
        $response = $this->post($app, '/crear', ['fuente' => 'subida', 'zoom' => '1', 'x' => '0.5', 'y' => '0.5', 'nombre' => 'Para mi libro'], $this->upload());
        $this->assertRedirectsTo('#^/mio/mp-[0-9a-f]{16}$#', $response);

        $page = $this->get($app, $response->headers['Location']);
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('<h1>Para mi libro</h1>', $page->body);
        $this->assertSame('noindex, nofollow', $page->headers['X-Robots-Tag']);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $page->body);
        $this->assertStringContainsString('Se borrará solo el 14 de febrero de 2027', $page->body);
    }

    public function testALabelThatAccusesSomeoneIsNotSaved(): void
    {
        $app = $this->app();
        $response = $this->post($app, '/crear', ['fuente' => 'subida', 'nombre' => 'Fulano es un estafador'], $this->upload());

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('no publicamos insultos, acusaciones ni datos personales', $response->body);
        $this->assertStringContainsString('value="Fulano es un estafador"', $response->body, 'el formulario conserva el texto para corregirlo');
        $this->assertSame(0, (new BookmarkStore($this->dir . '/almacen/marcalibros'))->count());
    }

    public function testInvisibleCharactersCannotHideAPersonalDataFromTheReview(): void
    {
        $app = $this->app();
        foreach (["Escríbeme a juan.perez@gma\u{200B}il.com", "Fulano es un esta\u{200B}fador"] as $label) {
            $response = $this->post($app, '/crear', ['fuente' => 'subida', 'nombre' => $label], $this->upload());
            $this->assertSame(422, $response->status, json_encode($label));
        }
        $this->assertSame(0, (new BookmarkStore($this->dir . '/almacen/marcalibros'))->count());
    }

    public function testTheSheetAndTheCatalogShowTheSamePage(): void
    {
        $app = $this->app();
        $catalog = $this->get($app, '/', ['categoria' => 'leyes', 'pagina' => '2'])->body;
        $sheet = $this->get($app, '/imprimir', ['categoria' => 'leyes', 'pagina' => '2'])->body;
        preg_match_all('#mb-leyes-\d+#', $catalog, $a);
        preg_match_all('#mb-leyes-\d+#', $sheet, $b);
        $this->assertSame(array_values(array_unique($a[0])), array_values(array_unique($b[0])));
        $this->assertContains('mb-leyes-26', $b[0]);
    }

    public function testTheSavedImagesHaveTheTwoSizes(): void
    {
        $app = $this->app();
        $location = $this->post($app, '/crear', ['fuente' => 'subida'], $this->upload())->headers['Location'];
        foreach (['1x' => [1400, 200], '2x' => [2800, 400]] as $size => $expected) {
            $response = $this->get($app, "$location/$size.jpg");
            $this->assertSame(200, $response->status);
            $this->assertSame('image/jpeg', $response->headers['Content-Type']);
            $this->assertStringContainsString('private', $response->headers['Cache-Control']);
            $info = getimagesize((string) $response->file);
            $this->assertSame($expected, [$info[0] ?? 0, $info[1] ?? 0]);
        }
        $download = $this->get($app, "$location/2x.jpg", ['descargar' => '1']);
        $this->assertSame('attachment; filename="marcalibro-2800x400.jpg"', $download->headers['Content-Disposition']);
    }

    public function testCreateFromABrandLandscape(): void
    {
        $app = $this->app();
        $response = $this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg', 'zoom' => '2']);
        $this->assertRedirectsTo('#^/mio/mp-#', $response);
        $this->assertStringContainsString('<h1>Tu marcalibro</h1>', $this->get($app, $response->headers['Location'])->body);
    }

    public function testCropValuesAreAppliedToTheSavedImage(): void
    {
        $app = $this->app();
        // Mitad izquierda ocre, mitad derecha tinta: con zoom 4 y x=0 la franja es solo ocre; con x=1, solo tinta.
        $left = $this->post($app, '/crear', ['fuente' => 'subida', 'zoom' => '4', 'x' => '0', 'y' => '0.5'], $this->upload(2800, 700))->headers['Location'];
        $right = $this->post($app, '/crear', ['fuente' => 'subida', 'zoom' => '4', 'x' => '1', 'y' => '0.5'], $this->upload(2800, 700))->headers['Location'];
        $pixel = function (string $location) use ($app): array {
            $image = imagecreatefromjpeg((string) $this->get($app, "$location/1x.jpg")->file);
            $rgb = imagecolorat($image, 700, 100);
            return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
        };
        $this->assertGreaterThan(150, $pixel($left)[0], 'Con x=0 debe verse la mitad ocre (rojo alto).');
        $this->assertLessThan(60, $pixel($right)[0], 'Con x=1 debe verse la mitad tinta (rojo bajo).');
    }

    public function testRejectedUploadsShowAnErrorAndKeepWhatWasTyped(): void
    {
        $app = $this->app();
        $response = $this->post($app, '/crear', ['fuente' => 'subida', 'nombre' => 'No lo pierdas', 'zoom' => '1.5'], $this->upload(300, 200));
        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('role="alert"', $response->body);
        $this->assertStringContainsString('menos de 700 px', $response->body);
        $this->assertStringContainsString('value="No lo pierdas"', $response->body);
        $this->assertStringContainsString('value="1.5"', $response->body);
        $this->assertStringContainsString('Vuelve a elegirla', $response->body);
        $this->assertSame(0, (new BookmarkStore($this->dir . '/almacen/marcalibros'))->count());
    }

    public function testNoFileGivesAHelpfulMessage(): void
    {
        $response = $this->post($this->app(), '/crear', ['fuente' => 'subida']);
        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Elige una imagen para subir.', $response->body);
    }

    public function testNotAnImageIsRejected(): void
    {
        $path = $this->dir . '/falso.jpg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->uploaded[$path] = true;
        $response = $this->post($this->app(), '/crear', ['fuente' => 'subida'], ['imagen' => ['name' => 'falso.jpg', 'tmp_name' => $path, 'error' => 0, 'size' => 80]]);
        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Solo se aceptan imágenes JPG, PNG o WebP.', $response->body);
    }

    public function testOnlyFilesPhpReceivedAsUploadsAreProcessed(): void
    {
        // Alguien manda una ruta del servidor en vez de una subida real (por ejemplo, un archivo de la aplicación).
        $files = $this->upload(2000, 1000, asUploaded: false);
        $response = $this->post($this->app(), '/crear', ['fuente' => 'subida'], $files);
        $this->assertSame(422, $response->status);
        $this->assertSame(0, (new BookmarkStore($this->dir . '/almacen/marcalibros'))->count());
    }

    /** @return array<string, array{int, string}> */
    public static function uploadErrors(): array
    {
        return [
            'sin archivo' => [UPLOAD_ERR_NO_FILE, 'Elige una imagen para subir.'],
            'más que upload_max_filesize' => [UPLOAD_ERR_INI_SIZE, 'La imagen pesa demasiado. El máximo es 5 MB.'],
            'más que MAX_FILE_SIZE' => [UPLOAD_ERR_FORM_SIZE, 'La imagen pesa demasiado. El máximo es 5 MB.'],
            'subida parcial' => [UPLOAD_ERR_PARTIAL, 'La subida no se completó. Elige la imagen otra vez.'],
            'sin carpeta temporal' => [UPLOAD_ERR_NO_TMP_DIR, 'La subida no se completó. Elige la imagen otra vez.'],
            'no se pudo escribir' => [UPLOAD_ERR_CANT_WRITE, 'La subida no se completó. Elige la imagen otra vez.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('uploadErrors')]
    public function testUploadErrorCodesGetClearMessages(int $code, string $message): void
    {
        $response = $this->post($this->app(), '/crear', ['fuente' => 'subida'], ['imagen' => ['name' => 'a.jpg', 'tmp_name' => '', 'error' => $code, 'size' => 0]]);
        $this->assertSame(422, $response->status);
        $this->assertStringContainsString($message, $response->body);
    }

    public function testTheAdvertisedLimitIsTheSmallerOfTheBrandAndTheServer(): void
    {
        $this->assertStringContainsString('data-max-mb="5"', $this->get($this->app(), '/crear')->body);

        $limited = $this->app(serverUploadLimit: 2 * 1_048_576);
        $form = $this->get($limited, '/crear')->body;
        $this->assertStringContainsString('data-max-mb="2"', $form);
        $this->assertStringContainsString('data-max-bytes="2097152"', $form);
        $this->assertStringContainsString('hasta 2 MB', $form);

        $response = $this->post($limited, '/crear', ['fuente' => 'subida'], ['imagen' => ['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]]);
        $this->assertStringContainsString('El máximo es 2 MB.', $response->body);

        $roomy = $this->app(serverUploadLimit: 50 * 1_048_576);
        $this->assertStringContainsString('data-max-mb="5"', $this->get($roomy, '/crear')->body, 'Un servidor con más espacio no sube el límite de la marca.');
    }

    public function testABodyOverPostMaxSizeIs413(): void
    {
        $response = $this->post($this->app(), '/crear', tooLarge: true);
        $this->assertSame(413, $response->status);
        $this->assertStringContainsString('La imagen pesa demasiado', $response->body);
    }

    public function testAnOversizedBodyAlsoSpendsAnAttempt(): void
    {
        $app = $this->app($this->config(createsPerHour: 1));
        $this->assertSame(413, $this->post($app, '/crear', tooLarge: true)->status);
        $this->assertSame(429, $this->post($app, '/crear', tooLarge: true)->status);
    }

    public function testAFailureSavingTheDataKeepsTheFormAndSaysSo(): void
    {
        $config = $this->config();
        $limits = new \Marcalibros\Limits();
        $library = new \Marcalibros\LandscapeLibrary($config->projectRoot . '/images');
        $app = new Application(
            $config,
            new \Marcalibros\Catalog($config->projectRoot . '/catalog/manifest.json'),
            $library,
            new \Marcalibros\BookmarkMaker($config->storageDirectory . '/marcalibros', $library, $limits),
            new BookmarkStore($this->dir . '/no/existe'),   // los datos no se pueden escribir
            new \MarcalibrosApp\RateLimiter($config->storageDirectory . '/intentos'),
            new \MarcalibrosApp\View(dirname(__DIR__) . '/templates'),
            $limits,
            fn (): int => $this->now,
            fn (string $p): bool => false,
        );
        $previous = ini_set('error_log', $this->dir . '/errores.log');
        try {
            $response = @$this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg', 'nombre' => 'Para mi libro']);
        } finally {
            ini_set('error_log', (string) $previous);
        }
        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('No se pudo guardar el marcalibro', $response->body);
        $this->assertStringContainsString('value="Para mi libro"', $response->body);
    }

    public function testInvalidCropAndSourceAreRejected(): void
    {
        $app = $this->app();
        foreach ([['zoom' => '9'], ['zoom' => '0.5'], ['zoom' => 'abc'], ['x' => '2'], ['y' => '-1'], ['x' => 'NaN'], ['fuente' => 'url'], ['fuente' => ['subida']]] as $extra) {
            $response = $this->post($app, '/crear', $extra + ['fuente' => 'subida'], $this->upload(), uniqid());
            $this->assertSame(422, $response->status, json_encode($extra));
        }
    }

    public function testLandscapeNamesFromThePersonAreNeverTrusted(): void
    {
        $app = $this->app();
        foreach (['../../README.md', 'paisaje-costa-1920x1080.jpg/../../x', '', 'paisaje-inexistente-1x1.jpg'] as $name) {
            $response = $this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => $name], [], uniqid());
            $this->assertSame(422, $response->status, $name);
        }
    }

    public function testTheLabelIsEscapedAndTrimmed(): void
    {
        $app = $this->app();
        $location = $this->post($app, '/crear', ['fuente' => 'subida', 'nombre' => "  <img src=x onerror=alert(1)>\n" . str_repeat('a', 100)], $this->upload())->headers['Location'];
        $body = $this->get($app, $location)->body;
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;img src=x', $body);
    }

    // -------------------------------------------------- límites y protecciones

    public function testCrossSiteRequestsAreRefused(): void
    {
        $app = $this->app();
        $this->assertSame(403, $this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg'], headers: ['sec-fetch-site' => 'cross-site'])->status);
        $this->assertSame(403, $this->post($app, '/mio/mp-0123456789abcdef/eliminar', headers: ['sec-fetch-site' => 'cross-site'])->status);
        foreach (['same-origin', 'same-site', 'none'] as $site) {
            $this->assertNotSame(403, $this->post($app, '/crear', ['fuente' => 'subida'], headers: ['sec-fetch-site' => $site], ip: uniqid())->status, $site);
        }
    }

    public function testCreationIsRateLimitedPerAddress(): void
    {
        $app = $this->app($this->config(createsPerHour: 3));
        $landscape = ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg'];
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(303, $this->post($app, '/crear', $landscape, ip: '198.51.100.1')->status);
        }
        $blocked = $this->post($app, '/crear', $landscape, ip: '198.51.100.1');
        $this->assertSame(429, $blocked->status);
        $this->assertSame('3600', $blocked->headers['Retry-After']);
        $this->assertStringContainsString('Vuelve a probar en 60 minutos', $blocked->body);

        $this->assertSame(303, $this->post($app, '/crear', $landscape, ip: '198.51.100.2')->status, 'Otra dirección no se ve afectada.');
        $this->now += 3601;
        $this->assertSame(303, $this->post($app, '/crear', $landscape, ip: '198.51.100.1')->status, 'Pasada la hora se puede otra vez.');
    }

    public function testFailedAttemptsAlsoCountSoTheLimitCannotBeProbed(): void
    {
        $app = $this->app($this->config(createsPerHour: 2));
        $this->assertSame(422, $this->post($app, '/crear', ['fuente' => 'subida'], ip: '198.51.100.9')->status);
        $this->assertSame(422, $this->post($app, '/crear', ['fuente' => 'subida'], ip: '198.51.100.9')->status);
        $this->assertSame(429, $this->post($app, '/crear', ['fuente' => 'subida'], ip: '198.51.100.9')->status);
    }

    public function testTheStorageCapStopsNewBookmarks(): void
    {
        $app = $this->app($this->config(maxStored: 2));
        $landscape = ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg'];
        $this->assertSame(303, $this->post($app, '/crear', $landscape, ip: '1.1.1.1')->status);
        $this->assertSame(303, $this->post($app, '/crear', $landscape, ip: '1.1.1.2')->status);
        $full = $this->post($app, '/crear', $landscape, ip: '1.1.1.3');
        $this->assertSame(503, $full->status);
        $this->assertStringContainsString('no se pueden guardar más marcalibros', $full->body);
    }

    // ------------------------------------------------------- marcalibro propio

    public function testUnknownOrMalformedOwnIdsAre404(): void
    {
        $app = $this->app();
        foreach (['mp-0123456789abcdef', 'mp-zzzzzzzzzzzzzzzz', '..', '%2e%2e', 'mp-0123456789abcdef.json'] as $id) {
            $this->assertSame(404, $this->get($app, "/mio/$id")->status, $id);
            $this->assertSame(404, $this->get($app, "/mio/$id/1x.jpg")->status, $id);
            $this->assertSame(404, $this->post($app, "/mio/$id/eliminar")->status, $id);
        }
    }

    public function testOnlyTheTwoKnownSizesAreServed(): void
    {
        $app = $this->app();
        $location = $this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg'])->headers['Location'];
        $this->assertSame(404, $this->get($app, "$location/3x.jpg")->status);
        $this->assertSame(404, $this->get($app, "$location/1x.png")->status);
        $this->assertSame(404, $this->get($app, "$location/1x.json")->status);
    }

    public function testDeletingRemovesEverythingAndRedirectsWithANotice(): void
    {
        $app = $this->app();
        $location = $this->post($app, '/crear', ['fuente' => 'subida'], $this->upload())->headers['Location'];
        $deleted = $this->post($app, "$location/eliminar");
        $this->assertSame(303, $deleted->status);
        $this->assertSame('/?aviso=borrado', $deleted->headers['Location']);
        $this->assertSame(404, $this->get($app, $location)->status);
        $this->assertSame(404, $this->get($app, "$location/1x.jpg")->status);
        $this->assertSame([], glob($this->dir . '/almacen/marcalibros/mp-*') ?: []);
        $this->assertSame(404, $this->post($app, "$location/eliminar")->status, 'Borrar dos veces no es un éxito silencioso.');
    }

    public function testTheOwnPageMentionsTheRetentionPeriodFromTheConfiguration(): void
    {
        $app = $this->app($this->config(retentionDays: 7));
        $location = $this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg'])->headers['Location'];
        $body = $this->get($app, $location)->body;
        $this->assertStringContainsString('a los 7 días de crearlo', $body);
        $this->assertStringContainsString('se guardan 7 días', $body);
    }

    public function testWithoutRetentionTheBookmarkNeverExpires(): void
    {
        $app = $this->app($this->config(retentionDays: 0));
        $location = $this->post($app, '/crear', ['fuente' => 'paisaje', 'paisaje' => 'paisaje-costa-1920x1080.jpg'])->headers['Location'];
        $body = $this->get($app, $location)->body;
        $this->assertStringContainsString('sin fecha de vencimiento', $body);
        $this->assertStringNotContainsString('Se borrará solo', $body);
        $this->assertStringNotContainsString('se borran solos', $body);
    }

    public function testStaticFileResponsesExposeTheEtagOfTheCurrentFile(): void
    {
        $app = $this->app();
        $first = $this->get($app, '/catalogo/mb-leyes-02.svg');
        $second = $this->get($app, '/catalogo/mb-leyes-03.svg');
        $this->assertNotSame($first->headers['ETag'], $second->headers['ETag']);
        $this->assertSame(200, $this->get($app, '/catalogo/mb-leyes-02.svg', [], ['if-none-match' => '"otro"'])->status);
    }
}
