<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * A place: a 2-letter country first (ISO 3166-1), then up to two codes of
 * 1-3 letters (an ISO 3166-2 subdivision, a UN/LOCODE city). The country is
 * what makes it a place: as a first segment, 1 letter is a system function
 * and 3 letters a language, so neither reaches here.
 */
class PlaceMatchStrategy implements MatchStrategy
{
    use ResolvesActions;

    private const PAIS = '/^[a-z]{2}$/';
    private const SUBDIVISION = '/^[a-z]{1,3}$/';

    public function matches(array $segments, array $config): bool
    {
        return isset($config['place']) && preg_match(self::PAIS, $segments[0] ?? '') === 1;
    }

    public function resolve(array $segments, array $config): ?array
    {
        $entry = $config['place'];
        $codes = $segments;
        $actionCode = null;

        if (ctype_digit(end($codes))) {
            $actionCode = array_pop($codes);
        }

        if (empty($codes) || count($codes) > 3) {
            return null;
        }

        foreach (array_slice($codes, 1) as $code) {
            if (preg_match(self::SUBDIVISION, $code) !== 1) {
                return null;
            }
        }

        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode, 'show');

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['codes' => $codes],
        ];
    }
}
