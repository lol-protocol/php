#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Caches the text of every law's reference page (linked_resources) as Markdown or
 * plain text. Only the readable text is stored, never the original HTML.
 *
 *   php scripts/cache-texts.php                    every jurisdiction
 *   php scripts/cache-texts.php --only=us,br       only these jurisdictions (tld folders)
 *   php scripts/cache-texts.php --dry-run          list what would be fetched, no network
 *   php scripts/cache-texts.php --force            refetch even if the page did not change
 *   php scripts/cache-texts.php --format=txt       plain text instead of Markdown
 *   php scripts/cache-texts.php --allow-private-hosts   also fetch loopback/private addresses (refused by default)
 *   php scripts/cache-texts.php --max-kb=400 --delay=1 --timeout=20
 *   php scripts/cache-texts.php --convert < page.html     HTML on stdin → Markdown on stdout
 *
 * Output, next to each jurisdiction's data:
 *   jurisdictions/{tld}/texts/{slug}.md    the text (front matter: source, law, title, fetched)
 *   jurisdictions/{tld}/texts/index.json   what is cached + validators for incremental refresh
 *
 * One GET per reference URL, with a pause between requests. Pages that need
 * JavaScript, PDFs and other non-text content are skipped and reported.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/lib/html_to_text.php';

foreach (['curl', 'dom', 'mbstring', 'intl'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Error: the PHP extension \"$extension\" is required (curl, dom, mbstring, intl).\n");
        exit(2);
    }
}

const BUNDLE_KEY = 'jurisdictions';
const URL_FIELD = 'linked_resources';
const USER_AGENT = 'copyright-laws-text-cache/1.0 (+https://github.com/lol-protocol/php; one request per reference URL)';
const MAX_DOWNLOAD_BYTES = 5 * 1024 * 1024;
const MIN_TEXT_CHARS = 200;

/** Data folder; LAWS_DATA_DIR lets tests (or another checkout) point elsewhere. */
function dataDir(): string
{
    return rtrim(getenv('LAWS_DATA_DIR') ?: __DIR__ . '/../jurisdictions', '/');
}

function usage(string $message = ''): never
{
    if ($message !== '') {
        fwrite(STDERR, "Error: $message\n\n");
    }
    $doc = file(__FILE__, FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($doc, 5) as $line) {
        if (trim($line) === '*/') {
            break;
        }
        fwrite(STDERR, preg_replace('#^ \* ?#', '', $line) . "\n");
    }
    exit($message === '' ? 0 : 2);
}

/** @return array{only: list<string>, dryRun: bool, force: bool, format: string, maxKb: int, delay: float, timeout: int, convert: bool, allowPrivate: bool} */
function parseOptions(array $argv): array
{
    $options = ['only' => [], 'dryRun' => false, 'force' => false, 'format' => 'md', 'maxKb' => 400, 'delay' => 1.0, 'timeout' => 20, 'convert' => false, 'allowPrivate' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            usage();
        } elseif ($arg === '--dry-run') {
            $options['dryRun'] = true;
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } elseif ($arg === '--convert') {
            $options['convert'] = true;
        } elseif ($arg === '--allow-private-hosts') {
            $options['allowPrivate'] = true;
        } elseif (preg_match('/^--only=(.+)$/', $arg, $m)) {
            $options['only'] = array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $m[1])))));
            foreach ($options['only'] as $tld) {
                if (!preg_match('/^[a-z]{2}$/', $tld)) {
                    usage("--only expects 2-letter folders, got \"$tld\"");
                }
            }
        } elseif (preg_match('/^--format=(md|txt)$/', $arg, $m)) {
            $options['format'] = $m[1];
        } elseif (preg_match('/^--max-kb=(\d{1,5})$/', $arg, $m)) {
            $options['maxKb'] = max(10, (int) $m[1]);
        } elseif (preg_match('/^--delay=(\d+(?:\.\d+)?)$/', $arg, $m)) {
            $options['delay'] = (float) $m[1];
        } elseif (preg_match('/^--timeout=(\d{1,3})$/', $arg, $m)) {
            $options['timeout'] = max(1, (int) $m[1]);
        } else {
            usage("unknown option $arg");
        }
    }
    return $options;
}

function slugify(string $text): string
{
    // Without intl, accented letters would turn into hyphens and file names would differ between hosts.
    $text = Transliterator::create('Any-Latin; Latin-ASCII; Lower()')?->transliterate($text) ?: $text;
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    return substr($slug, 0, 80) ?: 'law';
}

