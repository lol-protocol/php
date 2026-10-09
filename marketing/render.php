#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Rellena el marcador [Marca] de los textos de marketing con el nombre
 * definido en config.json. No modifica los archivos de origen: escribe copias
 * en el directorio de salida (por defecto dist/, ignorado por git).
 *
 * Uso:
 *   php marketing/render.php                  genera dist/
 *   php marketing/render.php --out=RUTA       genera en otra carpeta
 *   php marketing/render.php --check          valida config.json y no escribe nada
 *   php marketing/render.php --config=RUTA    usa otro archivo de configuración
 *
 * Códigos de salida: 0 correcto, 1 configuración inválida o incompleta,
 * 2 uso incorrecto.
 *
 * [Nombre] no se toca: es por destinatario y lo rellena quien envía cada mensaje.
 */

const MARCADOR = '[Marca]';
const MARCADOR_DESTINATARIO = '[Nombre]';
const LARGO_MAXIMO = 80;
const CLAVES_PERMITIDAS = ['marca', 'marca_por_idioma'];

final class ErrorDeConfiguracion extends RuntimeException
{
}

function uso(): string
{
    return <<<TXT
    Uso: php marketing/render.php [--check] [--out=RUTA] [--config=RUTA]

      --check         Valida config.json y cuenta los marcadores; no escribe nada.
      --out=RUTA      Carpeta de salida (por defecto marketing/dist).
      --config=RUTA   Archivo de configuración (por defecto marketing/config.json).
      -h, --help      Muestra esta ayuda.

    TXT;
}

/** @return array{out: string, config: string, check: bool} */
function leerOpciones(array $argv): array
{
    $opciones = ['out' => __DIR__ . '/dist', 'config' => __DIR__ . '/config.json', 'check' => false];
    $total = count($argv);
    for ($i = 1; $i < $total; $i++) {
        $arg = $argv[$i];
        if ($arg === '--check') {
            $opciones['check'] = true;
            continue;
        }
        if ($arg === '-h' || $arg === '--help') {
            fwrite(STDOUT, uso());
            exit(0);
        }
        foreach (['out', 'config'] as $nombre) {
            $valor = null;
            if ($arg === "--$nombre") {
                $valor = $argv[++$i] ?? null;
            } elseif (str_starts_with($arg, "--$nombre=")) {
                $valor = substr($arg, strlen($nombre) + 3);
            } else {
                continue;
            }
            if ($valor === null || $valor === '') {
                fwrite(STDERR, "Falta el valor de --$nombre.\n\n" . uso());
                exit(2);
            }
            $opciones[$nombre] = $valor;
            continue 2;
        }
        fwrite(STDERR, "Opción desconocida: $arg\n\n" . uso());
        exit(2);
    }
    return $opciones;
}

/**
 * Archivos que contienen el marcador, con su código de idioma.
 *
 * @return array<string, string> ruta relativa => código de idioma
 */
function archivosOrigen(string $base): array
{
    $archivos = ['frases.md' => 'es'];
    $encontrados = glob($base . '/idiomas/frases.*.md') ?: [];
    sort($encontrados);
    foreach ($encontrados as $ruta) {
        if (preg_match('/^frases\.(.+)\.md$/', basename($ruta), $m) === 1) {
            $archivos['idiomas/' . basename($ruta)] = $m[1];
        }
    }
    return $archivos;
}

function validarMarca(mixed $valor, string $campo): string
{
    if (!is_string($valor)) {
        throw new ErrorDeConfiguracion("«{$campo}» debe ser un texto.");
    }
    $valor = trim($valor);
    if ($valor === '') {
        throw new ErrorDeConfiguracion("«{$campo}» está vacío: escribe el nombre de la marca en config.json.");
    }
    if (preg_match('//u', $valor) !== 1) {
        throw new ErrorDeConfiguracion("«{$campo}» no es UTF-8 válido.");
    }
    if (preg_match('/\p{Cc}/u', $valor) === 1) {
        throw new ErrorDeConfiguracion("«{$campo}» contiene caracteres de control (saltos de línea, tabulaciones…).");
    }
    if ((int) preg_match_all('/./us', $valor) > LARGO_MAXIMO) {
        throw new ErrorDeConfiguracion("«{$campo}» supera los " . LARGO_MAXIMO . ' caracteres.');
    }
    if (str_contains($valor, MARCADOR) || str_contains($valor, MARCADOR_DESTINATARIO)) {
        throw new ErrorDeConfiguracion("«{$campo}» no puede contener los marcadores " . MARCADOR . ' ni ' . MARCADOR_DESTINATARIO . '.');
    }
    return $valor;
}

