<?php

namespace DefamatoryContentReview\Language;

/**
 * Variantes de un idioma por país: «es-MX», «es_AR», «spa-CL». La misma palabra
 * no ofende igual en todas partes: «puto» es un insulto homófobo en México y un
 * intensificador en España («los putos deberes»); «polla» es la lotería en Chile.
 *
 * Un archivo de config/languages/ o config/chat-topics/ puede traer
 * `'regions' => ['MX' => [categoría => entradas]]`. Con ese país, cada entrada
 * reemplaza a la de la misma palabra en el idioma base (esté en la categoría que
 * esté) o se suma si no estaba; `'severity' => 'none'` la quita. Sin país, o con
 * uno sin variante, se usa el idioma tal cual.
 */
final class Regions
{
    /** @return array{0:string,1:?string} el código del idioma y el país en mayúsculas: «es-mx» → [es, MX], «spa» → [spa, null] */
    public static function split(string $code): array
    {
        if (preg_match('/^\s*([a-z]{2,3})[-_]([a-z]{2}|\d{3})\s*$/i', $code, $m) === 1) {
            return [strtolower($m[1]), strtoupper($m[2])];
        }

        return [$code, null];
    }

    /** «spa» y «MX» → «spa-MX»; sin país, el idioma. */
    public static function join(string $language, ?string $region): string
    {
        return $region === null ? $language : "{$language}-{$region}";
    }

    /**
     * @param array<string,mixed> $config un archivo de config/languages/ o config/chat-topics/
     * @return array<string,mixed> el mismo, con la variante del país aplicada y sin `regions`
     */
    public static function apply(array $config, ?string $region): array
    {
        $overlay = $region === null ? [] : ($config['regions'][$region] ?? []);
        unset($config['regions']);
        $replaced = [];
        foreach ($overlay as $entries) {
            foreach ($entries as $entry) {
                $replaced[mb_strtolower($entry['word'])] = true;
            }
        }
        if ($replaced === []) {
            return $config;
        }
        foreach ($config['words'] ?? [] as $category => $entries) {
            $config['words'][$category] = array_values(array_filter(
                $entries,
                fn($entry): bool => !isset($replaced[mb_strtolower(is_array($entry) ? $entry['word'] : $entry)])
            ));
        }
        foreach ($overlay as $category => $entries) {
            foreach ($entries as $entry) {
                if (($entry['severity'] ?? '') !== 'none') {
                    $config['words'][$category][] = $entry;
                }
            }
        }

        return $config;
    }
}
