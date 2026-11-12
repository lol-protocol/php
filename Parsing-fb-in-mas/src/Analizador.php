<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Base de los analizadores. Recibe el HTML de una página que la persona ya guardó:
 * ningún analizador descarga nada, inicia sesión ni sigue enlaces.
 */
abstract class Analizador
{
    private const LARGO_MAXIMO_DESCRIPCION = 500;

    /** Nombre corto de la red: `facebook`, `linkedin`, `twitter`… */
    abstract public function red(): string;

    /**
     * Dominios de la red (sin `www.`): `twitter.com`, `x.com`…
     *
     * @return list<string>
     */
    abstract public function dominios(): array;

    /**
     * Completa los campos propios de la red a partir de lo que leyó {@see Metadatos}.
     *
     * @param array{meta: array<string, string>, canonica: ?string, titulo: ?string, persona: ?array<string, string>} $d
     * @param ?string $url dirección de la página, si se conoce
     * @return array{usuario?: ?string, nombre?: ?string, titular?: ?string, lugar?: ?string, descripcion?: ?string, nacimiento?: ?string, muerte?: ?string, lugar_nacimiento?: ?string, lugar_muerte?: ?string}
     */
    abstract protected function extraer(array $d, ?string $url): array;

    /**
     * Primeros segmentos de la ruta que no son el nombre de un usuario (`p`, `home`, `people`…),
     * en minúsculas. Por defecto, ninguno.
     *
     * @return list<string>
     */
    protected function reservados(): array
    {
        return [];
    }

    /** El usuario que dice la ruta de la dirección, o null si ese segmento está reservado. */
    protected function usuarioDeRuta(?string $url): ?string
    {
        $segmento = self::primerSegmento($url);
        return $segmento !== null && in_array(strtolower($segmento), $this->reservados(), true) ? null : $segmento;
    }

    /**
     * @param array{meta: array<string, string>, canonica: ?string, titulo: ?string, persona: ?array<string, string>} $d
     */
    public function reconoce(array $d): bool
    {
        $host = self::host(self::urlDe($d));
        if ($host === null) {
            return false;
        }
        foreach ($this->dominios() as $dominio) {
            if ($host === $dominio || str_ends_with($host, '.' . $dominio)) {
                return true;
            }
        }
        return false;
    }

    public function analizar(string $html, ?string $capturado = null, bool $revisar = true): Perfil
    {
        return $this->desdeMetadatos(Metadatos::leer($html), $capturado, $revisar);
    }

    /**
     * @param array{meta: array<string, string>, canonica: ?string, titulo: ?string, persona: ?array<string, string>} $d
     */
    public function desdeMetadatos(array $d, ?string $capturado = null, bool $revisar = true): Perfil
    {
        $url = self::urlDe($d);
        $c = $this->extraer($d, $url);

        $nombre = self::texto($c['nombre'] ?? null);
        $descripcion = self::texto($c['descripcion'] ?? null);
        if ($descripcion !== null && mb_strlen($descripcion) > self::LARGO_MAXIMO_DESCRIPCION) {
            $descripcion = rtrim(mb_substr($descripcion, 0, self::LARGO_MAXIMO_DESCRIPCION - 1)) . '…';
        }

        $avisos = [];
        if ($nombre === null) {
            $avisos[] = 'sin nombre: la página no trae las etiquetas que se leen; quizá pide iniciar sesión';
        }
        if ($url === null) {
            $avisos[] = 'sin dirección: no se puede citar la fuente';
        }
        if ($capturado === null) {
            $avisos[] = 'sin fecha de captura: anótala al citar la fuente';
        }
        $segmento = self::primerSegmento($url);
        if ($segmento !== null && in_array(strtolower($segmento), $this->reservados(), true)) {
            $avisos[] = 'la dirección parece una publicación u otra página, no un perfil: sin usuario';
        }

        $titular = self::texto($c['titular'] ?? null);
        $marcas = [];
        if ($revisar) {
            $marcas = Revision::marcas(implode("\n", array_filter([$titular, $descripcion])));
            if (!Revision::hayFiltro()) {
                $avisos[] = 'sin filtro de difamación (falta ../defamatory-content-review/): solo se marcan términos sensibles';
            }
            if ($marcas !== []) {
                $avisos[] = 'leer el texto con cuidado: ' . implode(', ', $marcas);
            }
        }

        return new Perfil(
            $this->red(),
            $url,
            self::texto($c['usuario'] ?? null),
            $nombre,
            $titular,
            self::texto($c['lugar'] ?? null),
            $descripcion,
            $capturado,
            $avisos,
            self::texto($c['nacimiento'] ?? null),
            self::texto($c['muerte'] ?? null),
            self::texto($c['lugar_nacimiento'] ?? null),
            self::texto($c['lugar_muerte'] ?? null),
            [],
            $marcas,
        );
    }