/**
 * @param list<string> $idiomasValidos
 * @return array{0: string, 1: array<string, string>} marca general y marcas por idioma
 */
function cargarConfig(string $ruta, array $idiomasValidos): array
{
    if (!is_file($ruta)) {
        throw new ErrorDeConfiguracion("No existe el archivo de configuración: $ruta");
    }
    try {
        $datos = json_decode((string) file_get_contents($ruta), true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new ErrorDeConfiguracion('El archivo de configuración no es JSON válido: ' . $e->getMessage());
    }
    if (!is_array($datos)) {
        throw new ErrorDeConfiguracion('El archivo de configuración debe ser un objeto JSON.');
    }
    foreach (array_keys($datos) as $clave) {
        if (!in_array($clave, CLAVES_PERMITIDAS, true)) {
            throw new ErrorDeConfiguracion('Clave desconocida «' . $clave . '». Claves válidas: ' . implode(', ', CLAVES_PERMITIDAS) . '.');
        }
    }
    $marca = validarMarca($datos['marca'] ?? null, 'marca');

    $porIdioma = $datos['marca_por_idioma'] ?? [];
    if (!is_array($porIdioma)) {
        throw new ErrorDeConfiguracion('«marca_por_idioma» debe ser un objeto {"código": "nombre"}.');
    }
    $validadas = [];
    foreach ($porIdioma as $codigo => $valor) {
        $codigo = (string) $codigo;
        if (!in_array($codigo, $idiomasValidos, true)) {
            throw new ErrorDeConfiguracion("Idioma desconocido «{$codigo}» en marca_por_idioma. Códigos válidos: " . implode(', ', $idiomasValidos) . '.');
        }
        $validadas[$codigo] = validarMarca($valor, "marca_por_idioma.$codigo");
    }
    return [$marca, $validadas];
}

function main(array $argv): int
{
    $opciones = leerOpciones($argv);
    $base = __DIR__;
    $archivos = archivosOrigen($base);

    try {
        [$marca, $porIdioma] = cargarConfig($opciones['config'], array_values(array_unique($archivos)));

        if (!$opciones['check']) {
            $destino = $opciones['out'];
            if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
                throw new ErrorDeConfiguracion("No se pudo crear la carpeta de salida: $destino");
            }
            // Nunca escribir sobre los archivos de origen: eso fijaría la marca en duro.
            $real = realpath($destino);
            if ($real === realpath($base) || $real === realpath($base . '/idiomas')) {
                throw new ErrorDeConfiguracion('La carpeta de salida no puede ser la de los textos de origen.');
            }
        }

        $resumen = [];
        foreach ($archivos as $relativa => $codigo) {
            $texto = file_get_contents($base . '/' . $relativa);
            if ($texto === false) {
                throw new ErrorDeConfiguracion("No se pudo leer $relativa");
            }
            $usada = $porIdioma[$codigo] ?? $marca;
            $cantidad = substr_count($texto, MARCADOR);
            $resumen[] = [$relativa, $codigo, $usada, $cantidad];
            if ($opciones['check']) {
                continue;
            }
            $salida = str_replace(MARCADOR, $usada, $texto);
            $ruta = $opciones['out'] . '/' . $relativa;
            if (!is_dir(dirname($ruta)) && !mkdir(dirname($ruta), 0775, true) && !is_dir(dirname($ruta))) {
                throw new ErrorDeConfiguracion('No se pudo crear la carpeta: ' . dirname($ruta));
            }
            if (file_put_contents($ruta, $salida, LOCK_EX) === false) {
                throw new ErrorDeConfiguracion("No se pudo escribir $ruta");
            }
        }
    } catch (ErrorDeConfiguracion $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        return 1;
    }

    foreach ($resumen as [$relativa, $codigo, $usada, $cantidad]) {
        printf("%-28s %-8s %d × %s\n", $relativa, $codigo, $cantidad, $usada);
    }
    echo $opciones['check']
        ? "Configuración válida. No se escribió nada.\n"
        : 'Listo: ' . count($resumen) . ' archivos en ' . $opciones['out'] . "\n";
    return 0;
}

exit(main($argv));
