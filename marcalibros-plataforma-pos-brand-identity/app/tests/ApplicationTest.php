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
        $this->assertSame(20, preg_match_all('#href="/\?categoria=[a-z-]+"><span>#', $response->body), 'El índice debe tener las 20 categorías.');
        $this->assertStringContainsString('Realismo mágico', $response->body);
        $this->assertStringContainsString('Libros sagrados', $response->body);
        $this->assertStringContainsString('Movimientos literarios', $response->body);
        $this->assertStringContainsString('Tipos de texto', $response->body);
        $this->assertSame(40, preg_match_all('#<img class="tira" #', $response->body), 'Dos muestras por categoría.');
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
        $this->assertSame(1000, $catalog->count());
        foreach ($catalog->ids() as $id) {
            $response = $app->handle(new Request('GET', "/catalogo/$id.svg"));
            $this->assertSame(200, $response->status, $id);
            $this->assertLessThan(10 * 1024, (int) filesize((string) $response->file), "$id pesa más de 10 KB");
        }
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

    public function testStaticFileResponsesExposeTheEtagOfTheCurrentFile(): void
    {
        $app = $this->app();
        $first = $this->get($app, '/catalogo/mb-leyes-02.svg');
        $second = $this->get($app, '/catalogo/mb-leyes-03.svg');
        $this->assertNotSame($first->headers['ETag'], $second->headers['ETag']);
        $this->assertSame(200, $this->get($app, '/catalogo/mb-leyes-02.svg', [], ['if-none-match' => '"otro"'])->status);
    }
}
