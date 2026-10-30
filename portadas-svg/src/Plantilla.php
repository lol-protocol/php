<?php

declare(strict_types=1);

namespace PortadasSvg;

use PortadasSvg\Adornos\Adorno;
use PortadasSvg\Adornos\Motivo;
use PortadasSvg\Plantillas\Clasica;
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

    /** Contratapa: raya de acento (distancia al borde superior del corte, largo y grosor). */
    protected const CONTRATAPA_RAYA_Y = 22.0;
    protected const CONTRATAPA_RAYA_LARGO = 12.0;
    protected const CONTRATAPA_RAYA_GROSOR = 0.8;
    /** Contratapa: friso del adorno de la tradición, que sustituye a la raya (distancia al borde superior, ancho y alto). */
    protected const CONTRATAPA_FRISO_Y = 17.0;
    protected const CONTRATAPA_FRISO_ANCHO = 36.0;
    protected const CONTRATAPA_FRISO_ALTO = 5.0;
    /** Contratapa: motivo de la categoría, abajo a la izquierda sobre el pie (radio y distancia de su centro al borde inferior). */
    protected const CONTRATAPA_MOTIVO_RADIO = 6.5;
    protected const CONTRATAPA_MOTIVO_Y = 30.0;
    /** Contratapa: dónde empieza el texto desde el borde superior, qué fracción del alto puede ocupar y su tamaño. */
    protected const CONTRATAPA_TEXTO_Y = 34.0;
    protected const CONTRATAPA_TEXTO_ALTO = 0.5;
    protected const CONTRATAPA_TAMANO_MAX = 4.2;
    protected const CONTRATAPA_TAMANO_MIN = 2.8;
    /** Contratapa: la cita que la abre (alto máximo, tamaño, espacio libre bajo ella) y la línea de su autor (tamaño y separación). */
    protected const CITA_ALTO = 34.0;
    protected const CITA_TAMANO_MAX = 5.6;
    protected const CITA_TAMANO_MIN = 3.4;
    protected const CITA_SEPARACION = 8.0;
    protected const CITA_AUTOR_TAMANO = 3.2;
    protected const CITA_AUTOR_SEPARACION = 3.0;
    /** Pie con editorial y colección: distancia de su línea base al borde inferior del corte, y tamaño. */
    protected const PIE_Y = 12.0;
    protected const PIE_TAMANO_MAX = 3.2;
    protected const PIE_TAMANO_MIN = 2.4;
    /** Hueco del código de barras: ancho, alto, distancia de su borde superior al borde inferior del corte, y espacio libre hasta el pie. */
    protected const CODIGO_ANCHO = 42.0;
    protected const CODIGO_ALTO = 26.0;
    protected const CODIGO_Y = 38.0;
    protected const CODIGO_SEPARACION = 4.0;
    /** ISBN: separación sobre el hueco del código y tamaño. */
    protected const ISBN_SEPARACION = 3.0;
    protected const ISBN_TAMANO_MAX = 2.8;
    protected const ISBN_TAMANO_MIN = 2.0;

    /** Solapas: margen interior y tamaño del texto. */
    protected const SOLAPA_MARGEN = 10.0;
    protected const SOLAPA_TAMANO_MAX = 4.0;
    protected const SOLAPA_TAMANO_MIN = 2.8;

    /** Lomo: ancho a partir del cual lleva texto, tamaño del texto (el máximo es también una fracción del ancho) y centrado. */
    protected const LOMO_MINIMO = 3.0;
    protected const LOMO_TAMANO_MAX = 4.5;
    protected const LOMO_TAMANO_MIN = 2.2;
    protected const LOMO_TAMANO_ANCHO = 0.6;
    protected const LOMO_CENTRADO = 0.35;
    /** Lomo: el motivo de la categoría solo cabe si el lomo mide al menos esto; su radio (fracción del ancho del lomo) y el espacio libre hasta el texto. */
    protected const LOMO_MOTIVO_ANCHO_MINIMO = 5.0;
    protected const LOMO_MOTIVO_RADIO = 0.36;
    protected const LOMO_MOTIVO_SEPARACION = 3.0;

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

    /**
     * Contornos de las letras que ha dibujado la pieza en curso, por identificador (ver `aTrazos`).
     *
     * @var array<string, string>
     */
    private array $glifos = [];

    /** @return array<string, class-string<Plantilla>> */
    public static function disponibles(): array
    {
        return ['clasica' => Clasica::class, 'sobria' => Sobria::class, 'tipografica' => Tipografica::class];
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
        $this->glifos = [];
        $paneles = '';
        foreach ($medidas->paneles($vista) as $panel) {
            $zona = $medidas->pieza($panel);
            $paneles .= match ($panel) {
                'portada' => $this->portada($libro, $zona),
                'contratapa' => $this->contratapa($libro, $zona),
                'lomo' => $this->lomo($libro, $zona),
                'solapa-contratapa' => $this->solapa($libro->textos->solapaContratapa, $libro, $zona),
                'solapa-portada' => $this->solapa($libro->textos->solapaPortada, $libro, $zona),
                default => throw new \LogicException("Panel sin dibujar: '$panel'."),
            };
        }

        return ($libro->degradado?->defs($medidas->lienzo()) ?? '')
            . ($this->textoATrazos ? $this->defsDeGlifos() : Fuentes::estilo($this->usadas))
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

    /** @param array<string, float|int|string> $extra */
    private static function pesoDe(array $extra): int
    {
        $peso = $extra['font-weight'] ?? 400;

        return match ($peso) {
            'normal' => 400,
            'bold' => 700,
            default => (int) $peso,
        };
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

    protected function contratapa(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        // Arriba, el friso de la tradición; si no tiene, una raya de acento.
        $svg = $this->adorno($libro)->friso(new Rectangulo($x, $panel->y + self::CONTRATAPA_FRISO_Y, self::CONTRATAPA_FRISO_ANCHO, self::CONTRATAPA_FRISO_ALTO), $colores['acento']);
        if ($svg === '') {
            $svg = $this->linea($x, $panel->y + self::CONTRATAPA_RAYA_Y, $x + self::CONTRATAPA_RAYA_LARGO, $panel->y + self::CONTRATAPA_RAYA_Y, $colores['acento'], self::CONTRATAPA_RAYA_GROSOR);
        }
        $y = $panel->y + self::CONTRATAPA_TEXTO_Y;
        $alto = $panel->alto * self::CONTRATAPA_TEXTO_ALTO;
        if ($libro->textos->cita !== '') {
            // La cita abre la contratapa, más grande y con la letra propia de la plantilla, y firmada por el autor.
            $estilo = $this->estiloDeCita();
            $cita = Texto::ajustar('«' . $libro->textos->cita . '»', $ancho, self::CITA_ALTO, self::CITA_TAMANO_MAX, self::CITA_TAMANO_MIN, $this->medidaDe($estilo), self::INTERLINEADO, true);
            $svg .= $this->parrafo($cita['lineas'], $x, $y + $cita['tamano'] * self::ASCENDENTE, $cita['tamano'], 'start', $colores['texto'], $estilo);
            $firma = $y + $cita['tamano'] * self::ASCENDENTE + (count($cita['lineas']) - 1) * $cita['tamano'] * self::INTERLINEADO + $cita['tamano'] * self::DESCENDENTE + self::CITA_AUTOR_SEPARACION + self::CITA_AUTOR_TAMANO * self::ASCENDENTE;
            $svg .= $this->rotulo('— ' . $libro->textos->autor, $x, $firma, $ancho, self::CITA_AUTOR_TAMANO, self::CONTRATAPA_TAMANO_MIN, 'start', $colores['acento']);
            $gastado = $firma + self::CITA_AUTOR_TAMANO * self::DESCENDENTE + self::CITA_SEPARACION - $y;
            $y += $gastado;
            $alto -= $gastado;
        }
        if ($libro->textos->contratapa !== '') {
            $texto = Texto::ajustar($libro->textos->contratapa, $ancho, $alto, self::CONTRATAPA_TAMANO_MAX, self::CONTRATAPA_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO);
            $svg .= $this->parrafo($texto['lineas'], $x, $y + $texto['tamano'] * self::ASCENDENTE, $texto['tamano'], 'start', $colores['texto']);
        }

        if ($libro->categoria !== null) {
            $svg .= Motivo::dibujar($libro->categoria, $x + self::CONTRATAPA_MOTIVO_RADIO, $panel->y + $panel->alto - self::CONTRATAPA_MOTIVO_Y, self::CONTRATAPA_MOTIVO_RADIO, $colores['acento']);
        }

        // Hueco en blanco para el código de barras del ISBN: el código lo pone quien imprime. El pie
        // llega hasta el hueco y deja un espacio libre antes de él.
        $caja = new Rectangulo($panel->x + $panel->ancho - self::MARGEN - self::CODIGO_ANCHO, $panel->y + $panel->alto - self::CODIGO_Y, self::CODIGO_ANCHO, self::CODIGO_ALTO);
        $svg .= $this->editorial($libro, $x, $panel->y + $panel->alto - self::PIE_Y, 'start', $ancho - self::CODIGO_ANCHO - self::CODIGO_SEPARACION);
        $svg .= $this->rectangulo($caja, ['fill' => '#ffffff']);
        if ($libro->textos->isbn !== '') {
            $svg .= $this->rotulo('ISBN ' . $libro->textos->isbn, $caja->x + self::CODIGO_ANCHO, $caja->y - self::ISBN_SEPARACION, self::CODIGO_ANCHO, self::ISBN_TAMANO_MAX, self::ISBN_TAMANO_MIN, 'end', $colores['texto']);
        }

        return $svg;
    }

    protected function lomo(Libro $libro, Rectangulo $panel): string
    {
        $colores = $this->coloresLomo($libro);
        // El lomo no tiene borde exterior, así que su color llega también al sangrado de arriba y de abajo.
        $fondo = new Rectangulo($panel->x, 0.0, $panel->ancho, $panel->alto + 2 * $libro->formato->sangrado);
        $svg = $this->rectangulo($fondo, ['fill' => $colores['fondo']]);
        if ($panel->ancho < self::LOMO_MINIMO) {
            return $svg;
        }

        // Con categoría y un lomo ancho, el motivo va abajo. El texto deja el mismo espacio arriba y abajo,
        // para seguir centrado.
        $radio = $libro->categoria !== null && $panel->ancho >= self::LOMO_MOTIVO_ANCHO_MINIMO ? $panel->ancho * self::LOMO_MOTIVO_RADIO : 0.0;
        if ($radio > 0.0) {
            $svg .= Motivo::dibujar($libro->categoria ?? '', $panel->x + $panel->ancho / 2, $panel->y + $panel->alto - self::MARGEN - $radio, $radio, $colores['texto']);
        }
        $largo = $panel->alto - 2 * self::MARGEN - ($radio > 0.0 ? 2 * (2 * $radio + self::LOMO_MOTIVO_SEPARACION) : 0.0);
        $completo = $libro->textos->titulo . ' · ' . $libro->textos->autor;
        $texto = Texto::unaLinea($completo, $largo, min(self::LOMO_TAMANO_MAX, $panel->ancho * self::LOMO_TAMANO_ANCHO), self::LOMO_TAMANO_MIN, $this->medidaDe());

        // El texto va girado 90°. Al girarlo, la línea base queda en x y las letras crecen hacia un lado
        // u otro según el sentido; se corre x para que el texto quede centrado en el lomo.
        $sentido = $libro->lomoDireccion === 'abajo-arriba' ? -1 : 1;
        $x = $panel->x + $panel->ancho / 2 - $sentido * self::LOMO_CENTRADO * $texto['tamano'];
        $y = $panel->y + $panel->alto / 2;

        $this->usar([], $texto['texto']);

        if ($this->textoATrazos) {
            return $svg . $this->aTrazos([$texto['texto']], 0.0, 0.0, $texto['tamano'], 'middle', $colores['texto'], [], 'translate(' . Svg::n($x) . ' ' . Svg::n($y) . ') rotate(' . (90 * $sentido) . ') ');
        }

        return $svg . Svg::elemento('text', [
            'transform' => 'translate(' . Svg::n($x) . ' ' . Svg::n($y) . ') rotate(' . (90 * $sentido) . ')',
            'font-family' => Fuentes::pila($this->familia()),
            'font-size' => $texto['tamano'],
            'text-anchor' => 'middle',
            'fill' => $colores['texto'],
        ], Svg::e($texto['texto']));
    }

    /** Solapa interior: texto arriba a la izquierda, con su margen. */
    protected function solapa(string $texto, Libro $libro, Rectangulo $panel): string
    {
        if (trim($texto) === '') {
            return '';
        }

        $margen = self::SOLAPA_MARGEN;
        $ajuste = Texto::ajustar($texto, $panel->ancho - 2 * $margen, $panel->alto - 2 * $margen, self::SOLAPA_TAMANO_MAX, self::SOLAPA_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO);

        return $this->parrafo($ajuste['lineas'], $panel->x + $margen, $panel->y + $margen + $ajuste['tamano'] * self::ASCENDENTE, $ajuste['tamano'], 'start', $libro->colores['texto']);
    }

    /** Pie con editorial y colección, si hay alguna, en una sola línea que cabe en `ancho`. */
    protected function editorial(Libro $libro, float $x, float $y, string $anclaje, float $ancho): string
    {
        $pie = implode(' · ', array_filter([$libro->textos->editorial, $libro->textos->coleccion], static fn (string $dato): bool => $dato !== ''));

        return $pie === '' ? '' : $this->rotulo($pie, $x, $y, $ancho, self::PIE_TAMANO_MAX, self::PIE_TAMANO_MIN, $anclaje, $libro->colores['texto']);
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

        return $this->parrafo([$ajuste['texto']], $x, $y, $ajuste['tamano'], $anclaje, $color, $extra);
    }

    /**
     * Texto de varias líneas con la primera línea base en (x, y). Una línea vacía deja un hueco.
     *
     * @param list<string> $lineas
     * @param array<string, float|int|string> $extra
     */
    protected function parrafo(array $lineas, float $x, float $y, float $tamano, string $anclaje, string $color, array $extra = []): string
    {
        $this->usar($extra, implode('', $lineas));
        if ($this->textoATrazos) {
            return $this->aTrazos($lineas, $x, $y, $tamano, $anclaje, $color, $extra);
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
     * El mismo texto que `parrafo`, pero como contornos: una línea es un grupo escalado al tamaño de letra, y
     * cada letra un `<use>` de su trazado, colocado según su avance. No aplica kerning, igual que la medida
     * del texto. Una letra que la fuente no tiene deja su hueco (con el ancho medio) y no se dibuja.
     *
     * @param list<string> $lineas
     * @param array<string, float|int|string> $extra
     * @param string $antes transformación previa del grupo, para el texto girado del lomo
     */
    private function aTrazos(array $lineas, float $x, float $y, float $tamano, string $anclaje, string $color, array $extra, string $antes = ''): string
    {
        $familia = $this->familia();
        $estilo = self::estiloDe($extra);
        $peso = self::pesoDe($extra);
        $medida = $this->medidaDe($extra);
        $escala = $tamano / 1000;
        $espaciado = (float) ($extra['letter-spacing'] ?? 0.0) / $escala; // en milésimas de em

        $svg = '';
        foreach ($lineas as $indice => $linea) {
            $letras = '';
            $avance = 0.0;
            foreach (mb_str_split($linea) as $letra) {
                $codigo = mb_ord($letra);
                if ($codigo < 0x20) {
                    continue;
                }
                $contorno = Fuentes::contorno($familia, $estilo, $peso, $codigo);
                if ($contorno === null) {
                    $avance += $medida->em($letra) * 1000 + $espaciado;
                    continue;
                }
                if ($contorno[1] !== '') {
                    $id = "g$familia-$estilo-$peso-$codigo";
                    $this->glifos[$id] = $contorno[1];
                    $letras .= Svg::elemento('use', ['href' => "#$id", 'xlink:href' => "#$id", 'x' => $avance]);
                }
                $avance += $contorno[0] + $espaciado;
            }
            if ($letras === '') {
                continue;
            }
            $ancho = $avance * $escala;
            $inicio = match ($anclaje) {
                'middle' => $x - $ancho / 2,
                'end' => $x - $ancho,
                default => $x,
            };
            $base = $y + $indice * $tamano * self::INTERLINEADO;
            $svg .= Svg::elemento('g', [
                'transform' => $antes . 'translate(' . Svg::n($inicio) . ' ' . Svg::n($base) . ') scale(' . rtrim(rtrim(number_format($escala, 6, '.', ''), '0'), '.') . ')',
                'fill' => $color,
            ], "\n" . $letras);
        }

        return $svg;
    }

    /** Los contornos que usan las letras de la pieza, una sola vez cada uno, para referirse a ellos con `<use>`. */
    private function defsDeGlifos(): string
    {
        if ($this->glifos === []) {
            return '';
        }
        ksort($this->glifos);
        $trazados = '';
        foreach ($this->glifos as $id => $contorno) {
            $trazados .= Svg::elemento('path', ['id' => $id, 'd' => $contorno]);
        }

        return "<defs>\n" . $trazados . "</defs>\n";
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
