<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Red de seguridad para cualquier excepcion no capturada en el resto de la
 * app: el detalle completo va al log del servidor via error_log() -nunca a
 * la respuesta-, y el usuario ve un mensaje generico. La pagina de error no
 * usa View::render() ni nada que dependa de la sesion o la base a proposito:
 * si la app ya esta rota, mostrar el error no puede arriesgarse a romperse
 * tambien.
 */
final class ErrorHandler
{
    public static function registrar(): void
    {
        set_exception_handler([self::class, 'manejar']);
    }

    public static function manejar(Throwable $e): void
    {
        error_log(self::formatear($e));

        if (!headers_sent()) {
            http_response_code(500);
        }

        echo self::paginaGenerica();
    }

    /** Linea de log con el detalle real. Publico y puro para poder testearlo. */
    public static function formatear(Throwable $e): string
    {
        return sprintf(
            'Excepcion no capturada: %s en %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
    }

    private static function paginaGenerica(): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<title>Error</title></head><body style="font-family:system-ui,sans-serif;'
            . 'max-width:520px;margin:80px auto;padding:0 20px;color:#333;">'
            . '<h1 style="font-size:18px;">Ocurrió un error</h1>'
            . '<p>Ya quedó registrado. Probá de nuevo en un momento, y si sigue pasando avisale'
            . ' a quien administra el sistema.</p>'
            . '</body></html>';
    }
}
