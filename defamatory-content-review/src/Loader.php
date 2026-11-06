<?php

namespace DefamatoryContentReview;

/**
 * Carga la librería clase por clase, sin Composer, para quien la usa desde otra carpeta del repositorio
 * (url-routing, sistema-nuevo). Este archivo no depende de nada: el que llama lo incluye con require y
 * después pide `register()`. Es el único cargador: antes cada proyecto tenía su copia.
 */
final class Loader
{
    private const PREFIX = 'DefamatoryContentReview\\';

    /** @var array<string,true> */
    private static array $registered = [];

    /** Si la carpeta tiene la librería (un revisor de chat en src/Chat). */
    public static function exists(string $dir): bool
    {
        return is_file("{$dir}/src/Chat/ChatLineReviewer.php");
    }

    /** Registra el cargador de $dir una sola vez; las llamadas repetidas no suman otro. */
    public static function register(string $dir): void
    {
        if (isset(self::$registered[$dir])) {
            return;
        }
        self::$registered[$dir] = true;
        spl_autoload_register(static function (string $class) use ($dir): void {
            $file = $dir . '/src/' . str_replace('\\', '/', substr($class, strlen(self::PREFIX))) . '.php';
            if (str_starts_with($class, self::PREFIX) && is_file($file)) {
                require $file;
            }
        });
    }
}
