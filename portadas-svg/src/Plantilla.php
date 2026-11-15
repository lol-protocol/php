<?php

declare(strict_types=1);

namespace PortadasSvg;

use PortadasSvg\Adornos\Adorno;
use PortadasSvg\Adornos\Motivo;
use PortadasSvg\Piezas\Contratapa;
use PortadasSvg\Piezas\Imagenes;
use PortadasSvg\Piezas\Lomo;
use PortadasSvg\Piezas\Solapas;
use PortadasSvg\Plantillas\Cartel;
use PortadasSvg\Plantillas\Clasica;
use PortadasSvg\Plantillas\Ilustrada;
use PortadasSvg\Plantillas\Sobria;
use PortadasSvg\Plantillas\Tipografica;

/**
 * Plantilla de cubierta: dibuja la portada, la contratapa, el lomo y las solapas.
 *
 * Todo se dibuja con las coordenadas de la cubierta completa (ver Medidas), pero cada pieza lleva solo
 * sus paneles: así una pieza suelta coincide exactamente con su parte de la cubierta y no arrastra el
 * resto del dibujo.
 *
 * Una plantilla concreta implementa `portada()` y `coloresPorDefecto()`. Puede cambiar también la
 * fuente, el ancho medio de letra y los colores del lomo.
 *
 * Las medidas de la disposición van como constantes, en mm salvo que se diga otra cosa. Los tamaños de
 * letra son pares MAX/MIN: el texto empieza en el máximo y baja hasta el mínimo si no cabe.
 *
 * El texto se mide con la tabla de anchos de la fuente que se incrusta (ver Fuentes y Anchos), y cada
 * pieza lleva incrustadas solo las caras tipográficas que dibuja.
 */
abstract class Plantilla
{
    use Contratapa;
    use Imagenes;
    use Lomo;
    use Solapas;

    /** Espacio entre el borde del corte y el texto. */
    protected const MARGEN = 16.0;
    /** Distancia entre líneas, en múltiplos del tamaño de letra. */
    protected const INTERLINEADO = 1.35;
    /** Parte de una letra que queda sobre la línea base y parte que baja de ella, en múltiplos del tamaño de letra. */
    protected const ASCENDENTE = 0.8;
    protected const DESCENDENTE = 0.2;
    /** Subtítulo: espacio libre mínimo bajo el título, y fracción del tamaño del título que se deja libre. */
    protected const SUBTITULO_ESPACIO_MIN = 3.0;
    protected const SUBTITULO_ESPACIO = 0.35;
    /** Subtítulo: tamaño mínimo de letra, el mismo en todas las plantillas. */
    protected const SUBTITULO_TAMANO_MIN = 3.2;

    /**
     * Caras tipográficas que ha dibujado la pieza en curso, con todo el texto escrito con cada una. Se vacía
     * en cada `dibujar`.
     *
     * @var array<string, array{familia: string, estilo: string, peso: int, texto: string}>
     */
    private array $usadas = [];

    /**
     * Con `true`, el texto no se escribe con la fuente sino como contornos: cada letra es un trazado, y la
     * pieza no depende de ninguna fuente. Se ve igual en cualquier visor, pero el texto ya no se puede
     * seleccionar ni buscar.
     */
    public bool $textoATrazos = false;

    private ?TextoATrazos $trazos = null;

    private function trazos(): TextoATrazos
    {
        return $this->trazos ??= new TextoATrazos();
    }

    /** @return array<string, class-string<Plantilla>> */
    public static function disponibles(): array
    {
        return ['clasica' => Clasica::class, 'sobria' => Sobria::class, 'tipografica' => Tipografica::class, 'ilustrada' => Ilustrada::class, 'cartel' => Cartel::class];
    }

    public static function crear(string $nombre): self
    {
        $clase = self::disponibles()[$nombre] ?? null;
        if ($clase === null) {
            throw new \InvalidArgumentException("Plantilla desconocida: '$nombre'. Disponibles: " . implode(', ', array_keys(self::disponibles())) . '.');
        }

        return new $clase();
    }

    /** @return array{fondo: string, texto: string, acento: string} */
    abstract public function coloresPorDefecto(): array;

