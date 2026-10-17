<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\Application;
use MarcalibrosApp\BookmarkStore;
use MarcalibrosApp\Config;
use MarcalibrosApp\RateLimiter;
use MarcalibrosApp\Request;
use MarcalibrosApp\Response;
use MarcalibrosApp\View;
use Marcalibros\BookmarkMaker;
use Marcalibros\Catalog;
use Marcalibros\LandscapeLibrary;
use Marcalibros\Limits;
use PHPUnit\Framework\TestCase;

/** Base de las pruebas: una carpeta temporal para el almacén y una aplicación armada con el catálogo real. */
abstract class AppTestCase extends TestCase
{
    protected string $dir;
    protected int $now = 1_800_000_000;
    /** @var array<string, bool> Rutas que el servidor "recibió como subida". */
    protected array $uploaded = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marcalibros-app-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
        $this->uploaded = [];
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "$dir/$item";
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function config(int $createsPerHour = 10, int $maxStored = 5000, int $retentionDays = 30, ?string $publicUrl = null, ?string $projectRoot = null): Config
    {
        return new Config($projectRoot ?? $this->projectRoot(), $this->dir . '/almacen', $retentionDays, $createsPerHour, $maxStored, publicUrl: $publicUrl);
    }

    /**
     * Una copia liviana del proyecto (enlaces al catálogo real) con PNG falsos para los ids dados,
     * así las pruebas no dependen de que se hayan generado los PNG de verdad.
     *
     * @param list<string> $pngIds
     */
    protected function projectWithPng(array $pngIds): string
    {
        $root = $this->dir . '/proyecto';
        mkdir("$root/catalog/png", 0755, true);
        foreach (['catalog/manifest.json', 'catalog/svg', 'catalog/preview', 'images', 'svg'] as $part) {
            symlink($this->projectRoot() . "/$part", "$root/$part");
        }
        foreach ($pngIds as $id) {
            foreach (['', '@2x'] as $suffix) {
                $image = imagecreatetruecolor(14, 2);
                imagepng($image, "$root/catalog/png/$id$suffix.png");
            }
        }
        return $root;
    }

    protected function app(?Config $config = null, int $serverUploadLimit = PHP_INT_MAX): Application
    {
        $config ??= $this->config();
        $limits = new Limits();
        $bookmarks = $config->storageDirectory . '/marcalibros';
        $library = new LandscapeLibrary($config->projectRoot . '/images');

        return new Application(
            $config,
            new Catalog($config->projectRoot . '/catalog/manifest.json'),
            $library,
            new BookmarkMaker($bookmarks, $library, $limits),
            new BookmarkStore($bookmarks),
            new RateLimiter($config->storageDirectory . '/intentos'),
            new View(dirname(__DIR__) . '/templates', ['retentionDays' => $config->retentionDays, 'publicUrl' => $config->publicUrl]),
            $limits,
            fn (): int => $this->now,
            fn (string $path): bool => $this->uploaded[$path] ?? false,
            $serverUploadLimit,
        );
    }

    /** @param array<string, mixed> $query */
    protected function get(Application $app, string $path, array $query = [], array $headers = []): Response
    {
        return $app->handle(new Request('GET', $path, $query, headers: $headers));
    }

    /** @param array<string, mixed> $post @param array<string, mixed> $files @param array<string, string> $headers */
    protected function post(Application $app, string $path, array $post = [], array $files = [], string $ip = '203.0.113.7', array $headers = [], bool $tooLarge = false): Response
    {
        return $app->handle(new Request('POST', $path, [], $post, $files, $ip, $headers, $tooLarge));
    }

    /** Crea un JPEG de colores en una carpeta temporal y lo marca como "subido". @return array{imagen: array<string, mixed>} */
    protected function upload(int $width = 2000, int $height = 1000, string $format = 'jpeg', bool $asUploaded = true): array
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 42, 56));
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($image, 201, 139, 58));
        $path = $this->dir . '/subida-' . bin2hex(random_bytes(4));
        match ($format) {
            'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
        };
        $this->uploaded[$path] = $asUploaded;
        return ['imagen' => ['name' => 'foto.' . $format, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($path)]];
    }

    protected function assertRedirectsTo(string $pattern, Response $response): void
    {
        $this->assertSame(303, $response->status);
        $this->assertMatchesRegularExpression($pattern, $response->headers['Location'] ?? '');
    }
}
