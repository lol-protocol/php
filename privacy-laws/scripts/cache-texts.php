#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Caches the text of every law's reference page (website_url) as Markdown or
 * plain text. Only the readable text is stored, never the original HTML.
 *
 *   php scripts/cache-texts.php                    every country
 *   php scripts/cache-texts.php --only=us,br       only these countries (tld folders)
 *   php scripts/cache-texts.php --dry-run          list what would be fetched, no network
 *   php scripts/cache-texts.php --force            refetch even if the page did not change
 *   php scripts/cache-texts.php --format=txt       plain text instead of Markdown
 *   php scripts/cache-texts.php --max-kb=400 --delay=1 --timeout=20
 *   php scripts/cache-texts.php --convert < page.html     HTML on stdin → Markdown on stdout
 *
 * Output, next to each country's data:
 *   countries/{tld}/texts/{slug}.md        the text (front matter: source, law, title, fetched)
 *   countries/{tld}/texts/index.json       what is cached + validators for incremental refresh
 *
 * One GET per reference URL, with a pause between requests. Pages that need
 * JavaScript, PDFs and other non-text content are skipped and reported.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/lib/html_to_text.php';

const BUNDLE_KEY = 'countries';
const URL_FIELD = 'website_url';
const USER_AGENT = 'privacy-laws-text-cache/1.0 (+https://github.com/lol-protocol/php; one request per reference URL)';
const MAX_DOWNLOAD_BYTES = 5 * 1024 * 1024;
const MIN_TEXT_CHARS = 200;

/** Data folder; LAWS_DATA_DIR lets tests (or another checkout) point elsewhere. */
function dataDir(): string
{
    return rtrim(getenv('LAWS_DATA_DIR') ?: __DIR__ . '/../countries', '/');
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

/** @return array{only: list<string>, dryRun: bool, force: bool, format: string, maxKb: int, delay: float, timeout: int, convert: bool} */
function parseOptions(array $argv): array
{
    $options = ['only' => [], 'dryRun' => false, 'force' => false, 'format' => 'md', 'maxKb' => 400, 'delay' => 1.0, 'timeout' => 20, 'convert' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            usage();
        } elseif ($arg === '--dry-run') {
            $options['dryRun'] = true;
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } elseif ($arg === '--convert') {
            $options['convert'] = true;
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
    if (class_exists('Transliterator')) {
        $text = Transliterator::create('Any-Latin; Latin-ASCII; Lower()')?->transliterate($text) ?: $text;
    }
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

/** @return array{status: int, headers: array<string,string>, body: string, error: ?string, finalUrl: string} */
function fetchUrl(string $url, array $conditional, int $timeout): array
{
    $headers = [];
    $body = '';
    $tooBig = false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
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
        usage('countries/index.json not found. Run: npm run build');
    }

    $known = array_column($bundle[BUNDLE_KEY], 'tld');
    foreach ($options['only'] as $tld) {
        if (!in_array($tld, $known, true)) {
            usage("unknown country folder \"$tld\"");
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
            $haveFile = $old !== null && ($old['status'] ?? '') === 'ok' && is_file("$dir/{$old['file']}");
            $conditional = [];
            if ($haveFile && !$options['force']) {
                if (!empty($old['etag'])) {
                    $conditional[] = 'If-None-Match: ' . $old['etag'];
                }
                if (!empty($old['lastModified'])) {
                    $conditional[] = 'If-Modified-Since: ' . $old['lastModified'];
                }
            }

            $response = fetchUrl($url, $conditional, $options['timeout']);
            $stamp = now();
            $failure = null;
            $skipReason = null;
            $text = '';
            $title = '';

            if ($response['error'] !== null || $response['status'] === 0) {
                $failure = $response['error'] ?? 'no response';
            } elseif ($response['status'] === 304 && $haveFile) {
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
                if ($old !== null && isset($old['file']) && $old['file'] !== $file) {
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
