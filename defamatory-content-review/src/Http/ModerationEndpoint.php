<?php

namespace DefamatoryContentReview\Http;

use DefamatoryContentReview\Chat\{ChatLineResult, ChatLineReviewer};
use DefamatoryContentReview\Language\LanguageRegistry;

/**
 * Endpoint HTTP de moderación de chat, sin framework: recibe el cuerpo JSON
 * `{"text": "…", "language": "spa"}` y responde la decisión de
 * ChatLineReviewer. public/moderar.php lo conecta a PHP; esta clase no toca
 * superglobales ni cabeceras, así se prueba sin servidor.
 *
 * Límites: sólo POST, cuerpo de hasta MAX_BODY_BYTES, `text` de hasta
 * MAX_TEXT_CHARS caracteres y `language` (opcional) un código soportado, de
 * tres letras o su alias de dos («es»). Los errores llevan
 * `{"error": {"code", "message"}}`.
 */
final class ModerationEndpoint
{
    public const MAX_BODY_BYTES = 16384;
    public const MAX_TEXT_CHARS = 2000;

    private readonly LanguageRegistry $registry;
    /** @var array<string,ChatLineReviewer> */
    private array $reviewers = [];

    public function __construct(private readonly string $configDir, private readonly string $defaultLanguage = 'spa')
    {
        $this->registry = LanguageRegistry::fromConfigDirectory($configDir);
    }

    /** @return array{status:int, headers:array<string,string>, body:array<string,mixed>} */
    public function handle(string $method, string $body): array
    {
        if (strtoupper($method) !== 'POST') {
            return self::error(405, 'metodo_no_permitido', 'Usa POST.', ['Allow' => 'POST']);
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            return self::error(413, 'cuerpo_demasiado_grande', 'El cuerpo supera ' . self::MAX_BODY_BYTES . ' bytes.');
        }
        $input = json_decode($body, true);
        if (!is_array($input) || !is_string($input['text'] ?? null)) {
            return self::error(400, 'texto_requerido', 'Envía un objeto JSON en UTF-8 con "text" de tipo string.');
        }
        if (mb_strlen($input['text']) > self::MAX_TEXT_CHARS) {
            return self::error(413, 'texto_demasiado_largo', 'El texto supera ' . self::MAX_TEXT_CHARS . ' caracteres.');
        }
        $language = $input['language'] ?? $this->defaultLanguage;
        if (!is_string($language) || !$this->registry->isSupported($language)) {
            return self::error(400, 'idioma_no_soportado', 'Usa un código de idioma soportado, como "spa" o "es".');
        }
        $language = $this->registry->resolve($language);

        return ['status' => 200, 'headers' => [], 'body' => self::present($this->reviewer($language)->review($input['text']), $language)];
    }

    private function reviewer(string $language): ChatLineReviewer
    {
        return $this->reviewers[$language] ??= ChatLineReviewer::create($this->configDir, $language);
    }

    /** @return array<string,mixed> */
    private static function present(ChatLineResult $result, string $language): array
    {
        return [
            'language' => $language,
            'decision' => $result->getDecision(),
            'censor' => $result->shouldCensor(),
            'contentTypes' => $result->getContentTypes(),
            'censored' => $result->censored(),
            'matches' => array_map(fn(array $match): array => [
                'found' => $match['found'],
                'term' => $match['original'],
                'contentType' => $match['contentType'],
                'severity' => $match['severity'],
            ], $result->getMatches()),
        ];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int, headers:array<string,string>, body:array<string,mixed>}
     */
    private static function error(int $status, string $code, string $message, array $headers = []): array
    {
        return ['status' => $status, 'headers' => $headers, 'body' => ['error' => ['code' => $code, 'message' => $message]]];
    }
}