    /**
     * @param array{meta: array<string, string>, canonica: ?string, titulo: ?string, persona: ?array<string, string>} $d
     */
    public static function urlDe(array $d): ?string
    {
        foreach ([$d['canonica'], $d['meta']['og:url'] ?? null, $d['persona']['url'] ?? null] as $url) {
            if ($url !== null && preg_match('#^https?://#i', $url) === 1) {
                return $url;
            }
        }
        return null;
    }

    public static function host(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower($host);
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Separa «Ana Ejemplo (@anaejemplo) / X» en nombre y usuario. Con `$arroba` en falso busca
     * «Ana Ejemplo (anaejemplo)», como Pinterest.
     *
     * @param string $usuario expresión del usuario, sin delimitadores
     * @return array{0: ?string, 1: ?string} nombre y usuario, o dos null si el título no tiene esa forma
     */
    protected static function nombreYUsuario(?string $titulo, string $usuario = '[\w.]{1,30}', bool $arroba = true): array
    {
        $patron = '/^(.*?)\s*\(' . ($arroba ? '@' : '') . '(' . $usuario . ')\)/u';
        if ($titulo !== null && preg_match($patron, $titulo, $m) === 1) {
            return [$m[1] !== '' ? $m[1] : null, $m[2]];
        }
        return [null, null];
    }

    /** El usuario de una ruta que lo lleva con arroba (`/@ana`), o null si la ruta no empieza por «@». */
    protected static function usuarioConArroba(?string $url): ?string
    {
        $segmento = self::primerSegmento($url);
        return $segmento !== null && str_starts_with($segmento, '@') && strlen($segmento) > 1 ? substr($segmento, 1) : null;
    }

    /**
     * Los campos de una persona de schema.org (JSON-LD o microdatos) tal como los pide `extraer()`.
     * Agregar un campo a `Metadatos` pide cambiarlo aquí y en ningún otro sitio.
     *
     * @param ?array<string, string> $p
     * @return array{nombre: ?string, titular: ?string, lugar: ?string, descripcion: ?string, nacimiento: ?string, muerte: ?string, lugar_nacimiento: ?string, lugar_muerte: ?string}
     */
    protected static function camposDePersona(?array $p): array
    {
        return [
            'nombre' => $p['nombre'] ?? null,
            'titular' => $p['titular'] ?? null,
            'lugar' => $p['lugar'] ?? null,
            'descripcion' => $p['descripcion'] ?? null,
            'nacimiento' => $p['nacimiento'] ?? null,
            'muerte' => $p['muerte'] ?? null,
            'lugar_nacimiento' => $p['lugar_nacimiento'] ?? null,
            'lugar_muerte' => $p['lugar_muerte'] ?? null,
        ];
    }

    /** Primer segmento de la ruta de una dirección: `/ana.ejemplo/about` da `ana.ejemplo`. */
    protected static function primerSegmento(?string $url): ?string
    {
        $ruta = $url === null ? null : parse_url($url, PHP_URL_PATH);
        if (!is_string($ruta)) {
            return null;
        }
        $segmento = explode('/', trim($ruta, '/'))[0];
        return $segmento === '' ? null : rawurldecode($segmento);
    }

    private static function texto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $valor = Metadatos::limpiar($valor);
        return $valor === '' ? null : $valor;
    }
}
