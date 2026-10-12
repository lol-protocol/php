<?php

namespace MarcalibrosApp;

use Marcalibros\BookmarkMaker;
use Marcalibros\BookmarkRejectedException;
use Marcalibros\Catalog;
use Marcalibros\CropSettings;
use Marcalibros\ImageNormalizer;
use Marcalibros\LandscapeLibrary;
use Marcalibros\Label;
use Marcalibros\Limits;
use Marcalibros\RejectionReason;

/**
 * La aplicación web de marcalibros, sin cuentas: catálogo para elegir, y un
 * formulario para crear uno propio a partir de una imagen. Recibe una Request y
 * devuelve una Response, así se prueba sin servidor.
 *
 * Quien tiene el enlace de un marcalibro propio puede verlo, descargarlo y
 * borrarlo; nadie más puede encontrarlo. Se borran solos pasados unos días.
 */
final class Application
{
    private const PER_PAGE = 25;
    private const SAMPLE_PER_CATEGORY = 2;
    private const RATE_WINDOW = 3600;

    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; img-src 'self' blob:; font-src 'self'; style-src 'self'; script-src 'self'; "
            . "connect-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    private const LANDSCAPE_NAMES = ['amanecer' => 'Amanecer', 'bosque' => 'Bosque', 'costa' => 'Costa', 'montana' => 'Montaña'];

    /**
     * @param \Closure(): int         $clock          Hora actual (se cambia en las pruebas).
     * @param \Closure(string): bool  $isUploadedFile Comprueba que el archivo vino de una subida HTTP.
     * @param int                     $serverUploadLimit Lo que el servidor deja subir (upload_max_filesize y post_max_size); si es menor que el límite de la marca, es el que se anuncia.
     */
    public function __construct(
        private readonly Config $config,
        private readonly Catalog $catalog,
        private readonly LandscapeLibrary $library,
        private readonly BookmarkMaker $maker,
        private readonly BookmarkStore $store,
        private readonly RateLimiter $limiter,
        private readonly View $view,
        private readonly Limits $limits,
        private readonly \Closure $clock,
        private readonly \Closure $isUploadedFile,
        private readonly int $serverUploadLimit = PHP_INT_MAX,
    ) {
    }

    public static function create(Config $config, string $appDirectory): self
    {
        $limits = new Limits();
        $bookmarks = $config->storageDirectory . '/marcalibros';
        $library = new LandscapeLibrary($config->projectRoot . '/images');

        return new self(
            $config,
            new Catalog($config->projectRoot . '/catalog/manifest.json'),
            $library,
            new BookmarkMaker($bookmarks, $library, $limits),
            new BookmarkStore($bookmarks),
            new RateLimiter($config->storageDirectory . '/intentos'),
            new View($appDirectory . '/templates', ['retentionDays' => $config->retentionDays]),
            $limits,
            static fn (): int => time(),
            static fn (string $path): bool => is_uploaded_file($path),
            self::serverUploadLimitFromIni(),
        );
    }

    /** El menor límite positivo entre upload_max_filesize y post_max_size; PHP_INT_MAX si no hay ninguno. */
    private static function serverUploadLimitFromIni(): int
    {
        $limits = array_filter([
            Request::iniBytes((string) ini_get('upload_max_filesize')),
            Request::iniBytes((string) ini_get('post_max_size')),
        ], static fn (int $bytes): bool => $bytes > 0);
        return $limits === [] ? PHP_INT_MAX : min($limits);
    }

    /** Tamaño máximo que se puede subir de verdad: el de la marca o el del servidor, el que sea menor. */
    private function uploadLimit(): int
    {
        return min($this->limits->maxBytes, $this->serverUploadLimit);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->route($request);
        } catch (\Throwable $e) {
            error_log('marcalibros: ' . $e::class . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            try {
                $response = $this->errorPage(500, 'Algo falló en el servidor', 'No se pudo completar la acción. Inténtalo de nuevo en unos minutos.');
            } catch (\Throwable) {
                // Ni siquiera la página de error se pudo armar (por ejemplo, falta una plantilla).
                $response = Response::text('Algo falló en el servidor. Inténtalo de nuevo en unos minutos.', 500);
            }
        }
        return $response->withDefaultHeaders(self::SECURITY_HEADERS);
    }

