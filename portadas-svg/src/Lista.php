<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Lee una lista de libros en CSV: una fila por libro, con una fila de encabezados. Cada fila pasa por los
 * mismos controles que un JSON (`Libro::desdeArray`), y los errores de todas las filas se dan juntos para
 * corregir la lista de una vez.
 *
 * Los encabezados no distinguen mayúsculas ni tildes (`Título` es `titulo`) y el separador puede ser
 * coma, punto y coma o tabulador. Una celda vacía equivale a no dar el dato.
 */
final class Lista
{
    /** Columnas de texto que pasan tal cual al libro. */
    private const TEXTO = [
        'titulo', 'subtitulo', 'autor', 'traductor', 'epoca', 'tradicion', 'categoria', 'isbn',
        'editorial', 'coleccion', 'cita', 'contratapa', 'solapa_portada', 'solapa_contratapa', 'plantilla',
    ];
    /** Columnas de medidas en mm. */
    private const MEDIDAS = ['solapas_mm', 'lomo_mm'];
    /** Columnas de color, que van a `colores`. */
    private const COLORES = ['fondo', 'texto', 'acento'];
    /** Cuántos caracteres puede tener el nombre de carpeta de un libro. */
    private const ID_MAXIMO = 80;

    /**
     * @return list<Libro>
     * @throws \InvalidArgumentException si la lista no se puede leer o alguna fila tiene errores
     */
    public static function leer(string $ruta): array
    {
        $contenido = is_file($ruta) && is_readable($ruta) ? file_get_contents($ruta) : false;
        if ($contenido === false) {
            throw new \InvalidArgumentException("$ruta: no existe o no se puede leer.");
        }
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            throw new \InvalidArgumentException("$ruta: el archivo no está en UTF-8. En Excel o LibreOffice, guárdalo como «CSV UTF-8».");
        }

        $filas = self::filas($contenido, $ruta);
        $encabezados = self::encabezados(array_shift($filas) ?? [], $ruta);

        $libros = [];
        $errores = [];
        $ids = [];
        foreach ($filas as $indice => $celdas) {
            $numero = $indice + 2; // la fila 1 son los encabezados: así coincide con el número de fila de la hoja
            $id = null;
            try {
                $datos = self::datosDeFila($encabezados, $celdas);
                if ($datos === null) {
                    continue;
                }
                $id = is_string($datos['id'] ?? null) ? $datos['id'] : self::idDe($datos);
                unset($datos['id']);
                if (isset($ids[$id])) {
                    throw new \InvalidArgumentException("el id '$id' ya lo usa la fila {$ids[$id]}; da una columna 'id' distinta.");
                }
                $ids[$id] = $numero;
                $libros[] = Libro::desdeArray($id, $datos);
            } catch (\InvalidArgumentException $error) {
                // Los errores de Libro empiezan por el id, que aquí es un nombre inventado: no aporta.
                $mensaje = $id !== null && str_starts_with($error->getMessage(), "$id: ") ? substr($error->getMessage(), strlen($id) + 2) : $error->getMessage();
                $errores[] = "fila $numero" . self::nombreDe($celdas, $encabezados) . ': ' . $mensaje;
            }
        }

        if ($errores !== []) {
            throw new \InvalidArgumentException("$ruta: " . count($errores) . (count($errores) === 1 ? ' fila con errores' : ' filas con errores') . ":\n  " . implode("\n  ", $errores));
        }
        if ($libros === []) {
            throw new \InvalidArgumentException("$ruta: no tiene ningún libro (solo la fila de encabezados o nada).");
        }

