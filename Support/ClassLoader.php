<?php

declare(strict_types=1);

namespace App\Support;

class ClassLoader
{
    public static function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $relative = (string)preg_replace('/^App\\\\/', '', $class);
            $path = str_replace('\\', '/', $relative);
            $file = __DIR__ . '/../' . $path . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }
}
