<?php

declare(strict_types=1);

namespace App\Routing;

class ConfigValidator
{
    public static function validate(array $config): void
    {
        // Validate by_length entries
        if (isset($config['by_length'])) {
            $lengths = array_keys($config['by_length']);
            $unique = array_unique($lengths);
            if (count($lengths) !== count($unique)) {
                throw new \InvalidArgumentException('Duplicate digit lengths in by_length config');
            }

            foreach ($config['by_length'] as $length => $entry) {
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
