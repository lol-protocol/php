<?php
declare(strict_types=1);

/**
 * Privacy Laws API (read-only). See API.md.
 *
 *   ?action=countries               countries with law counts (+ dataset version)
 *   ?action=bundle                  the whole dataset, same file the web app loads
 *   ?action=country&tld=us          laws of one country (JSON)
 *   ?action=country_info&tld=us     country metadata
 *   ?action=country_csv&tld=us      laws of one country (CSV)
 *   ?action=texts&tld=us            cached reference texts available for a country
 *   ?action=text&tld=us&slug=...    one cached reference text (Markdown / plain text)
 *
 * Every response carries ETag + Last-Modified, so clients revalidate with a
 * cheap 304 instead of downloading the data again, and is gzip-compressed.
 */

const DATA_DIR = __DIR__ . '/countries';
const BUNDLE_KEY = 'countries';
const CACHE_MAX_AGE = 300;
const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, If-None-Match, If-Modified-Since');
header('X-Content-Type-Options: nosniff');

function fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_FLAGS);
    exit;
}

function tldParam(): string
{
    $tld = strtolower((string) ($_GET['tld'] ?? ''));
    if (!preg_match('/^[a-z]{2}$/', $tld)) {
        fail(400, 'tld must be a 2-letter country code, e.g. tld=us');
    }
    return $tld;
}

function slugParam(): string
{
    $slug = (string) ($_GET['slug'] ?? '');
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $slug)) {
        fail(400, 'slug must be lowercase letters, digits and hyphens');
    }
    return $slug;
}

function existingFile(string $path, string $notFound): string
{
    if (!is_file($path)) {
        fail(404, $notFound);
    }
    return $path;
}

/** Sends validators and short-circuits with 304 when the client copy is still good. */
function conditional(string $seed, int $mtime): void
{
    $etag = 'W/"' . $seed . '"';
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: public, max-age=' . CACHE_MAX_AGE);
    header('Vary: Accept-Encoding');

    $fresh = false;
    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
    if ($ifNoneMatch !== null) {
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if (preg_replace('#^W/#', '', trim($candidate)) === preg_replace('#^W/#', '', $etag)) {
                $fresh = true;
                break;
            }
        }
    } elseif (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        $fresh = $since !== false && $since >= $mtime;
    }
    if ($fresh) {
        http_response_code(304);
        exit;
    }
}

/** Sends a file, or a body derived from it (`$prefix . file . $suffix`), with caching + gzip. */
function sendFile(string $path, string $contentType, string $prefix = '', string $suffix = ''): never
{
    $mtime = (int) filemtime($path);
    conditional(dechex($mtime) . '-' . dechex((int) filesize($path)) . '-' . dechex(crc32($prefix . $suffix)), $mtime);
    header('Content-Type: ' . $contentType);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    if (function_exists('ob_gzhandler')) {
        ob_start('ob_gzhandler');
    }
    echo $prefix;
    readfile($path);
    echo $suffix;
    exit;
}

function sendJsonFile(string $path): never
{
    sendFile($path, 'application/json; charset=utf-8');
}

function sendWrappedJsonFile(string $path): never
{
    sendFile($path, 'application/json; charset=utf-8', '{"data":', '}');
}

function sendJsonPayload(string $sourcePath, array $payload): never
{
    $mtime = (int) filemtime($sourcePath);
    conditional(dechex($mtime) . '-' . dechex((int) filesize($sourcePath)) . '-list', $mtime);
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    if (function_exists('ob_gzhandler')) {
        ob_start('ob_gzhandler');
    }
    echo json_encode($payload, JSON_FLAGS);
    exit;
}

function bundlePath(): string
{
    return existingFile(DATA_DIR . '/index.json', 'Dataset not built yet. Run: npm run build');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD, OPTIONS');
    fail(405, 'Only GET, HEAD and OPTIONS are supported');
}

$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    case 'countries':
        $path = bundlePath();
        $bundle = json_decode((string) file_get_contents($path), true);
        $list = array_map(
            static fn(array $c): array => [
                'code' => $c['code'],
                'name' => $c['name'],
                'tld' => $c['tld'],
                'region' => $c['region'],
                'lawCount' => $c['lawCount'],
            ],
            $bundle[BUNDLE_KEY]
        );
        sendJsonPayload($path, [
            'data' => $list,
            'meta' => ['version' => $bundle['version'], 'generatedAt' => $bundle['generatedAt']],
        ]);

    case 'bundle':
        sendJsonFile(bundlePath());

    case 'country':
        $tld = tldParam();
        sendWrappedJsonFile(existingFile(DATA_DIR . "/$tld/laws.json", "No data for country: $tld"));

    case 'country_info':
        $tld = tldParam();
        sendWrappedJsonFile(existingFile(DATA_DIR . "/$tld/info.json", "No data for country: $tld"));

    case 'country_csv':
        $tld = tldParam();
        sendFile(existingFile(DATA_DIR . "/$tld/laws.csv", "No data for country: $tld"), 'text/csv; charset=utf-8');

    case 'texts':
        $tld = tldParam();
        $index = DATA_DIR . "/$tld/texts/index.json";
        if (!is_file($index)) {
            if (!is_dir(DATA_DIR . "/$tld")) {
                fail(404, "No data for country: $tld");
            }
            header('Content-Type: application/json; charset=utf-8');
            echo '{"data":[]}';
            exit;
        }
        sendFile($index, 'application/json; charset=utf-8', '{"data":', '}');

    case 'text':
        $tld = tldParam();
        $slug = slugParam();
        foreach (['md' => 'text/markdown; charset=utf-8', 'txt' => 'text/plain; charset=utf-8'] as $ext => $type) {
            $file = DATA_DIR . "/$tld/texts/$slug.$ext";
            if (is_file($file)) {
                sendFile($file, $type);
            }
        }
        fail(404, "No cached text for $tld/$slug");

    default:
        fail(400, 'Unknown action. Use: countries, bundle, country, country_info, country_csv, texts, text');
}
