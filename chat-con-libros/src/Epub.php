<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Lee un libro EPUB (2 o 3) y lo convierte en un Libro: título y autor de sus metadatos, los
 * capítulos de su tabla de contenido y un pasaje por cada párrafo (los muy cortos se unen).
 *
 * Es un EPUB, o sea un zip: el archivo es una entrada de otra persona, así que se lee con cuidado.
 *  - No extrae nada a disco: lee entradas por nombre con ZipArchive, y las rutas que trae el propio
 *    EPUB (href de la tabla de contenido y del spine) se resuelven dentro del zip, sin salirse.
 *  - Tope de tamaño por entrada descomprimida, de total leído y de cantidad de entradas.
 *  - El XML se lee sin entidades externas, sin DTD y sin red (LIBXML_NONET, sin LIBXML_NOENT).
 *  - Un EPUB con DRM no se puede leer: se dice, en vez de devolver texto cifrado.
 *
 * Necesita las extensiones zip y dom de PHP. Solo PHP: el port a Java no lee EPUB.
 * Lo que no hace: imágenes, notas al pie enlazadas ni fórmulas (solo el texto), ni PDF.
 */
final class Epub
{
    /** Bytes descomprimidos que se aceptan de una sola entrada. */
    public const MAX_ENTRADA = 20 * 1024 * 1024;

    /** Bytes descomprimidos que se leen en total de un EPUB (los capítulos, no las imágenes). */
    public const MAX_TOTAL = 150 * 1024 * 1024;

    /** Entradas del zip que se aceptan. */
    public const MAX_ENTRADAS = 5000;

    /** Un párrafo de menos de tantos caracteres se une con el siguiente del mismo capítulo. */
    public const PASAJE_MINIMO = 200;

    /** Una unión no pasa de tantos caracteres. */
    public const PASAJE_MAXIMO = 900;

    /** Un encabezado más largo que esto no se toma como título de capítulo. */
    private const TITULO_MAXIMO = 120;

    /** Algoritmos de «encryption.xml» que solo ofuscan las fuentes: no impiden leer el texto. */
    private const OFUSCACION_DE_FUENTES = [
        'http://www.idpf.org/2008/embedding',
        'http://ns.adobe.com/pdf/enc#RC',
    ];

    private const BLOQUES = [
        'p', 'div', 'section', 'article', 'body', 'blockquote', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'pre', 'figure', 'figcaption',
        'header', 'footer', 'aside', 'main', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
    ];

    private const OMITIR = ['script', 'style', 'head', 'nav', 'svg', 'math', 'noscript', 'template'];

    private int $leido = 0;

    private function __construct(private readonly \ZipArchive $zip)
    {
    }

    public static function disponible(): bool
    {
        return class_exists(\ZipArchive::class) && class_exists(\DOMDocument::class);
    }