    /**
     * Los colores de la plantilla para un libro de la categoría que tiene ese tono (0–360°, ver
     * Catalogo): el mismo estilo de siempre, teñido de ese color.
     *
     * @return array{fondo: string, texto: string, acento: string}
     */
    abstract public function paleta(float $tono): array;

    /**
     * Atributos de la letra de la cita de la contratapa: cursiva en la serif. La plantilla sans la escribe en
     * redonda, porque no incrusta su cursiva.
     *
     * @return array<string, string>
     */
    protected function estiloDeCita(): array
    {
        return $this->familia() === 'serif' ? ['font-style' => 'italic'] : [];
    }

    /**
     * La paleta de un tono con el tema que pide el libro: `claro` (fondo claro, letra oscura), `oscuro` (al revés) o
     * null, que deja la de la propia plantilla.
     *
     * @return array{fondo: string, texto: string, acento: string}
     */
    public function paletaConTema(float $tono, ?string $tema): array
    {
        return match ($tema) {
            'claro' => ['fondo' => Color::hsl($tono, 0.45, 0.91), 'texto' => Color::hsl($tono, 0.35, 0.13), 'acento' => Color::hsl($tono, 0.55, 0.33)],
            'oscuro' => ['fondo' => Color::hsl($tono, 0.25, 0.12), 'texto' => Color::hsl($tono, 0.30, 0.94), 'acento' => Color::hsl($tono, 0.55, 0.62)],
            default => $this->paleta($tono),
        };
    }

    /** Adorno de la tradición del libro. */
    protected function adorno(Libro $libro): Adorno
    {
        return Adorno::de($libro->tradicion);
    }

    /**
     * Dibuja una vista (ver Medidas::vista): el fondo de esa zona y solo los paneles que caen en ella.
     * Sin argumento, dibuja todo: la cubierta completa con sus solapas, si las tiene.
     */
    public function dibujar(Libro $libro, Medidas $medidas, string $vista = 'sobrelomo'): string
    {
        $this->usadas = [];
        $this->trazos()->reiniciar();
        $paneles = '';
        foreach ($medidas->paneles($vista) as $panel) {
            $zona = $medidas->pieza($panel);
            $paneles .= match ($panel) {
                'portada' => $this->marcaDeAgua($libro, $zona) . $this->portada($libro, $zona),
                'contratapa' => $this->contratapa($libro, $zona),
                'lomo' => $this->lomo($libro, $zona),
                'solapa-contratapa' => $this->solapa($libro->textos->solapaContratapa, $libro, $zona),
                'solapa-portada' => $this->solapa($libro->textos->solapaPortada, $libro, $zona),
                default => throw new \LogicException("Panel sin dibujar: '$panel'."),
            };
        }

        return ($libro->degradado?->defs($medidas->lienzo()) ?? '')
            . ($this->textoATrazos ? $this->trazos()->defs() : Fuentes::estilo($this->usadas))
            . $this->rectangulo($medidas->vista($vista), ['fill' => $this->relleno($libro)])
            . $paneles;
    }

    /** Dibuja la portada dentro de su panel de corte. */
    abstract protected function portada(Libro $libro, Rectangulo $panel): string;

    /** Familia tipográfica de la plantilla: `serif` o `sans` (ver Fuentes). */
    public function familia(): string
    {
        return 'serif';
    }

    /**
     * Medida de la cara con la que se escribe un texto, según sus atributos `font-style` y `font-weight`.
     * Es la misma cara que se incrusta y que se pinta, así que lo que se mide es lo que se ve.
     *
     * @param array<string, float|int|string> $extra
     */
    public function medidaDe(array $extra = []): MedidaFuente
    {
        return Anchos::de($this->familia(), self::estiloDe($extra), self::pesoDe($extra));
    }

    /** @param array<string, float|int|string> $extra */
    private static function estiloDe(array $extra): string
    {
        return ($extra['font-style'] ?? 'normal') === 'italic' ? 'italic' : 'normal';
    }