function now(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function writeAtomic(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create $dir");
    }
    $tmp = $path . '.tmp' . getmypid();
    if (file_put_contents($tmp, $contents) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Cannot write $path");
    }
}

/** One request, no redirect handling. @return array{status: int, headers: array<string,string>, body: string, error: ?string, finalUrl: string} */
function fetchOnce(string $url, array $conditional, int $timeout, ?string $pinnedIp): array
{
    $headers = [];
    $body = '';
    $tooBig = false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array_merge(
            ['Accept: text/html,application/xhtml+xml,text/plain;q=0.8,*/*;q=0.5', 'Accept-Language: en,*;q=0.5'],
            $conditional
        ),
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            if (str_starts_with($line, 'HTTP/')) {
                $headers = [];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooBig): int {
            $body .= $chunk;
            if (strlen($body) > MAX_DOWNLOAD_BYTES) {
                $tooBig = true;
                return 0;
            }
            return strlen($chunk);
        },
    ]);
    if ($pinnedIp !== null) {
        // Connect to the address that was just checked, so DNS cannot change its answer in between.
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80);
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$parts['host']}:$port:$pinnedIp"]);
    }
    $ca = getenv('CURL_CA_BUNDLE') ?: getenv('SSL_CERT_FILE');
    if ($ca && is_readable($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }

    curl_exec($ch);
    $result = [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'headers' => $headers,
        'body' => $body,
        'error' => $tooBig ? 'page larger than ' . (MAX_DOWNLOAD_BYTES / 1048576) . ' MB' : (curl_errno($ch) ? curl_error($ch) : null),
        'finalUrl' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
    ];
    curl_close($ch);
    return $result;
}

/** Loopback, private, link-local and other reserved addresses. */
function isPrivateAddress(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/** @return list<string> every address the host resolves to */
function resolveHost(string $host): array
{
    $host = trim($host, '[]');
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return [$host];
    }
    $ips = [];
    foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
        $ips[] = $record['ip'] ?? $record['ipv6'] ?? '';
    }
    foreach (@gethostbynamel($host) ?: [] as $ip) {
        $ips[] = $ip;
    }
    return array_values(array_unique(array_filter($ips)));
}

/** Absolute URL for a Location header relative to the URL that sent it. */
function resolveRedirect(string $base, string $location): string
{
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
        return $location;
    }
    $parts = parse_url($base);
    $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($location, '//')) {
        return ($parts['scheme'] ?? 'http') . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }
    $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');
    return $origin . $dir . $location;
}

/**
 * GET with up to five redirects. Every hop is checked: only http(s), and (unless
 * allowed) never a loopback/private address, so a reference URL cannot be used to
 * read internal services and publish the answer through the API.
 *
 * @return array{status: int, headers: array<string,string>, body: string, error: ?string, finalUrl: string}
 */
function fetchUrl(string $url, array $conditional, int $timeout, bool $allowPrivate = false): array
{
    for ($hop = 0; $hop <= 5; $hop++) {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'only http(s) URLs are fetched', 'finalUrl' => $url];
        }
        $pinned = null;
        if (!$allowPrivate) {
            $ips = resolveHost($parts['host']);
            if (!$ips) {
                return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'host does not resolve', 'finalUrl' => $url];
            }
            foreach ($ips as $ip) {
                if (isPrivateAddress($ip)) {
                    return ['status' => 0, 'headers' => [], 'body' => '', 'error' => "refusing to fetch a private address ($ip); use --allow-private-hosts to override", 'finalUrl' => $url];
                }
            }
            $pinned = $ips[0];
        }

        $response = fetchOnce($url, $conditional, $timeout, $pinned);
        $location = $response['headers']['location'] ?? '';
        if ($response['error'] !== null || $location === '' || !in_array($response['status'], [301, 302, 303, 307, 308], true)) {
            return $response;
        }
        $url = resolveRedirect($url, $location);
    }
    return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'too many redirects', 'finalUrl' => $url];
}

/** @return array{text: string, title: string}|string  the text, or the reason it was skipped */
function extractText(string $body, array $headers): array|string
{
    $contentType = strtolower($headers['content-type'] ?? '');
    $charset = preg_match('/charset\s*=\s*["\']?([\w\-:.]+)/i', $contentType, $m) ? $m[1] : null;

    if (str_contains($contentType, 'pdf')) {
        return 'PDF documents are not converted';
    }
    $looksHtml = str_contains($contentType, 'html') || ($contentType === '' && preg_match('/^\s*</', $body));
    if ($looksHtml) {
        $page = HtmlToText::convert(HtmlToText::toUtf8($body, $charset));
        return ['text' => $page['markdown'], 'title' => $page['title']];
    }
    if (str_starts_with($contentType, 'text/plain')) {
        return ['text' => trim(HtmlToText::toUtf8($body, $charset)), 'title' => ''];
    }
    return 'unsupported content type: ' . ($contentType ?: 'unknown');
}

