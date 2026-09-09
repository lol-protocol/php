<?php

namespace DefamatoryContentReview;

use RuntimeException;

class DefamatoryContentReviewer
{
    private LanguageRegistry $registry;
    private string $languageDir;
    private string $language;

    /** @var array<string,WordList> diccionarios ya cargados, por código ISO 639-3 */
    private array $dictionaries = [];

    /**
     * Tipos de riesgo que por sí solos justifican severidad alta cuando la
     * palabra concreta no declara la suya. La severidad por palabra manda; esto
     * es sólo el respaldo.
     */
    private array $highSeverityRiskTypes = [
        'ordinario', 'moral', 'discapacidad', 'genero', 'religioso', 'etnico',
    ];

    private const SEVERITY_VALUE = ['none' => 0, 'low' => 1, 'medium' => 2, 'high' => 3];

    public function __construct(LanguageRegistry $registry, string $languageDir, string $language = 'spa')
    {
        $this->registry = $registry;
        $this->languageDir = rtrim($languageDir, '/');
        $this->language = $registry->resolve($language);
    }

    public static function create(string $configDir, string $language = 'spa'): self
    {
        $configDir = rtrim($configDir, '/');

        return new self(
            LanguageRegistry::fromConfigDirectory($configDir),
            $configDir . '/languages',
            $language
        );
    }

    // -----------------------------------------------------------------
    // Validación en el idioma principal
    // -----------------------------------------------------------------

    public function validateName(string $name): ValidationResult
    {
        return $this->evaluate($name, [$this->language => 1.0]);
    }

    public function validateFullName(string $firstName, string $lastName): ValidationResult
    {
        $result = $this->validateName(trim($firstName . ' ' . $lastName));
        $this->applyPhoneticChecks($result, $firstName, $lastName);

        return $result;
    }

    /**
     * @param array<int,string> $names
     * @return array<int,ValidationResult>
     */
    public function batchValidateNames(array $names): array
    {
        return array_map(fn(string $name) => $this->validateName($name), $names);
    }

    /**
     * @param array<int,string> $fullNames
     * @return array<int,ValidationResult>
     */
    public function batchValidateFullNames(array $fullNames): array
    {
        return array_map(fn(string $name) => $this->validateName(trim($name)), $fullNames);
    }

    // -----------------------------------------------------------------
    // Validación cruzada entre idiomas asociados
    // -----------------------------------------------------------------

    /**
     * Valida contra el idioma principal y los emparentados con él.
     *
     * En una plataforma genealógica los registros de una región traen apellidos
     * de las lenguas vecinas: un árbol español contiene ramas portuguesas, uno
     * ruso ramas ucranianas. Una coincidencia hallada en un idioma asociado
     * pesa menos que una del principal — su confianza es la afinidad léxica
     * entre ambos — de modo que un insulto grave en portugués marca un nombre
     * español para revisión, pero no lo rechaza con la misma rotundidad que si
     * lo fuera en español.
     *
     * @param float|null $threshold Afinidad mínima para incluir un idioma.
     */
    public function validateAcrossRelated(string $name, ?float $threshold = null): ValidationResult
    {
        return $this->evaluate($name, $this->registry->getValidationSet($this->language, $threshold));
    }

    public function validateFullNameAcrossRelated(
        string $firstName,
        string $lastName,
        ?float $threshold = null
    ): ValidationResult {
        $result = $this->validateAcrossRelated(trim($firstName . ' ' . $lastName), $threshold);
        $this->applyPhoneticChecks($result, $firstName, $lastName);

        return $result;
    }

    /**
     * Valida contra un conjunto explícito de idiomas, cada uno con confianza 1.0.
     * Útil cuando la plataforma ya sabe qué lenguas concurren en un fondo
     * documental concreto, sin depender del modelo de parentesco.
     *
     * @param array<int,string> $languages
     */
    public function validateInLanguages(string $name, array $languages): ValidationResult
    {
        $set = [];
        foreach ($languages as $code) {
            $set[$this->registry->resolve($code)] = 1.0;
        }

        return $this->evaluate($name, $set);
    }

    // -----------------------------------------------------------------
    // Núcleo de evaluación
    // -----------------------------------------------------------------