    // ------------------------------------------------------------------ rutas

    private function route(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $path = $request->path;

        if ($path === '/') {
            return $method === 'GET' ? $this->catalogPage($request) : $this->notAllowed('GET, HEAD');
        }
        if ($path === '/crear') {
            return match ($method) {
                'GET' => $this->createForm(),
                'POST' => $this->createBookmark($request),
                default => $this->notAllowed('GET, HEAD, POST'),
            };
        }
        if (preg_match('#^/marcalibro/([a-z0-9-]+)\z#', $path, $m) === 1) {
            return $method === 'GET' ? $this->detailPage($m[1]) : $this->notAllowed('GET, HEAD');
        }
        if (preg_match('#^/catalogo/([a-z0-9-]+)\.svg\z#', $path, $m) === 1) {
            return $method === 'GET' ? $this->catalogSvg($request, $m[1]) : $this->notAllowed('GET, HEAD');
        }
        if (preg_match('#^/paisajes/([a-z0-9.-]+)\z#', $path, $m) === 1) {
            return $method === 'GET' ? $this->landscape($request, $m[1]) : $this->notAllowed('GET, HEAD');
        }
        if ($path === '/marca.svg') {
            return $method === 'GET'
                ? $this->staticFile($request, $this->config->projectRoot . '/svg/icon-marca.svg', 'image/svg+xml')
                : $this->notAllowed('GET, HEAD');
        }
        if (preg_match('#^/mio/([^/]+)\z#', $path, $m) === 1) {
            return $method === 'GET' ? $this->minePage($m[1]) : $this->notAllowed('GET, HEAD');
        }
        if (preg_match('#^/mio/([^/]+)/(1x|2x)\.jpg\z#', $path, $m) === 1) {
            return $method === 'GET' ? $this->mineImage($request, $m[1], $m[2]) : $this->notAllowed('GET, HEAD');
        }
        if (preg_match('#^/mio/([^/]+)/eliminar\z#', $path, $m) === 1) {
            return $method === 'POST' ? $this->deleteMine($request, $m[1]) : $this->notAllowed('POST');
        }

        return $this->notFound();
    }

    // --------------------------------------------------------------- catálogo

    private function catalogPage(Request $request): Response
    {
        $slug = $request->param('categoria');
        if (isset($request->query['categoria']) && ($slug === null || !$this->catalog->hasCategory($slug))) {
            return $this->notFound('No encontramos esa categoría.');
        }

        $families = [];
        foreach ($this->catalog->categories() as $category) {
            $families[(string) $category['familia']][] = $category;
        }
        $notice = $request->param('aviso') === 'borrado' ? 'Se eliminó tu marcalibro.' : null;
        $options = ['scripts' => ['catalogo.js'], 'section' => 'catalogo'];

        if ($slug === null) {
            // Portada: unas muestras de cada categoría, para recorrerlas todas sin pasar 20 páginas.
            $groups = [];
            foreach ($this->catalog->categories() as $category) {
                $sample = array_slice($this->catalog->byCategory((string) $category['slug']), 0, self::SAMPLE_PER_CATEGORY);
                $groups[] = ['category' => $category, 'items' => array_map($this->presentEntry(...), $sample)];
            }
            return Response::html($this->view->page('catalogo', [
                'groups' => $groups,
                'families' => $families,
                'current' => null,
                'motifs' => [],
                'items' => [],
                'page' => 1,
                'pages' => 1,
                'total' => $this->catalog->count(),
                'catalogTotal' => $this->catalog->count(),
                'categoryCount' => count($this->catalog->categories()),
                'notice' => $notice,
            ], 'Marcalibros', $options + ['description' => 'Marcalibros para descargar: ' . $this->catalog->count() . ' diseños de movimientos literarios y tipos de texto, o crea el tuyo con tu imagen.']));
        }

        $all = $this->catalog->byCategory($slug);
        $pages = max(1, (int) ceil(count($all) / self::PER_PAGE));
        $requested = $request->param('pagina');
        $page = $requested !== null && ctype_digit($requested) ? max(1, min($pages, (int) $requested)) : 1;
        $items = array_map($this->presentEntry(...), array_slice($all, ($page - 1) * self::PER_PAGE, self::PER_PAGE));
        $current = $this->categoryBySlug($slug);

        return Response::html($this->view->page('catalogo', [
            'groups' => [],
            'families' => $families,
            'current' => $current,
            'motifs' => array_map(fn ($motif): string => $this->catalog->motifName((string) $motif), $current['motivos'] ?? []),
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => count($all),
            'catalogTotal' => $this->catalog->count(),
            'categoryCount' => count($this->catalog->categories()),
            'notice' => $notice,
        ], ($current['nombre'] ?? 'Catálogo') . ' · Marcalibros', $options));
    }