function limitText(string $text, int $maxKb): string
{
    $limit = $maxKb * 1024;
    if (strlen($text) <= $limit) {
        return $text;
    }
    $cut = substr($text, 0, $limit);
    $paragraph = strrpos($cut, "\n\n");
    return rtrim($paragraph !== false && $paragraph > $limit / 2 ? substr($cut, 0, $paragraph) : mb_strcut($cut, 0, $limit)) . "\n\n[… truncated]";
}

function renderFile(string $format, string $text, array $meta): string
{
    $json = static fn(string $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($format === 'txt') {
        $plain = preg_replace('/^#{1,6}\s+/m', '', $text);
        return "{$meta['law']}\nSource: {$meta['source']}\nFetched: {$meta['fetched']}\n\n$plain\n";
    }
    return "---\nsource: {$json($meta['source'])}\nlaw: {$json($meta['law'])}\ntitle: {$json($meta['title'])}\nfetched: {$json($meta['fetched'])}\n---\n\n$text\n";
}

/** Cached text files are only ever named like `some-slug.md`: never trust a path read from index.json. */
function isCacheFileName(mixed $name): bool
{
    return is_string($name) && preg_match('/\A[a-z0-9][a-z0-9-]*\.(md|txt)\z/', $name) === 1;
}

/** @return array<string, array<string,mixed>> index entries by slug */
function loadIndex(string $dir): array
{
    $file = "$dir/index.json";
    $entries = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $bySlug = [];
    foreach (is_array($entries) ? $entries : [] as $entry) {
        if (isset($entry['slug'])) {
            $bySlug[$entry['slug']] = $entry;
        }
    }
    return $bySlug;
}

function main(array $argv): int
{
    $options = parseOptions($argv);

    if ($options['convert']) {
        $page = HtmlToText::convert(HtmlToText::toUtf8((string) stream_get_contents(STDIN)));
        echo $page['markdown'], "\n";
        return 0;
    }

    $bundleFile = dataDir() . '/index.json';
    $bundle = is_file($bundleFile) ? json_decode((string) file_get_contents($bundleFile), true) : null;
    if (!is_array($bundle) || !isset($bundle[BUNDLE_KEY])) {
        usage('jurisdictions/index.json not found. Run: npm run build');
    }

    $known = array_column($bundle[BUNDLE_KEY], 'tld');
    foreach ($options['only'] as $tld) {
        if (!in_array($tld, $known, true)) {
            usage("unknown jurisdiction folder \"$tld\"");
        }
    }

    $ext = $options['format'];
    $totals = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0];
    $problems = [];
    $first = true;

    foreach ($bundle[BUNDLE_KEY] as $country) {
        $tld = $country['tld'];
        if ($options['only'] && !in_array($tld, $options['only'], true)) {
            continue;
        }
        $dir = dataDir() . "/$tld/texts";
        $previous = loadIndex($dir);
        $entries = [];
        $used = [];

        foreach ($country['laws'] as $law) {
            $url = (string) ($law[URL_FIELD] ?? '');
            if (!preg_match('#^https?://#i', $url)) {
                continue;
            }
            $base = slugify($law['law_name']);
            $slug = $base;
            for ($n = 2; isset($used[$slug]); $n++) {
                $slug = "$base-$n";
            }
            $used[$slug] = true;
            $label = "$tld/$slug";

            if ($options['dryRun']) {
                echo "[plan] $label.$ext  <-  $url\n";
                continue;
            }
            if (!$first && $options['delay'] > 0) {
                usleep((int) ($options['delay'] * 1_000_000));
            }
            $first = false;

            $old = $previous[$slug] ?? null;
            $haveFile = $old !== null && ($old['status'] ?? '') === 'ok' && isCacheFileName($old['file'] ?? null) && is_file("$dir/{$old['file']}");
            // Validators only make sense for the same URL and the same output file; otherwise a 304 would keep stale text.
            $reusable = $haveFile && ($old['source'] ?? '') === $url && $old['file'] === "$slug.$ext";
            $conditional = [];
            if ($reusable && !$options['force']) {
                if (!empty($old['etag'])) {
                    $conditional[] = 'If-None-Match: ' . $old['etag'];
                }
                if (!empty($old['lastModified'])) {
                    $conditional[] = 'If-Modified-Since: ' . $old['lastModified'];
                }
            }

            $response = fetchUrl($url, $conditional, $options['timeout'], $options['allowPrivate']);
            $stamp = now();
            $failure = null;
            $skipReason = null;
            $text = '';
            $title = '';

            if ($response['error'] !== null || $response['status'] === 0) {
                $failure = $response['error'] ?? 'no response';
            } elseif ($response['status'] === 304 && $reusable) {
                $entries[$slug] = ['checkedAt' => $stamp] + $old;
                $totals['unchanged']++;
                echo "[same] $label  (304 not modified)\n";
                continue;
            } elseif ($response['status'] < 200 || $response['status'] >= 300) {
                $failure = 'HTTP ' . $response['status'];
            } else {
                $extracted = extractText($response['body'], $response['headers']);
                if (is_string($extracted)) {
                    $skipReason = $extracted;
                } else {
                    $text = limitText($extracted['text'], $options['maxKb']);
                    $title = $extracted['title'];
                    if (mb_strlen($text) < MIN_TEXT_CHARS) {
                        $skipReason = 'no readable text (the page probably needs JavaScript)';
                    }
                }
            }

            if ($failure !== null || $skipReason !== null) {
                $reason = $failure ?? $skipReason;
                $totals[$failure !== null ? 'failed' : 'skipped']++;
                $problems[] = "$label: $reason";
                echo '[' . ($failure !== null ? 'fail' : 'skip') . "] $label  $reason\n";
                $entries[$slug] = $haveFile
                    ? ['checkedAt' => $stamp, 'lastError' => $reason] + $old
                    : ['slug' => $slug, 'law' => $law['law_name'], 'source' => $url, 'status' => $failure !== null ? 'error' : 'skipped', 'error' => $reason, 'checkedAt' => $stamp];
                continue;
            }

            $hash = hash('sha256', $text);
            $unchanged = $haveFile && ($old['sha256'] ?? '') === $hash && ($old['file'] ?? '') === "$slug.$ext";
            $file = "$slug.$ext";
            if (!$unchanged) {
                if ($old !== null && isCacheFileName($old['file'] ?? null) && $old['file'] !== $file) {
                    @unlink("$dir/{$old['file']}"); // format changed: keep a single file per law
                }
                writeAtomic("$dir/$file", renderFile($ext, $text, [
                    'source' => $url,
                    'law' => $law['law_name'],
                    'title' => $title,
                    'fetched' => $stamp,
                ]));
            }
            $entries[$slug] = [
                'slug' => $slug,
                'law' => $law['law_name'],
                'source' => $url,
                'file' => $file,
                'status' => 'ok',
                'fetchedAt' => $unchanged ? ($old['fetchedAt'] ?? $stamp) : $stamp,
                'checkedAt' => $stamp,
                'etag' => $response['headers']['etag'] ?? null,
                'lastModified' => $response['headers']['last-modified'] ?? null,
                'sha256' => $hash,
                'chars' => mb_strlen($text),
            ];
            $kind = $unchanged ? 'unchanged' : ($haveFile ? 'updated' : 'new');
            $totals[$kind]++;
            printf("[%s] %s  %.1f KB%s\n", $unchanged ? 'same' : 'ok', $label, strlen($text) / 1024, $unchanged ? '  (text identical)' : ($haveFile ? '  (updated)' : ''));
        }

        if (!$options['dryRun'] && $entries) {
            $sorted = array_values($entries);
            usort($sorted, static fn(array $a, array $b): int => strcmp($a['slug'], $b['slug']));
            writeAtomic("$dir/index.json", json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

            // A law that left the dataset must not stay downloadable through the API.
            $kept = array_column($sorted, 'file');
            foreach (glob("$dir/*") ?: [] as $path) {
                $name = basename($path);
                if (isCacheFileName($name) && !in_array($name, $kept, true)) {
                    @unlink($path);
                    echo "[gone] $tld/$name  (no longer in the dataset)\n";
                }
            }
        }
    }

    if (!$options['dryRun']) {
        echo sprintf(
            "\nDone: %d new, %d updated, %d unchanged, %d skipped, %d failed\n",
            $totals['new'], $totals['updated'], $totals['unchanged'], $totals['skipped'], $totals['failed']
        );
    }
    return 0;
}

exit(main($argv));