        return $libros;
    }

    /** @return list<list<string|null>> */
    private static function filas(string $contenido, string $ruta): array
    {
        $primera = strtok($contenido, "\r\n");
        $primera = $primera === false ? '' : $primera;
        $separador = ',';
        foreach ([';', "\t"] as $candidato) {
            if (substr_count($primera, $candidato) > substr_count($primera, $separador)) {
                $separador = $candidato;
            }
        }

        $flujo = fopen('php://memory', 'r+');
        if ($flujo === false) {
            throw new \RuntimeException("No se pudo leer $ruta.");
        }
        fwrite($flujo, $contenido);
        rewind($flujo);
        $filas = [];
        while (($celdas = fgetcsv($flujo, null, $separador, '"', '')) !== false) {
            // fgetcsv da [null] para una línea en blanco.
            $filas[] = array_map(static fn (?string $celda): ?string => $celda === null ? null : str_replace(["\r\n", "\r"], "\n", $celda), $celdas);
        }
        fclose($flujo);

        return $filas;
    }

    /**
     * @param list<string|null> $celdas
     * @return list<string> el nombre de cada columna, normalizado
     */
    private static function encabezados(array $celdas, string $ruta): array
    {
        $validas = [...self::TEXTO, ...self::MEDIDAS, ...self::COLORES, 'id', 'paginas'];
        $nombres = [];
        $desconocidas = [];
        foreach ($celdas as $celda) {
            $nombre = str_replace(' ', '_', Nombres::plegar((string) $celda));
            if (!in_array($nombre, $validas, true)) {
                $desconocidas[] = (string) $celda;
            } elseif (in_array($nombre, $nombres, true)) {
                throw new \InvalidArgumentException("$ruta: la columna '$nombre' está repetida.");
            }
            $nombres[] = $nombre;
        }
        if ($desconocidas !== []) {
            throw new \InvalidArgumentException("$ruta: columnas que no conozco: " . implode(', ', array_map(static fn (string $nombre): string => "«{$nombre}»", $desconocidas)) . '. Las válidas son: ' . implode(', ', $validas) . '.');
        }
        foreach (['titulo', 'autor'] as $obligatoria) {
            if (!in_array($obligatoria, $nombres, true)) {
                throw new \InvalidArgumentException("$ruta: falta la columna '$obligatoria' en la fila de encabezados.");
            }
        }

        return $nombres;
    }

    /**
     * Los datos de una fila como los espera `Libro::desdeArray` (más el `id`, si lo trae). Null si la
     * fila está en blanco.
     *
     * @param list<string> $encabezados
     * @param list<string|null> $celdas
     * @return array<string, mixed>|null
     */
    private static function datosDeFila(array $encabezados, array $celdas): ?array
    {
        $datos = [];
        $colores = [];
        foreach ($celdas as $posicion => $celda) {
            $valor = trim((string) $celda);
            if ($valor === '') {
                continue;
            }
            $columna = $encabezados[$posicion] ?? throw new \InvalidArgumentException('tiene más celdas que columnas; si un texto lleva comas, ponlo entre comillas.');
            if (in_array($columna, self::TEXTO, true)) {
                $datos[$columna] = $valor;
            } elseif (in_array($columna, self::COLORES, true)) {
                $colores[$columna] = $valor;
            } elseif (in_array($columna, self::MEDIDAS, true)) {
                $datos[$columna] = self::numero($columna, $valor);
            } elseif ($columna === 'paginas') {
                $datos['paginas'] = ctype_digit($valor) ? (int) $valor : throw new \InvalidArgumentException("'paginas' debe ser un número entero, no «{$valor}».");
            } else {
                $datos['id'] = preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $valor) === 1 && strlen($valor) <= self::ID_MAXIMO
                    ? $valor
                    : throw new \InvalidArgumentException("'id' debe llevar solo letras minúsculas sin tilde, cifras y guiones (hasta " . self::ID_MAXIMO . " caracteres), no «{$valor}».");
            }
        }

        if ($colores !== []) {
            $datos['colores'] = $colores;
        }

        return $datos === [] ? null : $datos;
    }

    /** Un número de mm, con punto o coma decimal. */
    private static function numero(string $columna, string $valor): float
    {
        if (preg_match('/^\d+(?:[.,]\d+)?$/', $valor) !== 1) {
            throw new \InvalidArgumentException("'$columna' debe ser un número en milímetros, no «{$valor}».");
        }

        return (float) str_replace(',', '.', $valor);
    }

    /**
     * Nombre de carpeta a partir del autor y el título, para el libro que no trae `id`.
     *
     * @param array<string, mixed> $datos
     */
    private static function idDe(array $datos): string
    {
        $texto = static fn (string $campo): string => is_string($datos[$campo] ?? null) ? $datos[$campo] : '';
        $id = Nombres::slug($texto('autor') . ' ' . $texto('titulo'), self::ID_MAXIMO);
        if ($id === '') {
            throw new \InvalidArgumentException("no se pudo sacar un nombre de carpeta del autor y el título (¿están vacíos o sin letras latinas?); da una columna 'id'.");
        }

        return $id;
    }

    /**
     * « («Título»)» para encabezar un error, o nada si la fila no tiene título.
     *
     * @param list<string|null> $celdas
     * @param list<string> $encabezados
     */
    private static function nombreDe(array $celdas, array $encabezados): string
    {
        $posicion = array_search('titulo', $encabezados, true);
        $titulo = $posicion === false ? '' : trim((string) ($celdas[$posicion] ?? ''));

        return $titulo === '' ? '' : ' («' . (mb_strlen($titulo) > 40 ? mb_substr($titulo, 0, 39) . '…' : $titulo) . '»)';
    }
}
