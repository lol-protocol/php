<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Datos de un libro ya validados, leídos de un JSON (ver README.md). Lo que no se da toma un valor
 * por defecto razonable; lo que no tiene sentido (un color mal escrito, un lomo negativo) falla antes
 * de dibujar nada.
 */
final class Libro
{
    /**
     * Límites de las medidas, en mm. Pasarse de ahí casi seguro es un error de unidades (centímetros o
     * pulgadas en vez de milímetros), y es mejor avisar antes de dibujar un lomo de cinco metros.
     */
    private const LADO_MINIMO = 50.0;
    private const LADO_MAXIMO = 500.0;
    private const SANGRADO_MAXIMO = 10.0;
    private const SOLAPA_MINIMA = 40.0;
    private const LOMO_MAXIMO = 150.0;
    /** Grosor de una hoja de papel, en mm. */
    private const GROSOR_MINIMO = 0.02;
    private const GROSOR_MAXIMO = 0.5;

    /** Largo máximo de cada texto, en caracteres: pasarse casi seguro es un texto pegado en la celda equivocada. */
    private const MAXIMOS = [
        'titulo' => 300, 'subtitulo' => 300, 'autor' => 200, 'traductor' => 200, 'editorial' => 200, 'coleccion' => 200,
        'isbn' => 40, 'epoca' => 100, 'cita' => 1000, 'contratapa' => 5000, 'solapa_contratapa' => 3000, 'solapa_portada' => 3000,
    ];
    private const MAXIMO_POR_DEFECTO = 200;
    /** Tono (en grados) de la paleta de un tema pedido sin categoría: el crema de la plantilla clásica. */
    private const TONO_NEUTRO = 41.0;
    /** Formatos con nombre (ancho × alto de corte, en mm) para no escribir las medidas. */
    public const FORMATOS = ['novela' => [135.0, 210.0], 'bolsillo' => [110.0, 178.0], 'a5' => [148.0, 210.0], 'a6' => [105.0, 148.0], 'ebook' => [100.0, 160.0]];
    /** Tamaño máximo de una imagen, en bytes. */
    private const IMAGEN_MAXIMA = 2_000_000;
    private const IMAGEN_TIPOS = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    /** @param array{fondo: string, texto: string, acento: string} $colores */
    private function __construct(
        public readonly string $id,
        public readonly Textos $textos,
        public readonly Formato $formato,
        public readonly string $plantilla,
        public readonly array $colores,
        /** Degradado del fondo, o null si el fondo es un color sólido. */
        public readonly ?Degradado $degradado,
        public readonly string $lomoDireccion,
        /** Idioma del texto de la cubierta, como código BCP 47 (`es`, `pt-BR`): para los lectores de pantalla. */
        public readonly string $idioma,
        /** Tradición cultural (slug de datos/tradiciones.json), o null si no se dio. */
        public readonly ?string $tradicion,
        /** Categoría de Marcalibros (slug de datos/categorias.json), o null si no se dio. */
        public readonly ?string $categoria,
        /** Ilustración de la portada (ruta absoluta de un SVG, PNG, JPG, GIF o WebP), o null. */
        public readonly ?string $imagen = null,
        /** Logo de la editorial (ruta absoluta), o null. */
        public readonly ?string $logo = null,
    ) {
    }

    /**
     * Los archivos de fuera que usa el libro (la ilustración y el logo): si cambian, el libro hay que volver a
     * escribirlo.
     *
     * @return list<string>
     */
    public function archivosExternos(): array
    {
        return array_values(array_filter([$this->imagen, $this->logo]));
    }

    public static function desdeArchivo(string $ruta): self
    {
        if (!is_file($ruta) || !is_readable($ruta)) {
            throw new \InvalidArgumentException("$ruta: no existe o no se puede leer.");
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new \InvalidArgumentException("No se pudo leer $ruta.");
        }
        try {
            $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \InvalidArgumentException("$ruta: el JSON no es válido (" . $error->getMessage() . '). Revisa comas, comillas y llaves.');
        }
        // Un objeto vacío ({}) también sale como [], así que solo se rechazan las listas con algo dentro.
        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            throw new \InvalidArgumentException("$ruta: el JSON debe ser un objeto con los datos del libro, no una lista ni un valor suelto.");
        }

