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
 * One GET per reference URL, with a pause between requests. robots.txt is honoured
 * (RFC 9309, including Crawl-delay up to 30 s). Pages that need JavaScript, PDFs and
 * other non-text content are skipped and reported.
 */

const BUNDLE_KEY = 'jurisdictions';
const URL_FIELD = 'linked_resources';
const USER_AGENT = 'copyright-laws-text-cache/1.0 (+https://github.com/lol-protocol/php; one request per reference URL)';
const UNIT = 'jurisdiction';
const DATA_DIR = __DIR__ . '/../jurisdictions';
const SCRIPT_FILE = __FILE__;

// The fetching, robots.txt handling and text extraction are shared with the other laws module:
// see ../../leyes-comunes/README.md. This file supplies only what is specific to this project.
require __DIR__ . '/../../leyes-comunes/cache-texts-core.php';
