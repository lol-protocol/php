<?php

declare(strict_types=1);

namespace App\Routing;

class ByLengthMatchStrategy implements MatchStrategy
{
    use ResolvesActions;

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     */
    public function matches(array $segments, array $config): bool
    {
        return !empty($segments) && ctype_digit($segments[0]);
    }

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     * @return array{controller: string, method: string, params: array<string, mixed>}|null
     */
    public function resolve(array $segments, array $config): ?array
    {
        $id = $segments[0];
        $entry = $config['by_length'][strlen($id)] ?? null;

        if (!$entry) {
            return null;
        }

        // A type that takes filters reads a second segment of 2 or more digits as one (/1001/48213/); one digit is
        // an action, as ever. What each filter segment is comes from its width, like any other id.
        $permitidos = $entry['filtros'] ?? [];
        if ($permitidos !== [] && isset($segments[1]) && ctype_digit($segments[1]) && strlen($segments[1]) >= 2) {
            $filtros = $this->filtros(array_slice($segments, 1), $permitidos, $config['by_length']);

            return $filtros === null ? null : [
                'controller' => $entry['controller'],
                'method' => 'show',
                'params' => ['id' => $id, 'filtros' => $filtros],
            ];
        }

        if (count($segments) > 2) {
            return null;
        }

        $actionCode = $segments[1] ?? null;
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode, 'show');

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['id' => $id],
        ];
    }

    /**
     * The filters of /{id}/{filtro}/[{filtro}/]: type => id. Null when a segment is not a filter this type accepts
     * (not digits, a width that names no type or one that is not allowed) or a type repeats.
     *
     * @param list<string> $segmentos
     * @param list<string> $permitidos
     * @param array<int, array<string, mixed>> $porLargo
     * @return array<string, string>|null
     */
    private function filtros(array $segmentos, array $permitidos, array $porLargo): ?array
    {
        $filtros = [];
        foreach ($segmentos as $segmento) {
            $tipo = ctype_digit($segmento) && strlen($segmento) >= 2 ? ($porLargo[strlen($segmento)]['type'] ?? null) : null;
            if ($tipo === null || !in_array($tipo, $permitidos, true) || isset($filtros[$tipo])) {
                return null;
            }
            $filtros[$tipo] = $segmento;
        }

        return $filtros;
    }
}
