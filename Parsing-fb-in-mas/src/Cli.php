<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/** Lo que comparten los programas de `bin/`, para no copiarlo en cada uno. */
final class Cli
{
    /**
     * Carga la lista de exclusión que se pasó con `--excluir=`. Si no se puede leer, o una línea está mal escrita,
     * lo dice por la salida de errores y termina con código 2. Si la lista no tiene ninguna entrada (todo comentado,
     * o vacía) avisa: casi seguro es un error y no se estaría excluyendo a nadie.
     */
    public static function exclusiones(string $ruta): Exclusiones
    {
        try {
            $lista = Exclusiones::desdeArchivo($ruta);
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
        if ($lista->estaVacia()) {
            fwrite(STDERR, "Aviso: la lista de exclusión $ruta no tiene ninguna entrada, así que no se excluye a nadie.\n");
        }
        return $lista;
    }

    /**
     * Revisa los argumentos ANTES de interpretarlos. Una opción desconocida o mal escrita no se toma por un archivo
     * de entrada: `--excluir lista.txt` (con espacio en vez de `=`) o `--sin-revison` no deben dejar que el programa siga
     * sin la exclusión que se quería. Termina con código 2 y el uso; `-h` y `--help` escriben el uso y terminan con 0.
     *
     * @param list<string> $argumentos los de la línea de comandos, sin el nombre del programa
     * @param list<string> $conocidas `--jsonl` (sin valor) o `--excluir=` (con valor)
     */
    public static function validar(array $argumentos, array $conocidas, string $uso): void
    {
        foreach ($argumentos as $arg) {
            if ($arg === '-h' || $arg === '--help') {
                fwrite(STDOUT, $uso);
                exit(0);
            }
            if (!str_starts_with($arg, '--')) {
                continue;
            }
            foreach ($conocidas as $conocida) {
                if (str_ends_with($conocida, '=')) {
                    if (str_starts_with($arg, $conocida)) {
                        continue 2;
                    }
                    if ($arg === rtrim($conocida, '=')) {
                        fwrite(STDERR, "$arg pide un valor y se escribe junto, sin espacio: $conocida" . "valor\n$uso");
                        exit(2);
                    }
                } elseif ($arg === $conocida) {
                    continue 2;
                }
            }
            fwrite(STDERR, 'Opción desconocida «' . explode('=', $arg, 2)[0] . "».\n$uso");
            exit(2);
        }
    }

    /** Un entero de `$minimo` en adelante; si el valor no lo es, lo dice y termina con código 2. */
    public static function entero(string $opcion, string $valor, int $minimo): int
    {
        if (preg_match('/^\d+$/', $valor) !== 1 || (int) $valor < $minimo) {
            fwrite(STDERR, "$opcion pide un número entero de $minimo en adelante (llegó «{$valor}»)\n");
            exit(2);
        }
        return (int) $valor;
    }

    /** Un número (con punto decimal); si el valor no lo es, lo dice y termina con código 2. */
    public static function decimal(string $opcion, string $valor): float
    {
        if (!is_numeric($valor)) {
            fwrite(STDERR, "$opcion pide un número, como 0.6 (llegó «{$valor}»)\n");
            exit(2);
        }
        return (float) $valor;
    }

    /** Una fecha AAAA-MM-DD que existe; si el valor no lo es, lo dice y termina con código 2. */
    public static function fecha(string $opcion, string $valor): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            fwrite(STDERR, "$opcion pide una fecha real AAAA-MM-DD (llegó «{$valor}»)\n");
            exit(2);
        }
        return $valor;
    }
}
