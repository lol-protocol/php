<?php

declare(strict_types=1);

use DefamatoryContentReview\Http\TextMarks;
use DefamatoryContentReview\Loader;

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

    public function __construct(private readonly string $dirConfig)
    {
    }

    /** @throws RuntimeException si la librería no está donde se la busca */
    public static function desdeEntorno(): self
    {
        $dir = getenv('FILTRO_DIFAMACION_DIR') ?: __DIR__ . '/../../../defamatory-content-review';
        if (!is_file("$dir/src/Loader.php") || !is_file("$dir/src/Chat/ChatLineReviewer.php")) {
            throw new RuntimeException("no está la librería del filtro en $dir (FILTRO_DIFAMACION_DIR)");
        }
        require_once "$dir/src/Loader.php";
        Loader::register($dir);
        return new self("$dir/config");
    }

    /**
     * @return array{decision: string, motivos: list<string>} decision: approve | review | reject;
     *         motivos: qué encontró («acusación de delito», «dato personal (RUT)», «insulto»), sin repetir
     */
    public function revisar(string $texto): array
    {
        ['decision' => $decision, 'marks' => $motivos] = TextMarks::of($texto, $this->dirConfig, self::IDIOMAS);
        return ['decision' => $decision, 'motivos' => $motivos];
    }
}