    private function detailPage(string $id): Response
    {
        if (!$this->catalog->has($id)) {
            return $this->notFound('Ese marcalibro no existe en el catálogo.');
        }
        $entry = $this->catalog->get($id);
        $slug = (string) $entry['categoria_slug'];
        $siblings = array_column($this->catalog->byCategory($slug), 'id');
        $position = (int) array_search($id, $siblings, true);

        $item = $this->presentEntry($entry);
        return Response::html($this->view->page('detalle', [
            'item' => $item,
            'colors' => array_values(array_unique(array_merge([(string) $entry['fondo']], array_map('strval', $entry['colores'])))),
            'authors' => array_map('strval', $entry['autores_inspiracion'] ?? []),
            'previous' => $position > 0 ? $siblings[$position - 1] : null,
            'next' => $position < count($siblings) - 1 ? $siblings[$position + 1] : null,
            'position' => $position + 1,
            'inCategory' => count($siblings),
            'listPage' => intdiv($position, self::PER_PAGE) + 1,
        ], $item['motif'] . ' · ' . $item['category'], ['section' => 'catalogo']));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function presentEntry(array $entry): array
    {
        $motif = $this->catalog->motifName((string) $entry['motivo']);
        return [
            'id' => (string) $entry['id'],
            'motif' => $motif,
            'category' => (string) $entry['categoria'],
            'categorySlug' => (string) $entry['categoria_slug'],
            'family' => (string) $entry['familia'],
            'alt' => 'Marcalibro de ' . $entry['categoria'] . ': ' . mb_strtolower($motif),
        ];
    }

    /** @return array<string, mixed>|null */
    private function categoryBySlug(string $slug): ?array
    {
        foreach ($this->catalog->categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }
        return null;
    }

    private function catalogSvg(Request $request, string $id): Response
    {
        if (!$this->catalog->has($id)) {
            return $this->notFound();
        }
        $path = $this->config->projectRoot . '/' . $this->catalog->get($id)['svg'];
        $headers = ['Content-Security-Policy' => "default-src 'none'; sandbox"];
        if ($request->param('descargar') === '1') {
            $headers['Content-Disposition'] = 'attachment; filename="' . $id . '.svg"';
        }
        return $this->staticFile($request, $path, 'image/svg+xml', $headers);
    }

    private function landscape(Request $request, string $name): Response
    {
        try {
            $path = $this->library->path($name);
        } catch (BookmarkRejectedException) {
            return $this->notFound();
        }
        return $this->staticFile($request, $path, 'image/jpeg');
    }

    // ------------------------------------------------------------ crear el mío

    private function createForm(): Response
    {
        return $this->renderCreate();
    }

    /**
     * @param array<string, string> $values Lo que la persona había escrito, para no perderlo al corregir.
     */
    private function renderCreate(array $values = [], ?string $error = null, int $status = 200): Response
    {
        $landscapes = [];
        foreach ($this->library->names() as $name) {
            if (preg_match('/^paisaje-([a-z]+)-1920x1080\.jpg\z/', $name, $m) === 1) {
                $landscapes[$name] = self::LANDSCAPE_NAMES[$m[1]] ?? ucfirst($m[1]);
            }
        }
        $values += ['fuente' => 'subida', 'paisaje' => (string) array_key_first($landscapes), 'zoom' => '1', 'x' => '0.5', 'y' => '0.5', 'nombre' => ''];

        return Response::html($this->view->page('crear', [
            'landscapes' => $landscapes,
            'values' => $values,
            'error' => $error,
            'maxMegabytes' => max(1, intdiv($this->uploadLimit(), 1_048_576)),
            'maxBytes' => $this->uploadLimit(),
            'minCropWidth' => $this->limits->minCropWidth,
            'maxZoom' => CropSettings::MAX_ZOOM,
            'labelLength' => Label::MAX_LENGTH,
            'ratio' => ImageNormalizer::RATIO,
        ], 'Crear mi marcalibro', ['scripts' => ['crear.js'], 'section' => 'crear']), $status);
    }

    private function createBookmark(Request $request): Response
    {
        if ($request->header('sec-fetch-site') === 'cross-site') {
            return Response::text('Petición de otro sitio rechazada.', 403);
        }
        if ($request->bodyTooLarge) {
            return $this->renderCreate([], $this->tooLargeMessage(), 413);
        }

        $now = ($this->clock)();
        $subject = 'crear:' . $request->clientIp;
        if (!$this->limiter->attempt($subject, $this->config->createsPerHour, self::RATE_WINDOW, $now)) {
            $wait = $this->limiter->retryAfter($subject, self::RATE_WINDOW, $now);
            return $this->renderCreate($this->formValues($request), 'Hiciste muchos intentos seguidos. Vuelve a probar en ' . $this->waitText($wait) . '.', 429)
                ->withDefaultHeaders(['Retry-After' => (string) $wait]);
        }
        if ($this->store->count() >= $this->config->maxStored) {
            return $this->renderCreate($this->formValues($request), 'Ahora mismo no se pueden guardar más marcalibros. Vuelve a intentarlo más tarde.', 503);
        }

        $values = $this->formValues($request);
        try {
            $crop = CropSettings::fromArray($request->post);
            $label = $request->field('nombre');
            // Si el campo viene pero no es texto (por ejemplo "fuente[]"), no se adivina: se rechaza.
            $source = array_key_exists('fuente', $request->post) ? ($request->field('fuente') ?? '') : 'subida';

            if ($source === 'paisaje') {
                $bookmark = $this->maker->fromLibraryImage($request->field('paisaje') ?? '', $crop, $label);
            } elseif ($source === 'subida') {
                $bookmark = $this->maker->fromUpload($this->uploadedPath($request), $crop, $label);
            } else {
                throw new BookmarkRejectedException(RejectionReason::InvalidCrop);
            }
            $this->store->save($bookmark, $now);
        } catch (BookmarkRejectedException $e) {
            $status = $e->reason === RejectionReason::StorageFailed ? 500 : 422;
            return $this->renderCreate($values, $this->messageFor($e), $status);
        }

        return Response::redirect('/mio/' . $bookmark->id);
    }

    /** Ruta del archivo subido, o una excepción que explica qué pasó. */
    private function uploadedPath(Request $request): string
    {
        $file = $request->upload('imagen');
        $error = $file === null ? UPLOAD_ERR_NO_FILE : $file['error'];

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new BookmarkRejectedException(RejectionReason::EmptyFile);
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new BookmarkRejectedException(RejectionReason::TooLarge);
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new BookmarkRejectedException(RejectionReason::Unreadable);
        }
        // Solo se procesa lo que PHP recibió como subida: nunca una ruta que mande la persona.
        if (!($this->isUploadedFile)($file['tmp_name'])) {
            throw new BookmarkRejectedException(RejectionReason::Unreadable);
        }
        return $file['tmp_name'];
    }

