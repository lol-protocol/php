<?php

declare(strict_types=1);

namespace App\Support;

use DefamatoryContentReview\Chat\ChatLineResult;
use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\CombinedReview;
use DefamatoryContentReview\Http\LongText;

/**
 * Marks what the defamation filter (../defamatory-content-review, or the folder in FILTRO_DIFAMACION_DIR)
 * finds in the text of a pedido de retiro. It only informs whoever reviews: the text of a complaint is
 * written about third parties and often repeats the accusation it complains about, so nothing is ever
 * blocked here. The library is loaded class by class, with no Composer; when it is not there the pedido
 * is stored without marks (and the log says so once per request), because a complaint must never be lost
 * for lack of a filter. The same lookup sistema-nuevo/servidor-php/codigo/FiltroNotas.php does.
 */
final class FiltroPedidos
{
    private const IDIOMAS = ['spa', 'eng'];
    /** Categories whose 'original' already says what was found («acusación de delito»); in the rest it is the insult itself. */
    private const DESCRIPTIVAS = ['patterns', 'datos personales'];

    /** @return string|null the marks, comma-separated, or null when there are none or the filter is not available */
    public static function marcas(string $texto): ?string
    {
        $dir = getenv('FILTRO_DIFAMACION_DIR') ?: dirname(__DIR__, 2) . '/defamatory-content-review';
        if (!is_file("{$dir}/src/Chat/ChatLineReviewer.php")) {
            log_info('filtro de difamación no disponible: el pedido de retiro se guarda sin marcas', ['dir' => basename($dir)]);
            return null;
        }
        self::cargar($dir);

        $revisores = array_map(static fn(string $idioma): ChatLineReviewer => ChatLineReviewer::create("{$dir}/config", $idioma), self::IDIOMAS);
        $resultado = LongText::review($texto, static fn(string $parte): ChatLineResult => CombinedReview::of(
            $parte,
            array_map(static fn(ChatLineReviewer $revisor): ChatLineResult => $revisor->review($parte), $revisores)
        ));
        $marcas = array_map(
            static fn(array $m): string => in_array($m['category'] ?? null, self::DESCRIPTIVAS, true) ? (string)$m['original'] : 'insulto',
            $resultado->getMatches()
        );

        return $marcas === [] ? null : implode(', ', array_values(array_unique($marcas)));
    }

    private static function cargar(string $dir): void
    {
        static $cargado = [];
        if (isset($cargado[$dir])) {
            return;
        }
        $cargado[$dir] = true;
        spl_autoload_register(static function (string $clase) use ($dir): void {
            $prefijo = 'DefamatoryContentReview\\';
            $archivo = $dir . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (str_starts_with($clase, $prefijo) && is_file($archivo)) {
                require $archivo;
            }
        });
    }
}
