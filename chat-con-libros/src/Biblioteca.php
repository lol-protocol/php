<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Los libros que se pueden elegir: los .txt y .epub de ejemplos/ y mis-libros/, y cada subcarpeta
 * de ellas que tenga libros, que se consulta como una colección (la obra de un autor, una saga…).
 *
 * El id de un libro es su ruta relativa a la raíz del proyecto («ejemplos/muestra.txt»,
 * «mis-libros/Elena Varela»). La web solo acepta ids de esta lista, nunca una ruta del cliente.
 *
 * Sinónimos: los generales (datos/sinonimos.tsv) más, si existen, los del libro
 * («<libro>.sinonimos.tsv» junto al archivo) o los de la colección («sinonimos.tsv» dentro de su carpeta).
 */
final class Biblioteca
{
    public const EXTENSIONES = ['txt', 'epub'];

    /** @param list<string> $carpetas subcarpetas de la raíz donde se buscan libros */
    public function __construct(
        private readonly string $raiz,
        private readonly array $carpetas = ['ejemplos', 'mis-libros'],
    ) {
    }

    /**
     * Archivos sueltos y colecciones, por carpeta y en orden alfabético (primero los archivos).
     *
     * @return list<string>
     */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->carpetas as $carpeta) {
            if (!is_dir($this->raiz . '/' . $carpeta)) {
                continue; // p. ej. mis-libros/, que no existe hasta que pones algo ahí
            }
            $entradas = scandir($this->raiz . '/' . $carpeta) ?: [];
            sort($entradas);
            $archivos = $colecciones = [];
            foreach ($entradas as $entrada) {
                $ruta = $this->raiz . '/' . $carpeta . '/' . $entrada;
                if ($entrada === '' || $entrada[0] === '.') {
                    continue;
                }
                if (is_file($ruta) && self::esLibro($entrada)) {
                    $archivos[] = $carpeta . '/' . $entrada;
                } elseif (is_dir($ruta) && self::librosDe($ruta) !== []) {
                    $colecciones[] = $carpeta . '/' . $entrada;
                }
            }
            array_push($ids, ...$archivos, ...$colecciones);
        }
        return $ids;
    }

    public function cargar(string $id): Libro
    {
        return self::cargarRuta($this->raiz . '/' . $id);
    }

    public function sinonimos(string $id): Sinonimos
    {
        return self::sinonimosDeRuta($this->raiz, $this->raiz . '/' . $id);
    }

    /**
     * Un archivo (.txt o .epub) o una carpeta con libros, que se junta en una colección. Un libro de
     * la carpeta sin texto se salta; si no queda ninguno, el resultado trae cero pasajes.
     */
    public static function cargarRuta(string $ruta): Libro
    {
        if (!is_dir($ruta)) {
            return Libro::desdeArchivo($ruta);
        }
        $libros = [];
        foreach (self::librosDe($ruta) as $archivo) {
            $libro = Libro::desdeArchivo($archivo);
            if ($libro->pasajes !== []) {
                $libros[] = $libro;
            }
        }
        return Libro::coleccion(self::nombreDeColeccion($ruta), $libros);
    }

    /** Los sinónimos generales de $raiz/datos/ y los del libro o de la colección en $ruta. */
    public static function sinonimosDeRuta(string $raiz, string $ruta): Sinonimos
    {
        return Sinonimos::desdeArchivos([
            $raiz . '/datos/sinonimos.tsv',
            is_dir($ruta) ? rtrim($ruta, '/') . '/sinonimos.tsv' : (string) preg_replace('/\.(txt|epub)$/i', '', $ruta) . '.sinonimos.tsv',
        ]);
    }

    /** @return list<string> las rutas de los libros de una carpeta, en orden alfabético */
    public static function librosDe(string $carpeta): array
    {
        $libros = [];
        $entradas = scandir($carpeta) ?: [];
        sort($entradas);
        foreach ($entradas as $entrada) {
            if ($entrada !== '' && $entrada[0] !== '.' && self::esLibro($entrada) && is_file($carpeta . '/' . $entrada)) {
                $libros[] = rtrim($carpeta, '/') . '/' . $entrada;
            }
        }
        return $libros;
    }

    private static function esLibro(string $nombre): bool
    {
        return in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), self::EXTENSIONES, true);
    }

    /** «obra-de-elena-varela» → «Obra de elena varela». */
    private static function nombreDeColeccion(string $ruta): string
    {
        $nombre = trim(str_replace(['-', '_'], ' ', basename(rtrim($ruta, '/'))));
        return $nombre === '' ? 'Colección' : mb_strtoupper(mb_substr($nombre, 0, 1)) . mb_substr($nombre, 1);
    }
}