    /** @return array<string, string> */
    private function formValues(Request $request): array
    {
        $values = [];
        foreach (['fuente', 'paisaje', 'zoom', 'x', 'y', 'nombre'] as $name) {
            $value = $request->field($name);
            if ($value !== null) {
                $values[$name] = mb_substr($value, 0, 200);
            }
        }
        return $values;
    }

    private function messageFor(BookmarkRejectedException $e): string
    {
        return match ($e->reason) {
            RejectionReason::EmptyFile => 'Elige una imagen para subir.',
            RejectionReason::TooLarge => $this->tooLargeMessage(),
            RejectionReason::TooSmall => 'La zona recortada mide menos de ' . $this->limits->minCropWidth
                . ' px de ancho y saldría borrosa. Baja el zoom o elige una imagen más grande.',
            RejectionReason::Unreadable => 'La subida no se completó. Elige la imagen otra vez.',
            default => $e->userMessage(),
        };
    }

    private function tooLargeMessage(): string
    {
        return 'La imagen pesa demasiado. El máximo es ' . max(1, intdiv($this->uploadLimit(), 1_048_576)) . ' MB.';
    }

    private function waitText(int $seconds): string
    {
        return $seconds >= 90 ? ceil($seconds / 60) . ' minutos' : 'un minuto';
    }

