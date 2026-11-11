<?php

declare(strict_types=1);

namespace PortadasSvg;

/** Escribe las piezas de un libro en una carpeta: un SVG por pieza y un resumen de medidas para la imprenta. */
final class Generador
{
    /**
     * Archivo => [vista, nombre legible]. Cada libro lleva cuatro SVG: portada, contratapa, lomo y la
     * cubierta, que es el sobrelomo si tiene solapas y la cubierta completa si no.
     */
    private const PIEZAS = [
        'portada.svg' => ['portada', 'Portada'],
        'contratapa.svg' => ['contratapa', 'Contratapa'],
        'lomo.svg' => ['lomo', 'Lomo'],
        'cubierta-completa.svg' => ['cubierta', 'Cubierta completa'],
        'sobrelomo.svg' => ['sobrelomo', 'Sobrelomo'],
    ];

    /**
     * Con `$textoATrazos`, el texto sale como contornos en vez de con la fuente incrustada (ver Plantilla).
     *
     * @return list<string> nombres de los archivos escritos
     */
    public static function escribir(Libro $libro, string $directorio, bool $textoATrazos = false): array
    {
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true)) {
            throw new \RuntimeException("No se pudo crear la carpeta $directorio.");
        }

        // Todo se calcula antes de tocar el disco: si algo falla al dibujar, la carpeta no cambia.
        $medidas = new Medidas($libro);
        $plantilla = Plantilla::crear($libro->plantilla);
        // Las letras de las fuentes extra y las palabras compuestas de antemano no se pueden incrustar como fuente:
        // si el libro las usa, sale a trazos aunque no se haya pedido.
        $textoATrazos = $textoATrazos || self::necesitaTrazos($libro);
        $plantilla->textoATrazos = $textoATrazos;
        $contenidos = [];
        foreach (self::piezas($libro) as $archivo => [$vista, $nombre]) {
            $cuerpo = $plantilla->dibujar($libro, $medidas, $vista);
            $contenidos[$archivo] = Svg::documento($medidas->vista($vista), "{$libro->textos->titulo} · $nombre", self::descripcion($libro, $vista, $nombre), $libro->idioma, $cuerpo);
        }
        $contenidos['medidas.txt'] = self::resumen($libro, $medidas, $textoATrazos);

        // Se quitan las piezas de una escritura anterior: si el libro cambió de solapas, el sobrelomo viejo
        // no puede quedarse junto a la cubierta nueva.
        foreach ([...array_keys(self::PIEZAS), 'medidas.txt'] as $archivo) {
            self::borrar("$directorio/$archivo");
        }
        foreach ($contenidos as $archivo => $contenido) {
            self::guardar("$directorio/$archivo", $contenido);
        }
        self::guardar("$directorio/" . self::HUELLA, self::huella($libro, $textoATrazos));

        return array_keys($contenidos);
    }

    /** Archivo oculto de cada libro con la huella de lo que se escribió (ver `huella`). */
    private const HUELLA = '.huella';

    /**
     * Una huella de todo lo que decide cómo sale un libro: sus datos, el modo (fuente o trazos), las fuentes extra y
     * el código y los datos del propio generador. Sirve para no volver a escribir un libro que no cambió.
     */
    public static function huella(Libro $libro, bool $textoATrazos): string
    {
        $externos = implode(',', array_map(static fn (string $ruta): string => is_file($ruta) ? (string) md5_file($ruta) : 'falta', $libro->archivosExternos()));

        return md5(serialize($libro) . $externos . ($textoATrazos ? 'trazos' : 'fuente') . Fuentes::huellaExtras() . self::versionDelCodigo());
    }

    /** Si la carpeta del libro ya tiene todas sus piezas y fueron escritas con esta misma huella. */
    public static function sinCambios(Libro $libro, string $directorio, bool $textoATrazos): bool
    {
        $archivo = "$directorio/" . self::HUELLA;
        if (!is_file($archivo) || file_get_contents($archivo) !== self::huella($libro, $textoATrazos)) {
            return false;
        }
        foreach ([...array_keys(self::piezas($libro)), 'medidas.txt'] as $archivo) {
            if (!is_file("$directorio/$archivo")) {
                return false;
            }
        }

        return true;
    }

    /** Una huella del código, los datos y las fuentes del generador: si cambian, los libros hay que volver a escribirlos. */
    private static function versionDelCodigo(): string
    {
        static $version = null;
        if ($version === null) {
            $partes = [];
            foreach (['src', 'datos', 'fuentes'] as $carpeta) {
                $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../' . $carpeta, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterador as $archivo) {
                    if ($archivo instanceof \SplFileInfo && $archivo->isFile() && !str_contains($archivo->getPathname(), '/fuentes/extra/')) {
                        $partes[$archivo->getPathname()] = md5_file($archivo->getPathname());
                    }
                }
            }
            ksort($partes);
            $version = md5(implode('|', $partes));
        }

        return $version;
    }

    /**
     * Lo que dice una pieza, para quien no la ve: qué pieza es, de qué libro y, en la contratapa, su texto.
     * Se recorta a 500 letras.
     */
    private static function descripcion(Libro $libro, string $vista, string $nombre): string
    {
        $t = $libro->textos;
        $libroDe = '«' . $t->titulo . '»' . ($t->subtitulo === '' ? '' : ": {$t->subtitulo}") . ', de ' . $t->autor . '.';
        $texto = match ($vista) {
            'contratapa', 'cubierta', 'sobrelomo' => ($t->cita === '' ? '' : "«{$t->cita}» ") . $t->contratapa,
            default => '',
        };
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if (mb_strlen($texto) > 500) {
            $texto = rtrim(mb_substr($texto, 0, 499)) . '…';
        }

        return trim("$nombre del libro $libroDe $texto");
    }

    /**
     * Cosas que conviene mirar de un libro antes de publicarlo: letras de sus textos que las fuentes
     * incrustadas no tienen (ver Fuentes: en el visor se dibujan con otra fuente, y el ajuste del texto las mide
     * con un ancho medio) y el derecho de autor de una traducción.
     *
     * @return list<string> un aviso por texto afectado
     */
    public static function avisos(Libro $libro, bool $textoATrazos = false): array
    {
        $medida = Plantilla::crear($libro->plantilla)->medidaDe();
        $t = $libro->textos;
        $textos = [
            'El título' => $t->titulo, 'El subtítulo' => $t->subtitulo, 'El autor' => Mayusculas::de($t->autor, $libro->idioma), 'El traductor' => $t->traductor,
            'La editorial' => $t->editorial, 'La colección' => $t->coleccion, 'La contratapa' => $t->contratapa,
            'La solapa de la contratapa' => $t->solapaContratapa, 'La solapa de la portada' => $t->solapaPortada,
        ];
        $avisos = [];
        if ($libro->imagen !== null && $libro->plantilla !== 'ilustrada') {
            $avisos[] = "la imagen no se usa: la plantilla '{$libro->plantilla}' no dibuja ilustración (usa la plantilla 'ilustrada').";
        }
        foreach ($textos as $nombre => $texto) {
            // Con las letras de las fuentes extra el libro sale a trazos solo: aquí falta lo que ni ellas tienen.
            $sin = $medida->sinContorno($texto);
            if ($sin !== '') {
                $avisos[] = "$nombre lleva letras que ni las fuentes ni las fuentes extra tienen ($sin); " . ($textoATrazos || self::necesitaTrazos($libro) ? 'no se dibujan' : 'se verán con otra fuente') . '. Ver herramientas/glifos-extra.py.';
            }
        }

        if ($t->traductor !== '') {
            $avisos[] = "El traductor «{$t->traductor}» figura en la cubierta: una traducción moderna puede no ser de dominio público; comprueba su licencia.";
        }
        if ($t->isbn !== '' && !Isbn::valido($t->isbn)) {
            $avisos[] = "El ISBN «{$t->isbn}» no es válido (el dígito de control no cuadra): revísalo antes de imprimirlo.";
        }
        if ($libro->formato->lomo < Plantilla::LOMO_MINIMO) {
            $avisos[] = 'El lomo mide ' . self::fmt($libro->formato->lomo) . ' mm y con menos de ' . self::fmt(Plantilla::LOMO_MINIMO) . ' mm no se escribe su texto: da más páginas, un papel más grueso o un `lomo_mm` propio.';
        }
        $fondos = [$libro->colores['fondo'], ...($libro->degradado?->colores ?? [])];
        foreach ($fondos as $fondo) {
            if (Color::contraste($libro->colores['texto'], $fondo) < 4.5) {
                $avisos[] = "El texto ({$libro->colores['texto']}) casi no se lee sobre el fondo $fondo (contraste " . number_format(Color::contraste($libro->colores['texto'], $fondo), 1, ',', '') . ':1, mínimo 4,5:1).';
                break;
            }
        }
        foreach (self::recortes($libro) as $pieza) {
            $avisos[] = "La $pieza recorta un texto con «…» porque no cabe: acórtalo.";
        }

        return $avisos;
    }

    /** Si algún texto del libro usa letras que solo tienen las fuentes extra o las palabras compuestas de antemano. */
    private static function necesitaTrazos(Libro $libro): bool
    {
        $medida = Plantilla::crear($libro->plantilla)->medidaDe();
        $t = $libro->textos;
        foreach ([$t->titulo, $t->subtitulo, $t->autor, Mayusculas::de($t->autor, $libro->idioma), $t->traductor, $t->editorial, $t->coleccion, $t->cita, $t->contratapa, $t->solapaContratapa, $t->solapaPortada] as $texto) {
            if ($medida->noCubiertas($texto) !== $medida->sinContorno($texto)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las piezas donde un texto se recortó con «…» al dibujarlo y el libro no lo trae: es lo que pasa cuando un texto
     * no cabe ni con la letra al tamaño mínimo.
     *
     * @return list<string>
     */
    private static function recortes(Libro $libro): array
    {
        $t = $libro->textos;
        $propios = mb_substr_count(implode(' ', [$t->titulo, $t->subtitulo, $t->autor, $t->traductor, $t->editorial, $t->coleccion, $t->cita, $t->contratapa, $t->solapaContratapa, $t->solapaPortada]), '…');
        $medidas = new Medidas($libro);
        $plantilla = Plantilla::crear($libro->plantilla);
        $recortadas = [];
        foreach (self::piezas($libro) as [$vista, $nombre]) {
            // Las piezas que contienen a otras (la cubierta) no se miran dos veces.
            if (in_array($vista, ['cubierta', 'sobrelomo'], true)) {
                continue;
            }
            $cuerpo = $plantilla->dibujar($libro, $medidas, $vista);
            if (mb_substr_count(strip_tags($cuerpo), '…') > $propios) {
                $recortadas[] = mb_strtolower($nombre);
            }
        }

        return $recortadas;
    }

    /**
     * El sobrelomo ya incluye la cubierta completa y sus solapas, así que con solapas no se escribe la
     * cubierta completa, y sin solapas no hay sobrelomo.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function piezas(Libro $libro): array
    {
        $excluida = $libro->formato->solapas > 0 ? 'cubierta-completa.svg' : 'sobrelomo.svg';

        return array_diff_key(self::PIEZAS, [$excluida => true]);
    }

    private static function borrar(string $ruta): void
    {
        if (is_file($ruta) && !unlink($ruta)) {
            throw new \RuntimeException("No se pudo borrar $ruta.");
        }
    }

    private static function guardar(string $ruta, string $contenido): void
    {
        if (file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException("No se pudo escribir $ruta.");
        }
    }

    private static function resumen(Libro $libro, Medidas $medidas, bool $textoATrazos): string
    {
        $lineas = [
            "Libro: {$libro->textos->titulo} — {$libro->textos->autor}",
            "Plantilla: {$libro->plantilla}; idioma: {$libro->idioma}",
            ...($libro->tradicion === null && $libro->categoria === null ? [] : ['Clasificación: ' . implode(' · ', array_filter([$libro->tradicion === null ? '' : "tradición $libro->tradicion", $libro->categoria === null ? '' : "categoría $libro->categoria"]))]),
            'Texto: ' . ($textoATrazos ? 'a trazos (sin fuentes)' : 'con las fuentes incrustadas'),
            'Corte: ' . self::fmt($libro->formato->ancho) . ' × ' . self::fmt($libro->formato->alto) . ' mm',
            'Sangrado: ' . self::fmt($libro->formato->sangrado) . ' mm en cada borde exterior',
            'Lomo: ' . self::fmt($libro->formato->lomo) . ' mm (confírmalo con la imprenta: depende del papel)',
            $libro->formato->solapas > 0 ? 'Solapas: ' . self::fmt($libro->formato->solapas) . ' mm' : 'Sin solapas',
            ...($libro->degradado === null ? [] : ['Fondo: ' . $libro->degradado->descripcion()]),
            '',
            'Tamaño de cada archivo, con sangrado (ancho × alto):',
        ];
        foreach (self::piezas($libro) as $archivo => [$vista]) {
            $zona = $medidas->vista($vista);
            $lineas[] = "  $archivo: " . self::fmt($zona->ancho) . ' × ' . self::fmt($zona->alto) . ' mm';
        }

        return implode("\n", $lineas) . "\n";
    }

    /** Milímetros con coma decimal y sin ceros sobrantes: 461,6 y 135, no 461,60 ni 135,00. */
    private static function fmt(float $mm): string
    {
        return rtrim(rtrim(number_format($mm, 2, ',', ''), '0'), ',');
    }
}
