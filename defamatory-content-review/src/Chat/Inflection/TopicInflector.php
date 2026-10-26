<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Expande las entradas con `forms` (noun, adj, verb) en una entrada por forma: «follar» cubre «follamos»,
 * «fóllame»…; `also` agrega a mano las irregulares. Un idioma sin TopicInflection deja todo literal.
 *
 * La ofensa no es simétrica («cerdo» suena mal, «cerda» horrible; «zorros» son animales): cada forma lleva
 * sus rasgos (`m.sg`, `f.pl`, `sg`, `pl`, `verb`) y `by` le da a cada uno su severidad (gana el más
 * específico: `f.pl` > `f` > `pl`; `none` la deja fuera). Lo escrito manda: una forma que ya es entrada
 * propia no se genera. Las generadas no heredan `nameCollision` («Cerda» es apellido, «cerdas» no).
 */
final class TopicInflector
{
    /** @return array<int,string> */
    public static function forms(string $language, string $lemma, string $kind): array
    {
        $class = InflectionLanguages::classFor($language);

        return $class === null ? [$lemma] : (new $class())->forms($lemma, $kind);
    }

    /** @return array<string,string> forma => rasgos: `sg`/`pl` (sustantivo), `m.sg`… `f.pl` (adjetivo), `infl` (adjetivo declinado), `verb` */
    public static function tagged(string $language, string $lemma, string $kind, bool $diminutives = false): array
    {
        $forms = self::forms($language, $lemma, $kind);
        if ($kind === 'verb') { return array_fill_keys($forms, 'verb'); }
        if (InflectionLanguages::inflects($language, $kind)) { return [$lemma => 'sg'] + array_fill_keys($forms, 'infl'); }
        $masculine = $kind === 'adj' ? self::forms($language, $lemma, 'noun') : $forms;
        $feminine = array_values(array_diff($forms, $masculine));
        $gender = $feminine === [] ? '' : 'm.';
        $tags = [];
        foreach ($forms as $form) {
            $tags[$form] = match (true) {
                $form === $lemma => $gender . 'sg',
                !in_array($form, $feminine, true) => $gender . 'pl',
                $form === $feminine[0] => 'f.sg', // el femenino singular sale primero: ver cada TopicInflection
                default => 'f.pl',
            };
        }

        return $diminutives ? $tags + Diminutives::tagged($language, $lemma, $kind) : $tags;
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $categories categoría => entradas
     * @param bool $capGenerated en los diccionarios: una forma generada sin `by` llega como mucho a `medium`
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function expand(array $categories, string $language, bool $capGenerated = false): array
    {
        $written = [];
        foreach ($categories as $entries) {
            foreach ($entries as $entry) {
                $written[mb_strtolower((string) $entry['word'])] = true;
            }
        }
        $expanded = [];
        foreach ($categories as $category => $entries) {
            foreach ($entries as $entry) {
                $base = array_diff_key($entry, ['forms' => 1, 'also' => 1, 'by' => 1]);
                $dim = (bool) preg_grep('/^dim/', array_keys($entry['by'] ?? []));
                $tagged = isset($entry['forms']) ? self::tagged($language, $entry['word'], $entry['forms'], $dim) : [];
                foreach ($tagged ?: [$entry['word'] => ''] as $form => $tag) {
                    $own = $form === $entry['word'];
                    if (!$own && isset($written[mb_strtolower($form)])) {
                        continue; // lo escrito manda
                    }
                    $severity = FormSeverity::of($tag, $entry['by'] ?? [], $base['severity'] ?? 'medium', $capGenerated && !$own);
                    $row = ['word' => $form] + $base;
                    $row['severity'] = $severity;
                    if (!$own) {
                        $row['generated'] = true;
                        if (isset($row['nameCollision'])) {
                            $row['nameCollision'] = false;
                        }
                    }
                    if ($severity !== 'none') {
                        $expanded[$category][] = $row;
                    }
                }
                foreach (array_diff($entry['also'] ?? [], array_keys($tagged)) as $form) {
                    $expanded[$category][] = ['word' => $form] + $base;
                }
            }
        }

        return $expanded;
    }
}