    /** @throws \RuntimeException si no se puede leer (no es un EPUB, tiene DRM, es enorme…) */
    public static function desdeArchivo(string $ruta): Libro
    {
        if (!self::disponible()) {
            throw new \RuntimeException('Leer EPUB necesita las extensiones zip y dom de PHP.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('No se pudo abrir el EPUB (¿es un archivo .epub válido?): ' . basename($ruta));
        }
        try {
            return (new self($zip))->leer(basename($ruta));
        } finally {
            $zip->close();
        }
    }

    private function leer(string $nombreDeArchivo): Libro
    {
        if ($this->zip->numFiles > self::MAX_ENTRADAS) {
            throw new \RuntimeException('El EPUB tiene demasiadas entradas.');
        }
        $this->rechazarSiTieneDrm();

        $contenedor = $this->xpath($this->entrada('META-INF/container.xml') ?? '');
        $rutaOpf = $contenedor?->evaluate("string(//*[local-name()='rootfile']/@full-path)");
        if (!is_string($rutaOpf) || $rutaOpf === '') {
            throw new \RuntimeException('El EPUB no tiene META-INF/container.xml con su libro de paquete (.opf).');
        }
        $rutaOpf = self::resolver('', $rutaOpf) ?? '';
        $opf = $this->xpath($this->entrada($rutaOpf) ?? '');
        if ($opf === null) {
            throw new \RuntimeException('No se pudo leer el paquete .opf del EPUB.');
        }
        $base = dirname($rutaOpf) === '.' ? '' : dirname($rutaOpf);

        $titulo = self::limpiar((string) $opf->evaluate("string(//*[local-name()='metadata']/*[local-name()='title'][1])"));
        $autores = [];
        foreach ($opf->query("//*[local-name()='metadata']/*[local-name()='creator']") ?: [] as $nodo) {
            $autor = self::limpiar((string) $nodo->nodeValue);
            if ($autor !== '' && !in_array($autor, $autores, true)) {
                $autores[] = $autor;
            }
        }

        $manifiesto = [];
        foreach ($opf->query("//*[local-name()='manifest']/*[local-name()='item']") ?: [] as $item) {
            /** @var \DOMElement $item */
            $manifiesto[$item->getAttribute('id')] = [
                'ruta' => self::resolver($base, $item->getAttribute('href')),
                'tipo' => strtolower($item->getAttribute('media-type')),
                'propiedades' => ' ' . $item->getAttribute('properties') . ' ',
            ];
        }
        $titulos = $this->tablaDeContenido($opf, $manifiesto, $base);

        $pasajes = [];
        $n = 0;
        foreach ($opf->query("//*[local-name()='spine']/*[local-name()='itemref']") ?: [] as $ref) {
            /** @var \DOMElement $ref */
            $item = $manifiesto[$ref->getAttribute('idref')] ?? null;
            if ($item === null || $item['ruta'] === null || $ref->getAttribute('linear') === 'no'
                || !in_array($item['tipo'], ['application/xhtml+xml', 'text/html'], true)
                || str_contains($item['propiedades'], ' nav ')) {
                continue;
            }
            $html = $this->entrada($item['ruta']);
            if ($html === null) {
                continue;
            }
            $n++;
            array_push($pasajes, ...self::pasajesDeHtml($html, $titulos[$item['ruta']] ?? null, $n));
        }

        $porDefecto = Libro::nombreLegible((string) preg_replace('/\.epub$/i', '', $nombreDeArchivo), Libro::TITULO_POR_DEFECTO);
        return new Libro(
            $titulo !== '' ? $titulo : $porDefecto,
            $autores !== [] ? implode(', ', $autores) : Libro::AUTOR_POR_DEFECTO,
            self::unirCortos($pasajes),
        );
    }

    /**
     * El título de cada archivo según la tabla de contenido: la de EPUB 3 («nav») o, si no hay, la
     * NCX de EPUB 2. Solo se guarda el primer título de cada archivo.
     *
     * @param array<string,array{ruta:?string, tipo:string, propiedades:string}> $manifiesto
     * @return array<string,string> ruta del archivo dentro del zip → título
     */
    private function tablaDeContenido(\DOMXPath $opf, array $manifiesto, string $base): array
    {
        $titulos = [];
        foreach ($manifiesto as $item) {
            if ($item['ruta'] !== null && str_contains($item['propiedades'], ' nav ')) {
                $doc = self::htmlADom($this->entrada($item['ruta']) ?? '');
                $carpeta = dirname($item['ruta']) === '.' ? '' : dirname($item['ruta']);
                foreach ((new \DOMXPath($doc))->query('//nav//a[@href]') ?: [] as $a) {
                    /** @var \DOMElement $a */
                    $ruta = self::resolver($carpeta, $a->getAttribute('href'));
                    $texto = self::limpiar($a->textContent);
                    if ($ruta !== null && $texto !== '' && !isset($titulos[$ruta])) {
                        $titulos[$ruta] = $texto;
                    }
                }
            }
        }
        if ($titulos !== []) {
            return $titulos;
        }

        $idNcx = (string) $opf->evaluate("string(//*[local-name()='spine']/@toc)");
        $ncx = $manifiesto[$idNcx] ?? null;
        $xp = $ncx !== null && $ncx['ruta'] !== null ? $this->xpath($this->entrada($ncx['ruta']) ?? '') : null;
        if ($ncx !== null && $ncx['ruta'] !== null && $xp !== null) {
            $carpeta = dirname($ncx['ruta']) === '.' ? '' : dirname($ncx['ruta']);
            foreach ($xp->query("//*[local-name()='navPoint']") ?: [] as $punto) {
                if (!$punto instanceof \DOMElement) {
                    continue;
                }
                $texto = self::limpiar((string) $xp->evaluate("string(*[local-name()='navLabel']/*[local-name()='text'])", $punto));
                $ruta = self::resolver($carpeta, (string) $xp->evaluate("string(*[local-name()='content']/@src)", $punto));
                if ($ruta !== null && $texto !== '' && !isset($titulos[$ruta])) {
                    $titulos[$ruta] = $texto;
                }
            }
        }
        return $titulos;
    }

    /**
     * Los párrafos de un archivo XHTML, cada uno con el capítulo en que está. Un encabezado h1–h3 abre
     * capítulo (y no es un pasaje); el título de la tabla de contenido manda sobre el primer
     * encabezado si lo incluye («Capítulo 1: El relojero» sobre «Capítulo 1»).
     *
     * @return list<array{capitulo:string, texto:string}>
     */
    private static function pasajesDeHtml(string $html, ?string $tituloToc, int $n): array
    {
        $cuerpo = self::htmlADom($html)->getElementsByTagName('body')->item(0);
        if ($cuerpo === null) {
            return [];
        }
        $bloques = [];
        self::recorrer($cuerpo, $bloques);

        $capitulo = $tituloToc ?? 'Sección ' . $n;
        $vistoEncabezado = false;
        $pasajes = [];
        foreach ($bloques as [$tipo, $texto]) {
            if ($tipo === 'encabezado') {
                $valido = $texto !== '' && mb_strlen($texto) <= self::TITULO_MAXIMO;
                $contenido = $tituloToc !== null && !$vistoEncabezado
                    && mb_stripos($tituloToc, $texto) !== false;
                if ($valido && !$contenido) {
                    $capitulo = $texto;
                }
                $vistoEncabezado = true;
            } elseif ($texto !== '' && !preg_match('/^\d{1,4}$/', $texto)) {
                $pasajes[] = ['capitulo' => $capitulo, 'texto' => $texto];
            }
        }
        return $pasajes;
    }

    /**
     * @param list<array{0:string, 1:string}> $bloques
     */
    private static function recorrer(\DOMNode $nodo, array &$bloques): void
    {
        $enLinea = '';
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof \DOMText) {
                $enLinea .= $hijo->nodeValue;
                continue;
            }
            if (!$hijo instanceof \DOMElement) {
                continue;
            }
            $etiqueta = strtolower($hijo->nodeName);
            if (in_array($etiqueta, self::OMITIR, true)) {
                continue;
            }
            if (!in_array($etiqueta, self::BLOQUES, true)) {
                $enLinea .= $etiqueta === 'br' ? ' ' : $hijo->textContent;
                continue;
            }
            self::vaciar($enLinea, $bloques);
            if (preg_match('/^h[1-3]$/', $etiqueta)) {
                $bloques[] = ['encabezado', self::limpiar($hijo->textContent)];
            } elseif (self::tieneBloques($hijo)) {
                self::recorrer($hijo, $bloques);
            } else {
                $bloques[] = ['parrafo', self::limpiar($hijo->textContent)];
            }
        }
        self::vaciar($enLinea, $bloques);
    }

    /** @param list<array{0:string, 1:string}> $bloques */
    private static function vaciar(string &$enLinea, array &$bloques): void
    {
        $texto = self::limpiar($enLinea);
        if ($texto !== '') {
            $bloques[] = ['parrafo', $texto];
        }
        $enLinea = '';
    }

    private static function tieneBloques(\DOMElement $nodo): bool
    {
        foreach ($nodo->getElementsByTagName('*') as $descendiente) {
            if (in_array(strtolower($descendiente->nodeName), self::BLOQUES, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Une los párrafos cortos con los que siguen (en el mismo capítulo) hasta llegar a
     * PASAJE_MINIMO, sin pasar de PASAJE_MAXIMO: un diálogo de frases de tres palabras no se
     * puede buscar de una en una.
     *
     * @param list<array{capitulo:string, texto:string}> $pasajes
     * @return list<array{capitulo:string, texto:string}>
     */
    private static function unirCortos(array $pasajes): array
    {
        $unidos = [];
        foreach ($pasajes as $pasaje) {
            $ultimo = array_key_last($unidos);
            if ($ultimo !== null && $unidos[$ultimo]['capitulo'] === $pasaje['capitulo']
                && mb_strlen($unidos[$ultimo]['texto']) < self::PASAJE_MINIMO
                && mb_strlen($unidos[$ultimo]['texto']) + 1 + mb_strlen($pasaje['texto']) <= self::PASAJE_MAXIMO) {
                $unidos[$ultimo]['texto'] .= ' ' . $pasaje['texto'];
            } else {
                $unidos[] = $pasaje;
            }
        }
        return $unidos;
    }

    private function rechazarSiTieneDrm(): void
    {
        $xml = $this->entrada('META-INF/encryption.xml');
        if ($xml === null) {
            return;
        }
        $xp = $this->xpath($xml);
        foreach ($xp?->query('//@Algorithm') ?: [] as $algoritmo) {
            if (!in_array($algoritmo->nodeValue, self::OFUSCACION_DE_FUENTES, true)) {
                throw new \RuntimeException('El EPUB tiene DRM (está cifrado): no se puede leer su texto.');
            }
        }
    }

    /** Lee una entrada del zip por su nombre, con tope de tamaño; null si no existe o es enorme. */
    private function entrada(string $nombre): ?string
    {
        if ($nombre === '') {
            return null;
        }
        $datos = $this->zip->getFromName($nombre, self::MAX_ENTRADA + 1);
        if ($datos === false || strlen($datos) > self::MAX_ENTRADA) {
            return null;
        }
        $this->leido += strlen($datos);
        if ($this->leido > self::MAX_TOTAL) {
            throw new \RuntimeException('El EPUB descomprimido es demasiado grande.');
        }
        return $datos;
    }

    /**
     * Resuelve un href relativo a una carpeta del zip, sin salirse de él: «../x» desde la raíz es
     * null. Quita el #fragmento y decodifica %20.
     */
    public static function resolver(string $carpeta, string $href): ?string
    {
        $href = rawurldecode((string) strtok($href, '#'));
        if ($href === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) || str_contains($href, "\0")) {
            return null;
        }
        $partes = [];
        foreach (explode('/', ($href[0] === '/' ? '' : $carpeta . '/') . $href) as $parte) {
            if ($parte === '' || $parte === '.') {
                continue;
            }
            if ($parte === '..') {
                if ($partes === []) {
                    return null;
                }
                array_pop($partes);
                continue;
            }
            $partes[] = $parte;
        }
        return $partes === [] ? null : implode('/', $partes);
    }

    private function xpath(string $xml): ?\DOMXPath
    {
        if (trim($xml) === '') {
            return null;
        }
        $doc = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        return $ok ? new \DOMXPath($doc) : null;
    }

    /** El XHTML real trae entidades de HTML (&nbsp;, &aacute;) que el analizador de XML no conoce. */
    private static function htmlADom(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        return $doc;
    }

    /** Espacios (también el que no parte) y saltos de línea a un solo espacio. */
    private static function limpiar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }
}
