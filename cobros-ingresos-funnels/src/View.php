<?php

declare(strict_types=1);

namespace App;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewsPath = dirname(__DIR__) . '/views';

        ob_start();
        require $viewsPath . '/' . $template . '.php';
        $content = ob_get_clean();

        require $viewsPath . '/layout.php';
    }
}
