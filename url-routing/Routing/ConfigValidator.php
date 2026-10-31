<?php

declare(strict_types=1);

namespace App\Routing;

class ConfigValidator
{
    public static function validate(array $config): void
    {
        // A PHP array cannot repeat a key, so a repeated digit length cannot get here: the
        // mistake that can is a key that is not a digit count at all.
        if (isset($config['by_length'])) {
            foreach ($config['by_length'] as $length => $entry) {
                if (!is_int($length) || $length < 1) {
                    throw new \InvalidArgumentException("by_length key '{$length}' must be a positive number of digits");
                }
                if (!isset($entry['type']) || !isset($entry['controller'])) {
                    throw new \InvalidArgumentException("Invalid by_length entry at length {$length}");
                }
            }
        }

        // Validate reserved entries
        if (isset($config['reserved'])) {
            foreach ($config['reserved'] as $key => $entry) {
                if (!isset($entry['controller'])) {
                    throw new \InvalidArgumentException("Invalid reserved entry for key '{$key}'");
                }
                // The router asks by_length, then place, then reserved, and the first that matches wins:
                // a reserved key one of them also matches would never be reached.
                $segment = (string)$key;
                if (ctype_digit($segment) && isset($config['by_length'][strlen($segment)])) {
                    throw new \InvalidArgumentException("Reserved entry '{$segment}' is shadowed by by_length[" . strlen($segment) . ']');
                }
                if (isset($config['place']) && preg_match('/^[a-z]{2}$/', $segment) === 1) {
                    throw new \InvalidArgumentException("Reserved entry '{$segment}' is shadowed by place: a 2-letter segment is a country");
                }
                if (isset($entry['ruta']) && (!is_int($entry['ruta']) || !isset($entry['method']))) {
                    throw new \InvalidArgumentException("Reserved entry '{$key}': ruta needs a segment count and a method");
                }
            }
        }

        if (isset($config['archivos'])) {
            foreach ($config['archivos'] as $nombre => $entry) {
                if (!isset($entry['controller'], $entry['method'])) {
                    throw new \InvalidArgumentException("Invalid archivos entry for '{$nombre}'");
                }
            }
        }

        // A redirect target is a local path: "//host" would send the browser elsewhere.
        if (isset($config['redirect'])) {
            foreach ($config['redirect'] as $desde => $hacia) {
                if (!is_string($hacia) || preg_match('#^/(?!/)#', $hacia) !== 1) {
                    throw new \InvalidArgumentException("Invalid redirect target for '{$desde}'");
                }
            }
        }

        // Validate place entry if present
        if (isset($config['place'])) {
            if (!isset($config['place']['controller'])) {
                throw new \InvalidArgumentException('Invalid place entry: missing controller');
            }
        }
    }
}