    // -------------------------------------------------------- marcalibro propio

    private function minePage(string $id): Response
    {
        $bookmark = $this->store->find($id);
        if ($bookmark === null) {
            return $this->notFound('Este marcalibro no existe o ya se borró.');
        }
        return Response::html($this->view->page('mio', [
            'bookmark' => $bookmark,
            'expires' => $bookmark['created'] + $this->config->retentionDays * 86400,
            'retentionDays' => $this->config->retentionDays,
        ], 'Tu marcalibro', ['private' => true, 'scripts' => ['mio.js'], 'section' => 'crear']), 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-cache']);
    }

    private function mineImage(Request $request, string $id, string $size): Response
    {
        $path = $this->store->imagePath($id, $size);
        if ($path === null) {
            return $this->notFound();
        }
        $headers = ['Cache-Control' => 'private, max-age=3600', 'X-Robots-Tag' => 'noindex, nofollow'];
        if ($request->param('descargar') === '1') {
            [$w, $h] = ImageNormalizer::SIZES[$size];
            $headers['Content-Disposition'] = "attachment; filename=\"marcalibro-{$w}x{$h}.jpg\"";
        }
        return $this->staticFile($request, $path, 'image/jpeg', $headers);
    }

    private function deleteMine(Request $request, string $id): Response
    {
        if ($request->header('sec-fetch-site') === 'cross-site') {
            return Response::text('Petición de otro sitio rechazada.', 403);
        }
        if (!$this->store->delete($id)) {
            return $this->notFound('Este marcalibro no existe o ya se borró.');
        }
        return Response::redirect('/?aviso=borrado');
    }

    // ----------------------------------------------------------------- auxiliares

    /** @param array<string, string> $extra */
    private function staticFile(Request $request, string $path, string $contentType, array $extra = []): Response
    {
        $size = @filesize($path);
        $time = @filemtime($path);
        if ($size === false || $time === false || !is_file($path)) {
            return $this->notFound();
        }
        $etag = '"' . dechex($size) . '-' . dechex($time) . '"';
        $headers = $extra + ['Cache-Control' => 'public, max-age=3600'];
        $headers['ETag'] = $etag;
        if ($request->header('if-none-match') === $etag) {
            return new Response(304, $headers);
        }
        return Response::file($path, $contentType, $headers);
    }

    private function notFound(string $message = 'La página que buscas no existe.'): Response
    {
        return $this->errorPage(404, 'No encontrado', $message);
    }

    private function notAllowed(string $allow): Response
    {
        return $this->errorPage(405, 'Acción no permitida', 'Esta dirección no acepta ese tipo de petición.')
            ->withDefaultHeaders(['Allow' => $allow]);
    }

    private function errorPage(int $status, string $title, string $message): Response
    {
        return Response::html($this->view->page('error', ['status' => $status, 'heading' => $title, 'message' => $message], $title), $status);
    }
}
