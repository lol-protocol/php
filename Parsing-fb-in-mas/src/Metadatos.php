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

        $persona ??= self::microdatos($xp);

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
     * Persona en microdatos (`itemtype=…schema.org/Person`, `itemprop=…`), que usan sitios de
     * genealogía como WikiTree y Find a Grave. Solo cuentan las propiedades de esa persona,
     * no las de un objeto anidado (un lugar con su propio `name`).
     *
     * @return ?array<string, string>
     */
    private static function microdatos(DOMXPath $xp): ?array
    {
        foreach ($xp->query('//*[@itemscope and contains(@itemtype, "schema.org/Person")]') ?: [] as $raiz) {
            $p = [];
            foreach ($xp->query('.//*[@itemprop]', $raiz) ?: [] as $nodo) {
                /** @var \DOMElement $nodo */
                $duenos = $xp->query('ancestor::*[@itemscope][1]', $nodo);
                $dueno = $duenos === false ? null : $duenos->item(0);
                if ($dueno === null || !$dueno->isSameNode($raiz)) {
                    continue;
                }
                $prop = $nodo->getAttribute('itemprop');
                if (isset($p[$prop])) {
                    continue;
                }
                $valor = $nodo->getAttribute('content') !== '' ? $nodo->getAttribute('content')
                    : ($nodo->getAttribute('datetime') !== '' ? $nodo->getAttribute('datetime') : $nodo->textContent);
                $p[$prop] = self::limpiar($valor);
            }
            if ($p !== []) {
                $p['@type'] = 'Person';
                return self::filtrarPersona($p);
            }
        }
        return null;
    }

    /**
     * Fecha de schema.org reducida a año, año-mes o año-mes-día; lo demás se descarta.
     */
    private static function fecha(mixed $valor): ?string
    {
        if (is_string($valor) && preg_match('/^(\d{4}(?:-\d{2}(?:-\d{2})?)?)/', trim($valor), $m) === 1) {
            return $m[1];
        }
        return null;
    }

    private static function lugar(mixed $valor): string
    {
        if (is_string($valor)) {
            return self::limpiar($valor);
        }
        if (is_array($valor)) {
            $nombre = Json::texto($valor, 'name');
            if ($nombre !== null) {
                return self::limpiar($nombre);
            }
            $partes = [];
            foreach (['addressLocality', 'addressRegion', 'addressCountry'] as $campo) {
                $v = Json::texto($valor, 'address', $campo) ?? Json::texto($valor, $campo);
                if ($v !== null && self::limpiar($v) !== '') {
                    $partes[] = self::limpiar($v);
                }
            }
            return implode(', ', $partes);
        }
        return '';
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
        $lugar = self::lugar($p['address'] ?? null);
        if ($lugar !== '') {
            $salida['lugar'] = $lugar;
        }

        // Fechas y lugares de vida y muerte: solo si la fuente da fecha de muerte.
        $muerte = self::fecha($p['deathDate'] ?? null);
        if ($muerte !== null) {
            $salida['muerte'] = $muerte;
            foreach (['birthDate' => 'nacimiento'] as $origen => $destino) {
                $f = self::fecha($p[$origen] ?? null);
                if ($f !== null) {
                    $salida[$destino] = $f;
                }
            }
            foreach (['birthPlace' => 'lugar_nacimiento', 'deathPlace' => 'lugar_muerte'] as $origen => $destino) {
                $l = self::lugar($p[$origen] ?? null);
                if ($l !== '') {
                    $salida[$destino] = $l;
                }
            }
        }
        return $salida;
    }
}
