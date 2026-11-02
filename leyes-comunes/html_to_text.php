<?php
declare(strict_types=1);

/**
 * Reduces an HTML page to its essential text, as Markdown: headings, paragraphs,
 * lists, tables, quotes and code. Navigation, scripts, forms, footers, dialogs
 * and hidden elements are dropped, and so are all attributes, styles, images
 * and link targets. The original HTML is never kept.
 */
final class HtmlToText
{
    private const SKIP_TAGS = [
        'script', 'style', 'noscript', 'template', 'svg', 'canvas', 'iframe', 'object', 'embed',
        'button', 'select', 'option', 'input', 'textarea', 'nav', 'header', 'footer',
        'aside', 'dialog', 'menu', 'audio', 'video', 'picture', 'img', 'map', 'head',
    ];
    private const SKIP_ROLES = ['banner', 'navigation', 'contentinfo', 'complementary', 'search', 'dialog', 'alertdialog'];
    private const HEADINGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];
    private const BLOCK_TAGS = [
        'html', 'body', 'main', 'article', 'section', 'div', 'p', 'address', 'fieldset', 'details',
        'summary', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'center', 'li', 'tr', 'td', 'th', 'form',
    ];
    /** Elements that start a new line of text even when they sit inside inline content or a table cell. */
    private const SEPARATING_TAGS = [
        'ul', 'ol', 'table', 'thead', 'tbody', 'tfoot', 'blockquote', 'pre', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];
    /** A form with at least this much text, or one that wraps <main>/<article>, is the page itself (ASP.NET style). */
    private const FORM_CONTENT_CHARS = 1000;
    private const MIN_USEFUL_CHARS = 200;

    /**
     * Expects UTF-8 (see toUtf8). Charset declarations are removed first: libxml would
     * otherwise decode the already-converted text a second time.
     *
     * @return array{title: string, markdown: string}
     */
    public static function convert(string $html): array
    {
        $html = preg_replace('/<meta\b[^>]*\bcharset\s*=[^>]*>/i', '', $html) ?? $html;
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $converter = new self();
        $title = $converter->title($dom);

        $body = $dom->getElementsByTagName('body')->item(0) ?? $dom->documentElement;
        $text = '';
        if ($body !== null) {
            $main = $converter->mainContent($dom);
            $text = $converter->render($main ?? $body);
            // A tiny <main> usually means the real content lives elsewhere on the page.
            if ($main !== null && mb_strlen($text) < self::MIN_USEFUL_CHARS) {
                $whole = $converter->render($body);
                if (mb_strlen($whole) > 2 * mb_strlen($text)) {
                    $text = $whole;
                }
            }
        }
        return ['title' => $title, 'markdown' => $text];
    }

    /** Converts to UTF-8 using the HTTP charset, a <meta> declaration, or a sensible guess. */
    public static function toUtf8(string $html, ?string $httpCharset = null): string
    {
        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html) ?? $html;
        $charset = $httpCharset;
        if ($charset === null || $charset === '') {
            if (preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([A-Za-z0-9_\-:.]+)/i', substr($html, 0, 4096), $m)) {
                $charset = $m[1];
            }
        }
        if ($charset !== null && $charset !== '' && strcasecmp($charset, 'utf-8') !== 0 && strcasecmp($charset, 'utf8') !== 0) {
            try {
                return mb_convert_encoding($html, 'UTF-8', $charset);
            } catch (ValueError) {
                // unknown charset name: fall back to detection below
            }
        }
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }
        return mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
    }

    private function title(DOMDocument $dom): string
    {
        $node = $dom->getElementsByTagName('title')->item(0) ?? $dom->getElementsByTagName('h1')->item(0);
        return $node === null ? '' : self::tidy($node->textContent ?? '');
    }

    private function mainContent(DOMDocument $dom): ?DOMNode
    {
        $xpath = new DOMXPath($dom);
        $queries = [
            '//main',
            '//*[@role="main"]',
            '//*[@id="content" or @id="main-content" or @id="main" or @id="primary" or @id="page-content"]',
        ];
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                return $nodes->item(0);
            }
        }
        // A single <article> is the content; several are a listing, so use the whole page.
        $articles = $xpath->query('//article');
        return $articles !== false && $articles->length === 1 ? $articles->item(0) : null;
    }

    private function render(DOMNode $root): string
    {
        $blocks = [];
        foreach ($this->blocks($root) as $block) {
            if (!preg_match('/[\p{L}\p{N}]/u', $block)) {
                continue;
            }
            if ($blocks !== [] && end($blocks) === $block) {
                continue;
            }
            $blocks[] = $block;
        }
        return implode("\n\n", $blocks);
    }

    private function skipped(DOMElement $element, string $name): bool
    {
        if (in_array($name, self::SKIP_TAGS, true)) {
            return true;
        }
        if ($name === 'form' && !$this->wrapsContent($element)) {
            return true; // search, login and cookie forms
        }
        if ($element->hasAttribute('hidden') || strtolower($element->getAttribute('aria-hidden')) === 'true'
            || strtolower($element->getAttribute('aria-modal')) === 'true') {
            return true;
        }
        if (in_array(strtolower($element->getAttribute('role')), self::SKIP_ROLES, true)) {
            return true;
        }
        $style = strtolower($element->getAttribute('style'));
        return $style !== '' && (bool) preg_match('/(display\s*:\s*none|visibility\s*:\s*hidden)/', $style);
    }

    private function wrapsContent(DOMElement $form): bool
    {
        return $form->getElementsByTagName('main')->length > 0
            || $form->getElementsByTagName('article')->length > 0
            || mb_strlen(trim($form->textContent)) >= self::FORM_CONTENT_CHARS;
    }

    /** @return list<string> */
    private function blocks(DOMNode $node): array
    {
        $blocks = [];
        $inline = '';
        $flush = function () use (&$blocks, &$inline): void {
            $text = self::tidy($inline);
            if ($text !== '') {
                $blocks[] = $text;
            }
            $inline = '';
        };

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $inline .= self::collapse($child->nodeValue ?? '');
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $name = strtolower($child->tagName);
            if ($this->skipped($child, $name)) {
                continue;
            }
            if ($name === 'br') {
                $inline .= "\n";
            } elseif (isset(self::HEADINGS[$name])) {
                $flush();
                $text = self::tidy($this->inlineText($child));
                if ($text !== '') {
                    $blocks[] = str_repeat('#', self::HEADINGS[$name]) . ' ' . str_replace("\n", ' ', $text);
                }
            } elseif ($name === 'ul' || $name === 'ol') {
                $flush();
                $list = $this->listBlock($child, $name === 'ol');
                if ($list !== '') {
                    $blocks[] = $list;
                }
            } elseif ($name === 'table') {
                $flush();
                $table = $this->tableBlock($child);
                if ($table !== '') {
                    $blocks[] = $table;
                }
            } elseif ($name === 'pre') {
                $flush();
                $code = trim($child->textContent ?? '', "\r\n");
                if (trim($code) !== '') {
                    $blocks[] = "```\n" . $code . "\n```";
                }
            } elseif ($name === 'blockquote') {
                $flush();
                $inner = implode("\n\n", $this->blocks($child));
                if ($inner !== '') {
                    $blocks[] = '> ' . str_replace("\n", "\n> ", $inner);
                }
            } elseif (in_array($name, self::BLOCK_TAGS, true)) {
                $flush();
                array_push($blocks, ...$this->blocks($child));
            } else {
                $inline .= $this->inlineText($child);
            }
        }
        $flush();
        return $blocks;
    }

    private function inlineText(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= self::collapse($child->nodeValue ?? '');
            } elseif ($child instanceof DOMElement) {
                $name = strtolower($child->tagName);
                if ($this->skipped($child, $name)) {
                    continue;
                }
                if ($name === 'br') {
                    $out .= "\n";
                } elseif (in_array($name, self::BLOCK_TAGS, true) || in_array($name, self::SEPARATING_TAGS, true)) {
                    $out .= ' ' . $this->inlineText($child) . ' '; // keep "Block A" and "Block B" apart
                } else {
                    $out .= $this->inlineText($child);
                }
            }
        }
        return $out;
    }

    private function listBlock(DOMElement $list, bool $ordered): string
    {
        $items = [];
        $number = 0;
        foreach ($list->childNodes as $li) {
            if (!$li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }
            $content = implode("\n", $this->blocks($li));
            if (trim($content) === '') {
                continue;
            }
            $number++;
            $marker = $ordered ? "$number. " : '- ';
            $items[] = $marker . str_replace("\n", "\n" . str_repeat(' ', strlen($marker)), $content);
        }
        return implode("\n", $items);
    }

    /** Rows of this table only: a nested table's rows belong to the cell that holds it. @return list<DOMElement> */
    private function tableRows(DOMElement $table): array
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $name = strtolower($child->tagName);
            if ($name === 'tr') {
                $rows[] = $child;
            } elseif (in_array($name, ['thead', 'tbody', 'tfoot'], true)) {
                foreach ($child->childNodes as $tr) {
                    if ($tr instanceof DOMElement && strtolower($tr->tagName) === 'tr') {
                        $rows[] = $tr;
                    }
                }
            }
        }
        return $rows;
    }

    private function tableBlock(DOMElement $table): string
    {
        $rows = [];
        $headerRow = false;
        foreach ($this->tableRows($table) as $tr) {
            $cells = [];
            $hasHeader = false;
            foreach ($tr->childNodes as $cell) {
                if (!$cell instanceof DOMElement || !in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    continue;
                }
                $hasHeader = $hasHeader || strtolower($cell->tagName) === 'th';
                $cells[] = str_replace('|', '\\|', str_replace("\n", ' ', self::tidy($this->inlineText($cell))));
            }
            if (implode('', $cells) === '') {
                continue;
            }
            if ($rows === [] && $hasHeader) {
                $headerRow = true;
            }
            $rows[] = '| ' . implode(' | ', $cells) . ' |';
            if ($rows !== [] && count($rows) === 1 && $headerRow) {
                $rows[] = '|' . str_repeat(' --- |', count($cells));
            }
        }
        return implode("\n", $rows);
    }

    private static function collapse(string $text): string
    {
        return preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text;
    }

    private static function tidy(string $text): string
    {
        $text = preg_replace('/[ \t\r\f\v\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