    /**
     * Peso de la letra de `font-weight`: las palabras de CSS (`normal`, `bold`, `lighter`, `bolder`) o un número de
     * 1 a 1000. `lighter` y `bolder` no dependen del texto de alrededor, así que valen 400 y 700.
     *
     * @param array<string, float|int|string> $extra
     */
    private static function pesoDe(array $extra): int
    {
        $peso = $extra['font-weight'] ?? 400;
        $palabras = ['normal' => 400, 'lighter' => 400, 'bold' => 700, 'bolder' => 700];
        if (is_string($peso) && isset($palabras[$peso])) {
            return $palabras[$peso];
        }
        if (is_numeric($peso) && $peso >= 1 && $peso <= 1000) {
            return (int) $peso;
        }

        throw new \InvalidArgumentException("font-weight no válido: «{$peso}». Usa normal, bold, lighter, bolder o un número de 1 a 1000.");
    }

    /**
     * Anota que se va a pintar este texto con esta cara, para incrustar solo los trozos de fuente que hacen
     * falta.
     *
     * @param array<string, float|int|string> $extra
     */
    private function usar(array $extra, string $texto): void
    {
        $estilo = self::estiloDe($extra);
        $peso = self::pesoDe($extra);
        $clave = "{$this->familia()}/$estilo/$peso";
        $antes = $this->usadas[$clave]['texto'] ?? '';
        $this->usadas[$clave] = ['familia' => $this->familia(), 'estilo' => $estilo, 'peso' => $peso, 'texto' => $antes . $texto];
    }

    /**
     * Relleno del fondo: el color sólido, o una referencia al degradado de la cubierta. El lomo lo
     * comparte, así que el degradado no se corta en el lomo.
     */
    protected function relleno(Libro $libro): string
    {
        return $libro->degradado === null ? $libro->colores['fondo'] : 'url(#' . Degradado::ID . ')';
    }

    /**
     * Relleno y color del texto del lomo.
     *
     * @return array{fondo: string, texto: string}
     */
    protected function coloresLomo(Libro $libro): array
    {
        return ['fondo' => $this->relleno($libro), 'texto' => $libro->colores['texto']];
    }

    /** «Vol. 3» en el idioma del libro, o vacío si no es de una serie. */
    protected function rotuloDeVolumen(Libro $libro): string
    {
        return $libro->textos->volumen === '' ? '' : Frases::texto($libro->idioma, 'volumen', ['n' => $libro->textos->volumen]);
    }

    /**
     * Una línea de texto que cabe en `ancho`: baja de `tamanoMax` a `tamanoMin` y, si ni así cabe, la
     * recorta con «…». El `letter-spacing` de `$extra` cuenta en el ancho.
     *
     * @param array<string, float|int|string> $extra atributos extra, como letter-spacing o font-style
     */
    protected function rotulo(string $texto, float $x, float $y, float $ancho, float $tamanoMax, float $tamanoMin, string $anclaje, string $color, array $extra = []): string
    {
        $espaciado = (float) ($extra['letter-spacing'] ?? 0.0);
        $ajuste = Texto::unaLinea($texto, $ancho, $tamanoMax, $tamanoMin, $this->medidaDe($extra), $espaciado);

        return $this->parrafo([$ajuste['texto']], $x, $y, $ajuste['tamano'], $anclaje, $color, $extra, $ancho);
    }

    /**
     * Texto de varias líneas con la primera línea base en (x, y). Una línea vacía deja un hueco. Con `$caja` (el ancho de
     * la caja donde empieza en `x`), un párrafo de derecha a izquierda se alinea a su borde derecho.
     *
     * @param list<string> $lineas
     * @param array<string, float|int|string> $extra
     */
    protected function parrafo(array $lineas, float $x, float $y, float $tamano, string $anclaje, string $color, array $extra = [], float $caja = 0.0): string
    {
        // Un párrafo que se lee de derecha a izquierda se alinea al borde derecho de su caja, no al izquierdo.
        if ($caja > 0.0 && $anclaje === 'start' && TextoATrazos::esDerechaAIzquierda(implode(' ', $lineas))) {
            $x += $caja;
            $anclaje = 'end';
        }
        $this->usar($extra, implode('', $lineas));
        if ($this->textoATrazos) {
            return $this->trazos()->escribir($lineas, $x, $y, $tamano, $anclaje, $color, $this->familia(), self::estiloDe($extra), self::pesoDe($extra), $this->medidaDe($extra), (float) ($extra['letter-spacing'] ?? 0.0), self::INTERLINEADO);
        }

        return Svg::elemento('text', [
            'x' => $x,
            'y' => $y,
            'font-family' => Fuentes::pila($this->familia()),
            'font-size' => $tamano,
            'text-anchor' => $anclaje,
            'fill' => $color,
        ] + $extra, Svg::lineas($lineas, $x, $tamano, self::INTERLINEADO));
    }

