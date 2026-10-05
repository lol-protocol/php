<?php

declare(strict_types=1);

namespace App;

final class View
{
    public static function render(string $template, array $data = [], bool $sinLayout = false): void
    {
        $content = self::capturar($template, $data);

        if ($sinLayout) {
            echo $content;
            return;
        }

        // El layout recibe solo lo suyo, tomado de $data y no del scope de la
        // vista: una vista que reasigna $titulo (el foreach de segmentacion del
        // Dashboard, las tablas de conversion del Funnel) le cambiaba el <title>
        // a toda la pagina.
        echo self::capturar('layout', [
            'content' => $content,
            'activePage' => $data['activePage'] ?? '',
            'titulo' => $data['titulo'] ?? '',
        ]);
    }

    /** Corre una plantilla en su propio scope y devuelve lo que imprimio. */
    private static function capturar(string $plantilla, array $variables): string
    {
        extract($variables, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__) . '/views/' . $plantilla . '.php';

        return (string) ob_get_clean();
    }
}
