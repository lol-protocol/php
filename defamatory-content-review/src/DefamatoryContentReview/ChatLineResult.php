<?php

namespace DefamatoryContentReview;

/** Resultado de ChatLineReviewer::review(): qué se encontró, la decisión y la línea ya censurada. */
final class ChatLineResult
{
    /** @param array<int,array<string,mixed>> $matches cada uno con found, riskType, severity y contentType */
    public function __construct(
        private readonly string $line,
        private readonly array $matches,
        private readonly string $decision
    ) { }

    public function getLine(): string { return $this->line; }
    /** @return array<int,array<string,mixed>> */
    public function getMatches(): array { return $this->matches; }
    /** 'approve', 'review' o 'reject'. */
    public function getDecision(): string { return $this->decision; }
    public function shouldCensor(): bool { return $this->decision !== 'approve'; }

    /** @return array<int,string> difamatorio, burlesco, sexual y/o belico, sin repetir */
    public function getContentTypes(): array
    {
        return array_values(array_unique(array_column($this->matches, 'contentType')));
    }

    public function hasContentType(string $type): bool { return in_array($type, $this->getContentTypes(), true); }

    /** La línea con cada término que no sea 'low' tapado con asteriscos. */
    public function censored(): string
    {
        $line = $this->line;
        foreach ($this->matches as $match) {
            if ($match['severity'] !== 'low') {
                $line = str_ireplace($match['found'], str_repeat('*', mb_strlen($match['found'])), $line);
            }
        }

        return $line;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'decision' => $this->decision,
            'contentTypes' => $this->getContentTypes(),
            'matches' => $this->matches,
            'censored' => $this->censored(),
        ];
    }
}
