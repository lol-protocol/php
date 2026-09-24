<?php

namespace App\Support;

class ClassLoader
{
    public static function register()
    {
        spl_autoload_register(function ($class) {
            $relative = preg_replace('/^App\\\\/', '', $class);
            $path = str_replace('\\', '/', $relative);
            $file = __DIR__ . '/../' . $path . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }
}
