<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use DefamatoryContentReview\Dictionary\{WordList, WordListScanner};

/**
 * Lista de temas de chat de un idioma (config/chat-topics/<código>.php):
 * palabras con sus formas regulares, y frases con forma. Colaborador
 * interno de ChatLineReviewer.
 *
 * Las palabras de la categoría `ambiguous` («coger», «bomba», «concha», que
 * también es un nombre) sólo cuentan si la misma línea trae, del mismo
 * riskType, algo que no sea ambiguo ni de severidad `low`. Sin eso, «vamos a
 * coger el bus» sería contenido sexual.
 */
final class ChatTopics
{
    private const AMBIGUOUS = 'ambiguous';

    private WordList $words;
    /** @var array<int,array<string,mixed>> */
    private array $patterns;
    /** @var array<string,true> */
    private array $legit = [];
    /** @var array<string,true> */
    private array $everyday = [];
    /** true: también las dobles («puuta»); 'elongated': sólo rachas de 3 o más («puuuta»); false: nada */
    private bool|string $collapseRepeats;
    /** @var array{types?:list<string>, before?:list<string>, after?:list<string>} */
    private array $targeting;

    /** @param array<string,mixed> $config ['meta' => …, 'words' => categoría => entradas, 'patterns' => …] */
    public function __construct(array $config, string $language)
    {
        $words = TopicInflector::expand($config['words'] ?? [], $language);
        $this->words = new WordList(['meta' => $config['meta'] ?? [], 'words' => $words], $language);
        $this->collapseRepeats = $config['meta']['collapseRepeats'] ?? 'elongated';
        foreach ($config['legit'] ?? [] as $word) {
            $this->legit[RepeatedLetters::key($word)] = true;
        }
        foreach ($config['everyday'] ?? [] as $word) {
            $this->everyday[mb_strtolower($word)] = true;
        }
        $this->targeting = $config['targeted'] ?? [];
        $patterns = $config['patterns'] ?? [];
        $this->patterns = $this->collapseRepeats
            ? array_map(fn(array $pattern): array => ['pattern' => RepeatedLetters::tolerant($pattern['pattern'])] + $pattern, $patterns)
            : $patterns;
    }

    public static function fromFile(string $path, string $language, ?string $region = null): ?self { return is_file($path) ? new self(\DefamatoryContentReview\Language\Regions::apply(require $path, $region), $language) : null; }

    /**
     * Los términos de `$list` en la línea, también los escritos con 3 o más
     * letras iguales seguidas («puuuta», «cazzzo»): eso no lo escribe nadie sin
     * querer, en ningún idioma. Donde el idioma lo pide (meta `collapseRepeats`
     * => true) también los de letras dobles («puuta»), con `repeat => doubled`;
     * con false, ninguno. Ver RepeatedLetters. Las palabras de `everyday`
     * («moc», «sık») no se buscan: ver EverydayWords.
     *
     * @return array<int,array<string,mixed>>
     */
    public function scan(WordList $list, string $text): array
    {
        $text = EverydayWords::mask($text, $this->everyday);
        $search = fn(string $phrase): ?array => $list->search($phrase);

        return WordListScanner::scan($text, $this->collapseRepeats !== false ? RepeatedLetters::searcher($search, $this->legit, $this->collapseRepeats === true) : $search);
    }

    /** @return array{types?:list<string>, before?:list<string>, after?:list<string>} ver TargetContext */
    public function targeting(): array { return $this->targeting; }

    /** @return array<string,mixed>|null la entrada de la lista de temas para esa palabra exacta */
    public function word(string $word): ?array { return $this->words->search($word); }
    /** @return array<int,array<string,mixed>> las frases con forma («te voy a matar»), ver ChatPatternMatcher */
    public function patterns(): array { return $this->patterns; }

    /**
     * @param string $text una lectura de la línea (ver SpacedLetters::variants())
     * @return array<int,array<string,mixed>>
     */
    public function find(string $text): array
    {
        $matches = array_merge($this->scan($this->words, $text), ChatPatternMatcher::find($text, $this->patterns));
        $firm = [];
        foreach ($matches as $match) {
            if ($match['category'] !== self::AMBIGUOUS && $match['severity'] !== 'low') {
                $firm[$match['riskType']] = true;
            }
        }

        return array_values(array_filter(
            $matches,
            fn(array $match): bool => $match['category'] !== self::AMBIGUOUS || isset($firm[$match['riskType']])
        ));
    }
}