    /**
     * @param array<string,float> $languageSet código => confianza (1.0 = idioma principal)
     */
    private function evaluate(string $name, array $languageSet): ValidationResult
    {
        $result = new ValidationResult($name, true, $this->language);
        $result->setLanguagesChecked($languageSet);

        $maxScore = 0.0;
        $seen = [];

        foreach ($languageSet as $code => $confidence) {
            foreach ($this->dictionary($code)->findInText($name) as $match) {
                // El mismo término puede estar en varios diccionarios de una
                // familia; se conserva la aparición de mayor confianza.
                $key = $match['found'] . '|' . $match['riskType'];
                if (isset($seen[$key]) && $seen[$key] >= $confidence) {
                    continue;
                }
                $seen[$key] = $confidence;

                $result->addFlaggedTerm($match + [
                    'sourceLanguage' => $code,
                    'confidence' => $confidence,
                ]);

                $maxScore = max($maxScore, $this->scoreOf($match) * $confidence);
            }
        }

        if ($maxScore <= 0.0) {
            return $result->setValid(true)->setSeverity('none');
        }

        return $result->setValid(false)->setSeverity($this->severityFromScore($maxScore));
    }

    private function scoreOf(array $match): float
    {
        $severity = $match['severity'] ?? null;

        if ($severity === null || !isset(self::SEVERITY_VALUE[$severity])) {
            $severity = in_array($match['riskType'] ?? '', $this->highSeverityRiskTypes, true)
                ? 'high'
                : 'medium';
        }

        return (float) self::SEVERITY_VALUE[$severity];
    }

    private function severityFromScore(float $score): string
    {
        if ($score >= 2.5) {
            return 'high';
        }

        if ($score >= 1.5) {
            return 'medium';
        }

        return 'low';
    }

    // -----------------------------------------------------------------
    // Fusión fonética entre nombre y apellido
    // -----------------------------------------------------------------

    /**
     * "Elba Gina" ("el vagina"), "Felipe Lotas" ("Feli-pelotas"), "Susana
     * Oria" ("su zanahoria"): nombre y apellido, ninguno ofensivo por
     * separado, que al leerse seguidos componen otra palabra. Se suma a lo ya
     * detectado por evaluate() sin sustituirlo — el resultado ya trae, si los
     * hay, los hallazgos literales de validateName()/validateAcrossRelated().
     *
     * Sólo se aplica a los idiomas con reglas de plegado fonético registradas
     * (ver PhoneticFolderRegistry) y sólo cuando ambos campos traen texto.
     */
    private function applyPhoneticChecks(ValidationResult $result, string $firstName, string $lastName): void
    {
        $wordList = $this->dictionary($this->language);

        if (!$wordList->supportsPhoneticFolding() || trim($firstName) === '' || trim($lastName) === '') {
            return;
        }

        $detector = new PhoneticFusionDetector($wordList);
        $added = false;

        foreach ($detector->detectFusion($firstName, $lastName) as $match) {
            $result->addFlaggedTerm($match + ['sourceLanguage' => $this->language, 'confidence' => 1.0]);
            $added = true;
        }

        foreach ([$firstName, $lastName] as $field) {
            if ($wordList->search($field) !== null) {
                continue; // ya cubierto por la búsqueda literal de evaluate()
            }

            $variant = $detector->detectVariant($field);

            if ($variant !== null) {
                $result->addFlaggedTerm($variant + ['sourceLanguage' => $this->language, 'confidence' => 1.0]);
                $added = true;
            }
        }

        if ($added) {
            $this->recomputeSeverity($result);
        }
    }

    /**
     * Recalcula validez y severidad a partir de TODOS los términos marcados
     * hasta ahora (literales más, si los hubo, los de fusión fonética).
     */
    private function recomputeSeverity(ValidationResult $result): void
    {
        $maxScore = 0.0;

        foreach ($result->getFlaggedTerms() as $term) {
            $maxScore = max($maxScore, $this->scoreOf($term) * ($term['confidence'] ?? 1.0));
        }

        if ($maxScore <= 0.0) {
            $result->setValid(true)->setSeverity('none');
            return;
        }

        $result->setValid(false)->setSeverity($this->severityFromScore($maxScore));
    }

    // -----------------------------------------------------------------
    // Informes
    // -----------------------------------------------------------------

    public function getDetailedReport(ValidationResult $result): array
    {
        $data = $result->toArray();
        $decision = $this->decide($result);

        return $data + [
            'decision' => $decision,
            'recommendation' => $this->recommendationFor($decision, $result),
            'riskAnalysis' => $this->analyzeRisks($result),
        ];
    }

