<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use DOMDocument;
use DOMXPath;

/**
 * Lee de una página guardada solo lo que el sitio pone para que otros lo lean:
 * las etiquetas `<meta>` (Open Graph y Twitter Cards), el `<link rel="canonical">`
 * y el JSON-LD de schema.org. No usa clases CSS ni la estructura de la página: eso
 * cambia sin aviso y casi todo se pinta con JavaScript.
 *
 * Del JSON-LD devuelve una lista blanca de campos. El correo y el teléfono, aunque
 * vengan, no salen de aquí.
 */
final class Metadatos
{
    /**
     * @return array{meta: array<string, string>, canonica: ?string, titulo: ?string, persona: ?array<string, string>}
     */
    public static function leer(string $html): array
    {
        $doc = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        $xp = new DOMXPath($doc);

        $meta = [];
        foreach ($xp->query('//meta[@content and (@property or @name)]') ?: [] as $nodo) {
            /** @var \DOMElement $nodo */
            $clave = strtolower($nodo->getAttribute('property') !== '' ? $nodo->getAttribute('property') : $nodo->getAttribute('name'));
            $valor = self::limpiar($nodo->getAttribute('content'));
            if ($clave !== '' && $valor !== '' && !isset($meta[$clave])) {
                $meta[$clave] = $valor;
            }
        }

        $canonica = null;
        foreach ($xp->query('//link[@rel="canonical" and @href]') ?: [] as $nodo) {
            /** @var \DOMElement $nodo */
            $canonica = trim($nodo->getAttribute('href'));
            break;
        }

        $titulo = null;
        foreach ($xp->query('//title') ?: [] as $nodo) {
            $titulo = self::limpiar($nodo->textContent);
            break;
        }

        $persona = null;
        foreach ($xp->query('//script[@type="application/ld+json"]') ?: [] as $nodo) {
            $json = json_decode($nodo->textContent, true);
            if (is_array($json) && ($p = self::buscarPersona($json)) !== null) {
                $persona = self::filtrarPersona($p);
                break;
            }
        }

        return ['meta' => $meta, 'canonica' => $canonica, 'titulo' => $titulo, 'persona' => $persona];
    }

    public static function limpiar(string $texto): string
    {
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = (string) preg_replace('/\s+/u', ' ', $texto);
        return trim($texto);
    }

    /**
     * @param array<mixed> $nodo
     * @return ?array<mixed>
     */
    private static function buscarPersona(array $nodo): ?array
    {
        $tipo = $nodo['@type'] ?? null;
        if ($tipo === 'Person' || (is_array($tipo) && in_array('Person', $tipo, true))) {
            return $nodo;
        }
        foreach ($nodo as $hijo) {
            if (is_array($hijo) && ($p = self::buscarPersona($hijo)) !== null) {
                return $p;
            }
        }
        return null;
    }

    /**
     * @param array<mixed> $p
     * @return array<string, string>
     */
    private static function filtrarPersona(array $p): array
    {
        $salida = [];
        foreach (['name' => 'nombre', 'jobTitle' => 'titular', 'description' => 'descripcion', 'url' => 'url'] as $origen => $destino) {
            if (isset($p[$origen]) && is_string($p[$origen]) && self::limpiar($p[$origen]) !== '') {
                $salida[$destino] = self::limpiar($p[$origen]);
            }
        }
        $dir = $p['address'] ?? null;
        if (is_string($dir)) {
            $lugar = self::limpiar($dir);
        } elseif (is_array($dir)) {
            $partes = [];
            foreach (['addressLocality', 'addressRegion', 'addressCountry'] as $campo) {
                if (isset($dir[$campo]) && is_string($dir[$campo]) && self::limpiar($dir[$campo]) !== '') {
                    $partes[] = self::limpiar($dir[$campo]);
                }
            }
            $lugar = implode(', ', $partes);
        } else {
            $lugar = '';
        }
        if ($lugar !== '') {
            $salida['lugar'] = $lugar;
        }
        return $salida;
    }
}
