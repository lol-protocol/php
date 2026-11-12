<?php

declare(strict_types=1);

namespace App\Support;

class HostParser
{
    /**
     * Every language subdomain the URL layer knows (ISO 639-2/T, three letters) and its hreflang code
     * (ISO 639-1): the 30 languages the other projects of this repository work in. Knowing a language is not
     * serving it: see idiomas().
     */
    public const IDIOMAS_CONOCIDOS = [
        'ara' => 'ar', 'ben' => 'bn', 'ces' => 'cs', 'dan' => 'da', 'deu' => 'de', 'ell' => 'el',
        'eng' => 'en', 'fas' => 'fa', 'fin' => 'fi', 'fra' => 'fr', 'heb' => 'he', 'hin' => 'hi',
        'ind' => 'id', 'ita' => 'it', 'jpn' => 'ja', 'kor' => 'ko', 'nld' => 'nl', 'pol' => 'pl',
        'por' => 'pt', 'ron' => 'ro', 'rus' => 'ru', 'spa' => 'es', 'swa' => 'sw', 'swe' => 'sv',
        'tha' => 'th', 'tur' => 'tr', 'ukr' => 'uk', 'urd' => 'ur', 'vie' => 'vi', 'zho' => 'zh',
    ];

    /** The language of the bare domain and of every fallback: always served, always first. */
    public const IDIOMA_BASE = 'spa';

    /** The languages served when IDIOMAS_ACTIVOS says nothing. */
    public const IDIOMAS_POR_OMISION = 'spa,eng';

    /**
     * The languages this deployment serves: subdomain => hreflang code. They come from IDIOMAS_ACTIVOS (a
     * comma-separated list of subdomains, in .env), and only a known one counts. Spanish (IDIOMA_BASE) is always there,
     * first: it is the language of the bare domain. These are the only subdomains read as a language, and the only
     * ones announced in hreflang, so a language must be listed here only once its pages are in that language.
     *
     * @return array<string, string>
     */
    public static function idiomas(): array
    {
        // A page asks three times (locale, <html lang>, hreflang): the list is parsed once per value of the setting.
        /** @var array<string, array<string, string>> $porValor */
        static $porValor = [];
        $valor = strtolower((string)Config::get('IDIOMAS_ACTIVOS', self::IDIOMAS_POR_OMISION));
        if (isset($porValor[$valor])) {
            return $porValor[$valor];
        }

        $activos = [self::IDIOMA_BASE => self::IDIOMAS_CONOCIDOS[self::IDIOMA_BASE]];
        foreach (explode(',', $valor) as $codigo) {
            $codigo = trim($codigo);
            if (isset(self::IDIOMAS_CONOCIDOS[$codigo])) {
                $activos[$codigo] = self::IDIOMAS_CONOCIDOS[$codigo];
            }
        }

        return $porValor[$valor] = $activos;
    }

    /**
     * @return array{host_without_port: string, parts: list<string>}
     */
    public static function parse(string $host): array
    {
        $hostWithoutPort = explode(':', $host)[0];
        $parts = explode('.', $hostWithoutPort);

        return [
            'host_without_port' => $hostWithoutPort,
            'parts' => $parts,
        ];
    }

    /**
     * Which site serves $host: 'pos' when it is one of POS_DOMAINS (or a subdomain of one), 'genealogy' otherwise.
     * public/index.php picks the router, the database and the layout with it, and ErrorPage the layout of an error.
     */
    public static function sitio(string $host): string
    {
        $dominios = array_map('trim', explode(',', getenv('POS_DOMAINS') ?: 'contrastocolor.local,contrastocolor.test,contrastocolor.app'));

        return self::matchesDomain($host, $dominios) !== null ? 'pos' : 'genealogy';
    }

    /**
     * @param list<string> $domains
     */
    public static function matchesDomain(string $host, array $domains): string|null
    {
        $parsed = self::parse($host);
        $hostWithoutPort = $parsed['host_without_port'];

        foreach ($domains as $domain) {
            if ($hostWithoutPort === $domain || str_ends_with($hostWithoutPort, '.' . $domain)) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * @param list<string>|null $supportedLocales
     */
    public static function getLocale(string $host, ?array $supportedLocales = null): string
    {
        $supportedLocales ??= array_keys(self::idiomas());
        $parsed = self::parse($host);
        $parts = $parsed['parts'];
        $sub = strtolower($parts[0] ?? '');

        // Only treat as locale if there are multiple parts (not just the domain itself)
        if (count($parts) > 2 && in_array($sub, $supportedLocales, true)) {
            return $sub;
        }

        return self::IDIOMA_BASE;
    }

    /** This request's scheme://host, for absolute URLs. Only a plain host name is trusted from the Host header. */
    public static function origen(): string
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host) !== 1) {
            $host = 'localhost';
        }

        return (HttpSecurityHeaders::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    /**
     * The same $ruta in every language: hreflang code => absolute URL, plus
     * x-default on the bare domain (which serves the default language).
     *
     * @return array<string, string>
     */
    public static function alternos(string $ruta): array
    {
        [$esquema, $host] = explode('://', self::origen(), 2);
        $parts = explode('.', $host);
        if (count($parts) > 2 && isset(self::idiomas()[$parts[0]])) {
            array_shift($parts);
        }
        $base = implode('.', $parts);

        $urls = [];
        foreach (self::idiomas() as $subdominio => $hreflang) {
            $urls[$hreflang] = "{$esquema}://{$subdominio}.{$base}{$ruta}";
        }
        $urls['x-default'] = "{$esquema}://{$base}{$ruta}";

        return $urls;
    }
}