    /**
     * Línea base de la primera línea para que un bloque de `lineas` líneas quede centrado en la zona
     * que va de `y` a `y + alto`.
     */
    protected function baseline(float $y, float $alto, int $lineas, float $tamano): float
    {
        $visible = ($lineas - 1) * $tamano * self::INTERLINEADO + $tamano;

        return $y + ($alto - $visible) / 2 + $tamano * self::ASCENDENTE;
    }

    /**
     * El título de la portada y, si hay, el subtítulo debajo, ajustados a su zona y con el reparto en líneas
     * parejas. El título empieza en `$arriba` o, con `$centrar`, se centra en la zona de `$alto` que empieza ahí.
     *
     * @param array{max: float, min: float, estilo: array<string, string>, color: string} $titulo tamaños de letra (de `max` a `min`), atributos y color
     * @param array{alto: float, max: float, estilo: array<string, string>, color: string} $subtitulo alto disponible, tamaño máximo (el mínimo es SUBTITULO_TAMANO_MIN), atributos y color
     */
    protected function tituloYSubtitulo(Libro $libro, float $x, float $ancho, string $anclaje, float $arriba, float $alto, bool $centrar, array $titulo, array $subtitulo): string
    {
        $ajuste = Texto::ajustar($libro->textos->titulo, $ancho, $alto, $titulo['max'], $titulo['min'], $this->medidaDe($titulo['estilo']), self::INTERLINEADO, true);
        $y = $centrar ? $this->baseline($arriba, $alto, count($ajuste['lineas']), $ajuste['tamano']) : $arriba + $ajuste['tamano'] * self::ASCENDENTE;
        $svg = $this->parrafo($ajuste['lineas'], $x, $y, $ajuste['tamano'], $anclaje, $titulo['color'], $titulo['estilo']);
        if ($libro->textos->subtitulo === '') {
            return $svg;
        }

        $fin = $y + (count($ajuste['lineas']) - 1) * $ajuste['tamano'] * self::INTERLINEADO;
        $menor = Texto::ajustar($libro->textos->subtitulo, $ancho, $subtitulo['alto'], $subtitulo['max'], self::SUBTITULO_TAMANO_MIN, $this->medidaDe($subtitulo['estilo']), self::INTERLINEADO, true);

        return $svg . $this->parrafo($menor['lineas'], $x, $this->bajoElTitulo($fin, $ajuste['tamano'], $menor['tamano']), $menor['tamano'], $anclaje, $subtitulo['color'], $subtitulo['estilo']);
    }

    /**
     * Línea base del subtítulo. Bajo la última línea del título deja un espacio libre proporcional al
     * tamaño del título, además de lo que el título baja de su línea base y lo que el subtítulo sube
     * sobre la suya: así un título grande no queda pegado al subtítulo.
     */
    protected function bajoElTitulo(float $baseTitulo, float $tamanoTitulo, float $tamanoSubtitulo): float
    {
        $libre = max(self::SUBTITULO_ESPACIO_MIN, $tamanoTitulo * self::SUBTITULO_ESPACIO);

        return $baseTitulo + $tamanoTitulo * self::DESCENDENTE + $libre + $tamanoSubtitulo * self::ASCENDENTE;
    }

    /** @param array<string, float|int|string> $estilo */
    protected function rectangulo(Rectangulo $zona, array $estilo): string
    {
        return Svg::elemento('rect', ['x' => $zona->x, 'y' => $zona->y, 'width' => $zona->ancho, 'height' => $zona->alto] + $estilo);
    }

    protected function linea(float $x1, float $y1, float $x2, float $y2, string $color, float $grosor): string
    {
        return Svg::elemento('line', ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 'stroke' => $color, 'stroke-width' => $grosor]);
    }
}