        return self::desdeArray(pathinfo($ruta, PATHINFO_FILENAME), self::rutasDesde(dirname($ruta), self::clavesDeTexto($datos)));
    }

    /**
     * Las rutas de `imagen` y `logo` se escriben respecto a la carpeta del archivo del libro (o de la lista): aquí se
     * convierten en rutas que valen desde cualquier carpeta.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public static function rutasDesde(string $carpeta, array $datos): array
    {
        foreach (['imagen', 'logo'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo]) && trim($datos[$campo]) !== '' && !str_starts_with(trim($datos[$campo]), '/')) {
                $datos[$campo] = $carpeta . '/' . trim($datos[$campo]);
            }
        }

        return $datos;
    }

    /** @param array<string, mixed> $datos */
    public static function desdeArray(string $id, array $datos): self
    {
        $titulo = self::cadena($datos, 'titulo');
        $autor = self::cadena($datos, 'autor');
        if ($titulo === '' || $autor === '') {
            throw new \InvalidArgumentException("$id: 'titulo' y 'autor' son obligatorios.");
        }

        $plantilla = self::cadena($datos, 'plantilla', 'clasica');
        $tradicion = self::clasificacion($id, $datos, 'tradicion', Catalogo::tradicion(...));
        $categoria = self::clasificacion($id, $datos, 'categoria', Catalogo::categoria(...));
        $tema = self::cadena($datos, 'tema');
        if (!in_array($tema, ['', 'claro', 'oscuro'], true)) {
            throw new \InvalidArgumentException("$id: 'tema' debe ser claro u oscuro, no «{$tema}».");
        }
        // Sin colores propios, los de la categoría (con el tema pedido); sin categoría, los de la plantilla, o los de un
        // tono neutro si se pidió un tema.
        $objeto = Plantilla::crear($plantilla);
        $colores = match (true) {
            $categoria !== null => $objeto->paletaConTema($categoria['tono'], $tema === '' ? null : $tema),
            $tema !== '' => $objeto->paletaConTema(self::TONO_NEUTRO, $tema),
            default => $objeto->coloresPorDefecto(),
        };
        foreach (self::objeto($datos, 'colores') as $rol => $color) {
            if (!array_key_exists($rol, $colores)) {
                throw new \InvalidArgumentException("$id: el color '$rol' no existe; usa fondo, texto o acento.");
            }
            if (!is_string($color) || !Color::valido($color)) {
                throw new \InvalidArgumentException("$id: el color '$rol' debe ser #rrggbb.");
            }
            $colores[$rol] = $color;
        }

        $degradado = isset($datos['degradado'])
            ? Degradado::desdeArray(self::objeto($datos, 'degradado'), $colores['fondo'], $colores['texto'])
            : null;

        $nombreDeFormato = $datos['formato'] ?? null;
        if (is_string($nombreDeFormato)) {
            $clave = Nombres::plegar($nombreDeFormato);
            $medida = self::FORMATOS[$clave] ?? throw new \InvalidArgumentException("$id: el formato «{$nombreDeFormato}» no existe; usa " . implode(', ', array_keys(self::FORMATOS)) . ' o ancho_mm y alto_mm.');
            $formato = ['ancho_mm' => $medida[0], 'alto_mm' => $medida[1]];
        } else {
            $formato = self::objeto($datos, 'formato');
        }
        $ancho = self::numero($formato, 'ancho_mm', 135.0, self::LADO_MINIMO, self::LADO_MAXIMO);
        $alto = self::numero($formato, 'alto_mm', 210.0, self::LADO_MINIMO, self::LADO_MAXIMO);
        $sangrado = self::numero($datos, 'sangrado_mm', 3.0, 0.0, self::SANGRADO_MAXIMO);

        $solapas = self::numero($datos, 'solapas_mm', 0.0, 0.0);
        if ($solapas > 0.0 && $solapas < self::SOLAPA_MINIMA) {
            throw new \InvalidArgumentException("$id: 'solapas_mm' debe ser 0 (sin solapas) o " . self::SOLAPA_MINIMA . ' o más.');
        }
        if ($solapas > $ancho) {
            throw new \InvalidArgumentException("$id: 'solapas_mm' ($solapas mm) no puede ser mayor que el ancho de la portada ($ancho mm).");
        }

        if (isset($datos['lomo_mm'])) {
            $lomo = self::numero($datos, 'lomo_mm', null, 0.0, self::LOMO_MAXIMO);
        } else {
            $grosor = self::numero($datos, 'grosor_hoja_mm', 0.1, self::GROSOR_MINIMO, self::GROSOR_MAXIMO);
            $lomo = ceil(self::numero($datos, 'paginas', null, 2.0) / 2) * $grosor;
            if ($lomo > self::LOMO_MAXIMO) {
                throw new \InvalidArgumentException("$id: con esas 'paginas' y ese 'grosor_hoja_mm' el lomo sale de " . round($lomo, 1) . ' mm, y el máximo es ' . self::LOMO_MAXIMO . " mm. Revisa los dos campos o da 'lomo_mm'.");
            }
        }

        $direccion = self::cadena($datos, 'lomo_direccion', 'arriba-abajo');
        if (!in_array($direccion, ['arriba-abajo', 'abajo-arriba'], true)) {
            throw new \InvalidArgumentException("$id: 'lomo_direccion' debe ser arriba-abajo o abajo-arriba.");
        }

        $idioma = self::cadena($datos, 'idioma', 'es');
        if (preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $idioma) !== 1) {
            throw new \InvalidArgumentException("$id: 'idioma' debe ser un código como es, en o pt-BR.");
        }

        $subtitulo = self::cadena($datos, 'subtitulo');
        $traductor = self::cadena($datos, 'traductor');
        $paginas = isset($datos['paginas']) && (is_int($datos['paginas']) || is_float($datos['paginas'])) ? (int) $datos['paginas'] : null;
        $ficha = new Ficha($titulo, $subtitulo, $autor, $traductor, self::cadena($datos, 'epoca'), self::cadena($datos, 'cita'), $tradicion, $categoria, $paginas, $idioma);

        // Lo que se escribe a mano manda; la ficha rellena solo lo que quedó vacío, y solo si hay datos con
        // los que armarla.
        $textos = new Textos(
            titulo: $titulo,
            subtitulo: $subtitulo,
            autor: $autor,
            traductor: $traductor,
            editorial: self::cadena($datos, 'editorial'),
            coleccion: self::cadena($datos, 'coleccion'),
            isbn: self::cadena($datos, 'isbn'),
            contratapa: self::cadena($datos, 'contratapa', $ficha->hayDatos() ? $ficha->contratapa() : ''),
            solapaContratapa: self::cadena($datos, 'solapa_contratapa', $ficha->hayDatos() ? $ficha->solapaContratapa() : ''),
            solapaPortada: self::cadena($datos, 'solapa_portada', $ficha->hayDatos() ? $ficha->solapaPortada() : ''),
            cita: self::cadena($datos, 'cita'),
            volumen: self::cadena($datos, 'volumen'),
        );

        return new self(
            id: $id,
            textos: $textos,
            formato: new Formato(ancho: $ancho, alto: $alto, sangrado: $sangrado, solapas: $solapas, lomo: $lomo),
            plantilla: $plantilla,
            colores: $colores,
            degradado: $degradado,
            lomoDireccion: $direccion,
            idioma: $idioma,
            tradicion: $tradicion['slug'] ?? null,
            categoria: $categoria['slug'] ?? null,
            imagen: self::imagen($id, $datos, 'imagen'),
            logo: self::imagen($id, $datos, 'logo'),
        );
    }

    /**
     * La ruta de una imagen del libro, ya comprobada: que exista, que sea de un tipo que se puede incrustar y que no
     * pese demasiado. Un SVG no puede llevar scripts ni cargar nada de fuera. Null si el libro no la trae.
     *
     * @param array<string, mixed> $datos
     */
    private static function imagen(string $id, array $datos, string $campo): ?string
    {
        $ruta = self::cadena($datos, $campo);
        if ($ruta === '') {
            return null;
        }
        $tipo = self::IMAGEN_TIPOS[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? throw new \InvalidArgumentException("$id: '$campo' debe ser un archivo " . implode(', ', array_keys(self::IMAGEN_TIPOS)) . ", no «{$ruta}».");
        if (!is_file($ruta) || !is_readable($ruta)) {
            throw new \InvalidArgumentException("$id: '$campo': no existe o no se puede leer «{$ruta}».");
        }
        if (filesize($ruta) > self::IMAGEN_MAXIMA) {
            throw new \InvalidArgumentException("$id: '$campo' pesa " . round((int) filesize($ruta) / 1_000_000, 1) . ' MB y el máximo es ' . self::IMAGEN_MAXIMA / 1_000_000 . ' MB.');
        }
        if ($tipo === 'image/svg+xml' && preg_match('/<script|\son[a-z]+\s*=|(?:xlink:)?href\s*=\s*["\'](?!#|data:)/i', (string) file_get_contents($ruta)) === 1) {
            throw new \InvalidArgumentException("$id: '$campo' es un SVG con scripts o con enlaces a otros archivos: no se incrusta.");
        }

        return realpath($ruta) ?: $ruta;
    }

    /**
     * Busca en un catálogo lo que el libro dice en `$campo`, o null si no dice nada.
     *
     * @template T of array{slug: string, nombre: string}
     * @param array<string, mixed> $datos
     * @param callable(string): T $buscar
     * @return T|null
     */
    private static function clasificacion(string $id, array $datos, string $campo, callable $buscar): ?array
    {
        $clave = self::cadena($datos, $campo);
        if ($clave === '') {
            return null;
        }
        try {
            return $buscar($clave);
        } catch (\InvalidArgumentException $error) {
            throw new \InvalidArgumentException("$id: '$campo': " . $error->getMessage());
        }
    }

    /** @param array<string, mixed> $datos */
    private static function cadena(array $datos, string $campo, string $defecto = ''): string
    {
        $valor = $datos[$campo] ?? $defecto;
        if (!is_string($valor)) {
            throw new \InvalidArgumentException("'$campo' debe ser texto.");
        }
        $valor = trim($valor);
        $maximo = self::MAXIMOS[$campo] ?? self::MAXIMO_POR_DEFECTO;
        if (mb_strlen($valor) > $maximo) {
            throw new \InvalidArgumentException("'$campo' tiene " . mb_strlen($valor) . " caracteres y el máximo es $maximo: casi seguro se pegó otro texto en esa celda.");
        }

        return $valor;
    }

    /**
     * Número entre `$minimo` y `$maximo` (sin tope si es null). Con `$defecto` null, el campo es obligatorio.
     *
     * @param array<string, mixed> $datos
     */
    private static function numero(array $datos, string $campo, ?float $defecto, float $minimo, ?float $maximo = null): float
    {
        $valor = $datos[$campo] ?? $defecto;
        if ((!is_int($valor) && !is_float($valor)) || $valor < $minimo || ($maximo !== null && $valor > $maximo)) {
            throw new \InvalidArgumentException($maximo === null
                ? "'$campo' debe ser un número mayor o igual que $minimo."
                : "'$campo' debe ser un número entre $minimo y $maximo.");
        }

        return (float) $valor;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private static function objeto(array $datos, string $campo): array
    {
        $valor = $datos[$campo] ?? [];
        if (!is_array($valor)) {
            throw new \InvalidArgumentException("'$campo' debe ser un objeto JSON.");
        }

        return self::clavesDeTexto($valor);
    }

    /**
     * Un objeto JSON con claves numéricas ("1": ...) sale de json_decode con claves enteras: se pasan a texto.
     *
     * @param array<mixed> $datos
     * @return array<string, mixed>
     */
    private static function clavesDeTexto(array $datos): array
    {
        $resultado = [];
        foreach ($datos as $clave => $valor) {
            $resultado[(string) $clave] = $valor;
        }

        return $resultado;
    }
}
