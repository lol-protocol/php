<?php

declare(strict_types=1);

use DefamatoryContentReview\Chat\ChatLineResult;
use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\CombinedReview;
use DefamatoryContentReview\Http\LongText;

/**
 * Revisa el texto de una nota con el filtro de ../defamatory-content-review (o la carpeta de
 * FILTRO_DIFAMACION_DIR), igual que su endpoint de moderación: en español y en inglés a la vez
 * (elegir un idioma no sirve para colar un insulto) y por párrafos si el texto es largo.
 * Una nota habla de un usuario real: un insulto o una amenaza no se guarda, y una acusación,
 * un dato sensible o un dato personal se guarda marcado para revisar (ver api/notas.php).
 */
final class FiltroNotas
{
    private const IDIOMAS = ['spa', 'eng'];
    /** Categorías cuyo 'original' describe el hallazgo («acusación de delito»); en las demás es el insulto mismo. */
    private const DESCRIPTIVAS = ['patterns', 'datos personales'];

    /** @var array<string,ChatLineReviewer> */
    private array $revisores = [];

    public function __construct(private readonly string $dirConfig)
    {
    }

    /** @throws RuntimeException si la librería no está donde se la busca */
    public static function desdeEntorno(): self
    {
        $dir = getenv('FILTRO_DIFAMACION_DIR') ?: __DIR__ . '/../../../defamatory-content-review';
        if (!is_file("$dir/src/Chat/ChatLineReviewer.php")) {
            throw new RuntimeException("no está la librería del filtro en $dir (FILTRO_DIFAMACION_DIR)");
        }
        spl_autoload_register(static function (string $clase) use ($dir): void {
            $prefijo = 'DefamatoryContentReview\\';
            $archivo = $dir . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (str_starts_with($clase, $prefijo) && is_file($archivo)) {
                require $archivo;
            }
        });
        return new self("$dir/config");
    }

    /**
     * @return array{decision: string, motivos: list<string>} decision: approve | review | reject;
     *         motivos: qué encontró («acusación de delito», «dato personal (RUT)», «insulto»), sin repetir
     */
    public function revisar(string $texto): array
    {
        $resultado = LongText::review($texto, fn(string $parte): ChatLineResult => CombinedReview::of(
            $parte,
            array_map(fn(string $idioma): ChatLineResult => $this->revisor($idioma)->review($parte), self::IDIOMAS)
        ));
        $motivos = array_map(
            fn(array $m): string => in_array($m['category'] ?? null, self::DESCRIPTIVAS, true) ? (string) $m['original'] : 'insulto',
            $resultado->getMatches()
        );
        return ['decision' => $resultado->getDecision(), 'motivos' => array_values(array_unique($motivos))];
    }

    private function revisor(string $idioma): ChatLineReviewer
    {
        return $this->revisores[$idioma] ??= ChatLineReviewer::create($this->dirConfig, $idioma);
    }
}