    /**
     * Traduce severidad, colisión de nombre y método de detección en una
     * acción concreta.
     *
     * Un término que además es apellido documentado nunca se rechaza solo:
     * baja a revisión humana. Rechazar "Cerda" o "Moreno" en automático
     * borraría linajes reales del árbol. Lo mismo para una fusión fonética:
     * es una inferencia, no una coincidencia literal, así que tampoco basta
     * por sí sola para un rechazo automático.
     */
    public function decide(ValidationResult $result): string
    {
        if ($result->isValid()) {
            return 'accept';
        }

        return match ($result->getSeverity()) {
            'high' => ($result->hasNameCollision() || $result->hasOnlyPhoneticDetections()) ? 'review' : 'reject',
            'medium' => 'review',
            default => 'accept_with_flag',
        };
    }

    private function recommendationFor(string $decision, ValidationResult $result): string
    {
        $types = implode(', ', $result->getFlaggedRiskTypes());

        return match ($decision) {
            'reject' => "Rechazar: contenido gravemente ofensivo (tipos de riesgo: {$types}).",
            'review' => match (true) {
                $result->hasNameCollision() => "Revisión humana: coincide con términos ofensivos ({$types}) " .
                    "pero también con apellidos documentados.",
                $result->hasOnlyPhoneticDetections() => "Revisión humana: nombre y apellido, fusionados, " .
                    "componen un término ofensivo ({$types}) que ninguno de los dos tiene por separado.",
                default => "Revisión humana: términos potencialmente ofensivos ({$types}).",
            },
            'accept_with_flag' => "Aceptar con marca: términos de bajo riesgo ({$types}).",
            default => 'Aceptar: sin contenido difamatorio detectado.',
        };
    }

    private function analyzeRisks(ValidationResult $result): array
    {
        $descriptions = [
            'animal' => 'Comparación con animales',
            'intelectual' => 'Menoscabo de la capacidad intelectual',
            'discapacidad' => 'Referencia despectiva a discapacidad',
            'fisico' => 'Menoscabo de la apariencia física',
            'moral' => 'Imputación moral o delictiva',
            'genero' => 'Insulto por género u orientación sexual',
            'ordinario' => 'Léxico soez u obsceno',
            'burlesco' => 'Burla o ridiculización',
            'etnico' => 'Insulto étnico o racial',
            'religioso' => 'Insulto religioso',
            'fonetico' => 'Fusión fonética entre nombre y apellido',
        ];

        $analysis = [];

        foreach ($result->getFlaggedRiskTypes() as $riskType) {
            $terms = $result->getTermsByRiskType($riskType);
            $worst = 'low';

            foreach ($terms as $term) {
                if (self::SEVERITY_VALUE[$term['severity']] > self::SEVERITY_VALUE[$worst]) {
                    $worst = $term['severity'];
                }
            }

            $analysis[$riskType] = [
                'description' => $descriptions[$riskType] ?? $riskType,
                'level' => $worst,
                'isSevere' => $worst === 'high',
                'termCount' => count($terms),
                'languages' => array_values(array_unique(array_column($terms, 'sourceLanguage'))),
                'detectionMethods' => array_values(array_unique(array_column($terms, 'detectionMethod'))),
            ];
        }

        return $analysis;
    }

    // -----------------------------------------------------------------
    // Idiomas y diccionarios
    // -----------------------------------------------------------------

    private function dictionary(string $code): WordList
    {
        $code = $this->registry->resolve($code);

        if (!isset($this->dictionaries[$code])) {
            $path = "{$this->languageDir}/{$code}.php";

            if (!is_file($path)) {
                throw new RuntimeException("No existe diccionario para el idioma '{$code}' en {$path}.");
            }

            $this->dictionaries[$code] = WordList::fromLanguageFile($path, $code);
        }

        return $this->dictionaries[$code];
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    /** Acepta códigos de dos o tres letras; siempre se guarda el de tres. */
    public function setLanguage(string $language): self
    {
        $this->language = $this->registry->resolve($language);
        return $this;
    }

    /** @return array<string,float> idiomas asociados => afinidad */
    public function getRelatedLanguages(?float $threshold = null): array
    {
        return $this->registry->getRelated($this->language, $threshold);
    }

    public function getRegistry(): LanguageRegistry
    {
        return $this->registry;
    }

    public function getWordList(?string $language = null): WordList
    {
        return $this->dictionary($language ?? $this->language);
    }

    public function getWordListStatistics(?string $language = null): array
    {
        return $this->getWordList($language)->getStatistics();
    }

    public function setHighSeverityRiskTypes(array $riskTypes): self
    {
        $this->highSeverityRiskTypes = $riskTypes;
        return $this;
    }
}
