<?php

namespace DefamatoryContentReview\Http;

/**
 * Los hallazgos de una línea tal como los muestra ModerationEndpoint: uno por
 * aparición. «follar» está en el diccionario (difamatorio) y en la lista
 * sexual del chat; antes salía dos veces. Ahora sale una, con los dos tipos en
 * `contentTypes` (y el primero en `contentType`) y la severidad más alta. Las
 * repeticiones reales («puta puta») siguen contando dos veces.
 */
final class Findings
{
    private const RANK = ['low' => 0, 'medium' => 1, 'high' => 2];

    /**
     * @param array<int,array<string,mixed>> $matches de ChatLineResult::getMatches()
     * @return list<array{found:string,term:string,contentType:string,contentTypes:list<string>,severity:string}>
     */
    public static function of(array $matches): array
    {
        $findings = [];
        foreach ($matches as $match) {
            $type = (string) $match['contentType'];
            foreach ($findings as &$finding) {
                if ($finding['found'] === $match['found'] && $finding['term'] === $match['original'] && !in_array($type, $finding['contentTypes'], true)) {
                    $finding['contentTypes'][] = $type;
                    $finding['severity'] = self::RANK[$match['severity']] > self::RANK[$finding['severity']] ? $match['severity'] : $finding['severity'];
                    continue 2;
                }
            }
            unset($finding);
            $findings[] = ['found' => (string) $match['found'], 'term' => (string) $match['original'], 'contentType' => $type, 'contentTypes' => [$type], 'severity' => (string) $match['severity']];
        }

        return $findings;
    }
}
