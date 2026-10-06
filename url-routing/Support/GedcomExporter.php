<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal GEDCOM 5.5.1 export — the interchange format every genealogy
 * program imports. Individuals carry name, sex, birth and death; families
 * are rebuilt from each child's (father, mother) pair. Only personas inside
 * the exported set are referenced, so every pointer in the file resolves.
 */
final class GedcomExporter
{
    private const MESES = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    /**
     * @param list<array{id: int|string, nombres: string, apellidos: string, sexo: ?string,
     *   padre_id: int|string|null, madre_id: int|string|null, nacimiento?: ?string,
     *   lugar_nacimiento?: ?string, defuncion?: ?string}> $personas
     */
    public function export(array $personas, string $titulo): string
    {
        $porId = [];
        foreach ($personas as $p) {
            $porId[(int)$p['id']] = $p;
        }

        // Families: one per distinct (father, mother) pair among the children,
        // keeping only parents that are part of the export.
        $familias = [];
        foreach ($porId as $id => $p) {
            $padre = isset($p['padre_id'], $porId[(int)$p['padre_id']]) ? (int)$p['padre_id'] : null;
            $madre = isset($p['madre_id'], $porId[(int)$p['madre_id']]) ? (int)$p['madre_id'] : null;
            if ($padre === null && $madre === null) {
                continue;
            }
            $clave = ($padre ?? 0) . '-' . ($madre ?? 0);
            $familias[$clave] ??= ['padre' => $padre, 'madre' => $madre, 'hijos' => []];
            $familias[$clave]['hijos'][] = $id;
        }
        ksort($familias);

        $numero = 0;
        $famsDe = [];
        $famcDe = [];
        foreach ($familias as $clave => $f) {
            $ref = '@F' . ++$numero . '@';
            $familias[$clave]['ref'] = $ref;
            foreach ([$f['padre'], $f['madre']] as $progenitor) {
                if ($progenitor !== null) {
                    $famsDe[$progenitor][] = $ref;
                }
            }
            foreach ($f['hijos'] as $hijo) {
                $famcDe[$hijo] = $ref;
            }
        }

        $l = [
            '0 HEAD',
            '1 SOUR LOL_PROTOCOL_URL_ROUTING',
            '1 GEDC',
            '2 VERS 5.5.1',
            '2 FORM LINEAGE-LINKED',
            '1 CHAR UTF-8',
            '1 NOTE ' . self::valor($titulo),
        ];

        foreach ($porId as $id => $p) {
            $l[] = "0 @I{$id}@ INDI";
            $l[] = '1 NAME ' . self::valor($p['nombres']) . ' /' . self::valor($p['apellidos']) . '/';
            $l[] = '1 SEX ' . match ($p['sexo'] ?? null) { 'M' => 'M', 'F' => 'F', default => 'U' };
            self::evento($l, 'BIRT', $p['nacimiento'] ?? null, $p['lugar_nacimiento'] ?? null);
            self::evento($l, 'DEAT', $p['defuncion'] ?? null, null);
            if (isset($famcDe[$id])) {
                $l[] = "1 FAMC {$famcDe[$id]}";
            }
            foreach ($famsDe[$id] ?? [] as $ref) {
                $l[] = "1 FAMS {$ref}";
            }
        }

        foreach ($familias as $f) {
            $l[] = "0 {$f['ref']} FAM";
            if ($f['padre'] !== null) {
                $l[] = "1 HUSB @I{$f['padre']}@";
            }
            if ($f['madre'] !== null) {
                $l[] = "1 WIFE @I{$f['madre']}@";
            }
            foreach ($f['hijos'] as $hijo) {
                $l[] = "1 CHIL @I{$hijo}@";
            }
        }

        $l[] = '0 TRLR';
        return implode("\r\n", $l) . "\r\n";
    }

    /** @param list<string> $l */
    private static function evento(array &$l, string $tag, ?string $fecha, ?string $lugar): void
    {
        if (($fecha === null || $fecha === '') && ($lugar === null || $lugar === '')) {
            return;
        }
        $l[] = "1 {$tag}";
        if ($fecha !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m) === 1) {
            $l[] = '2 DATE ' . (int)$m[3] . ' ' . self::MESES[(int)$m[2] - 1] . ' ' . $m[1];
        }
        if ($lugar !== null && $lugar !== '') {
            $l[] = '2 PLAC ' . self::valor($lugar);
        }
    }

    /** GEDCOM values are single-line, and a literal "@" must be doubled. */
    private static function valor(string $texto): string
    {
        return str_replace('@', '@@', trim((string)preg_replace('/\s+/', ' ', $texto)));
    }
}
