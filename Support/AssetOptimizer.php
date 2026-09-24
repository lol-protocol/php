<?php

declare(strict_types=1);

namespace App\Support;

class AssetOptimizer
{
    private static ?AssetOptimizer $instance = null;
    private bool $minifyEnabled;
    private bool $lazyLoadEnabled;
    private array $assetCache = [];

    private function __construct()
    {
        $this->minifyEnabled = getenv('ASSET_MINIFY') === 'true';
        $this->lazyLoadEnabled = getenv('ASSET_LAZY_LOAD') === 'true';
    }

    public static function getInstance(): AssetOptimizer
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function minifyCss(string $css): string
    {
        if (!$this->minifyEnabled) {
            return $css;
        }

        $css = preg_replace('!/\*[^*]*\*+(?:[^/*][^*]*\*+)*/!', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        $css = trim($css);

        return $css;
    }

    public function minifyJs(string $js): string
    {
        if (!$this->minifyEnabled) {
            return $js;
        }

        $js = preg_replace('!/\*[^*]*\*+(?:[^/*][^*]*\*+)*/!', '', $js);
        $js = preg_replace('!//.*?[\r\n]!', "\n", $js);
        $js = preg_replace('/\n\s*\n/', "\n", $js);
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}();:,=\[\]])\s*/', '$1', $js);
        $js = trim($js);

        return $js;
    }

    public function generateImageLazyLoad(string $src, string $alt = '', array $attributes = []): string
    {
        if (!$this->lazyLoadEnabled) {
            return $this->generateImage($src, $alt, $attributes);
        }

        $attrs = 'loading="lazy"';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return sprintf(
            '<img src="%s" alt="%s" %s>',
            htmlspecialchars($src),
            htmlspecialchars($alt),
            $attrs
        );
    }

    public function generateImage(string $src, string $alt = '', array $attributes = []): string
    {
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return sprintf(
            '<img src="%s" alt="%s"%s>',
            htmlspecialchars($src),
            htmlspecialchars($alt),
            $attrs
        );
    }

    public function generateScriptTag(string $src, array $attributes = []): string
    {
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return sprintf(
            '<script src="%s"%s></script>',
            htmlspecialchars($src),
            $attrs
        );
    }

    public function generateLinkTag(string $href, string $rel = 'stylesheet', array $attributes = []): string
    {
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return sprintf(
            '<link rel="%s" href="%s"%s>',
            htmlspecialchars($rel),
            htmlspecialchars($href),
            $attrs
        );
    }

    public function getAssetHash(string $filePath): string|null
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return hash_file('sha256', $filePath) ?: null;
    }

    public function getVersionedAssetPath(string $assetPath): string
    {
        $publicPath = __DIR__ . '/../public' . $assetPath;

        if (!file_exists($publicPath)) {
            return $assetPath;
        }

        $hash = $this->getAssetHash($publicPath);
        if ($hash === null) {
            return $assetPath;
        }

        $shortHash = substr($hash, 0, 8);
        $pathInfo = pathinfo($assetPath);

        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $shortHash . '.' . $pathInfo['extension'];
    }

    public function getCriticalCss(string $viewName): string
    {
        $criticalCssPath = __DIR__ . '/../public/css/critical-' . $viewName . '.css';

        if (!file_exists($criticalCssPath)) {
            return '';
        }

        $css = file_get_contents($criticalCssPath);
        return $css !== false ? $this->minifyCss($css) : '';
    }

    public function setCaching(bool $enabled): void
    {
        if ($enabled) {
            $this->assetCache = [];
        }
    }

    public function setMinify(bool $enabled): void
    {
        $this->minifyEnabled = $enabled;
    }

    public function setLazyLoad(bool $enabled): void
    {
        $this->lazyLoadEnabled = $enabled;
    }
}
