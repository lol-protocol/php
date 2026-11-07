<?php

declare(strict_types=1);

namespace App\Support;

use DefamatoryContentReview\Http\TextMarks;
use DefamatoryContentReview\Loader;

/**
 * Marks what the defamation filter (../defamatory-content-review, or the folder in FILTRO_DIFAMACION_DIR)
 * finds in the text of a pedido de retiro. It only informs whoever reviews: the text of a complaint is
 * written about third parties and often repeats the accusation it complains about, so nothing is ever
 * blocked here. The library is loaded class by class, with no Composer (its Loader); when it is not there
 * the pedido is stored without marks (and the log says so), because a complaint must never be lost for lack
 * of a filter. The same lookup sistema-nuevo/servidor-php/codigo/FiltroNotas.php does, with the same TextMarks.
 */
final class FiltroPedidos
{
    private const IDIOMAS = ['spa', 'eng'];

    /** @return string|null the marks, comma-separated, or null when there are none or the filter is not available */
    public static function marcas(string $texto): ?string
    {
        $dir = getenv('FILTRO_DIFAMACION_DIR') ?: dirname(__DIR__, 2) . '/defamatory-content-review';
        if (!is_file("{$dir}/src/Loader.php") || !is_file("{$dir}/src/Chat/ChatLineReviewer.php")) {
            log_info('filtro de difamación no disponible: el pedido de retiro se guarda sin marcas', ['dir' => basename($dir)]);
            return null;
        }
        require_once "{$dir}/src/Loader.php";
        Loader::register($dir);

        $marcas = TextMarks::of($texto, "{$dir}/config", self::IDIOMAS)['marks'];

        return $marcas === [] ? null : implode(', ', $marcas);
    }
}
