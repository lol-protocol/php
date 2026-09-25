<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Los términos 'etnico' de severidad alta son los más propensos a coincidir
 * con un gentilicio o apellido real. No se exige que todos lleven
 * nameCollision (eso depende de revisión nativa, ver CONTRIBUTING.md), pero
 * se fija cuántos quedan sin marcar para que la lista no crezca sin querer.
 */
class EthnicTermsTrackingTest extends TestCase
{
    /**
     * Umbral revisado. Cada subida anterior (162 → 195) añadió formas
     * femeninas o jerga comprobadas una a una como no-apellidos; el detalle
     * está en el CHANGELOG. Antes de subirlo, revisa las entradas nuevas y
     * anota el motivo en el CHANGELOG.
     */
    private const REVIEWED_THRESHOLD = 195;

    public function testHighSeverityEthnicTermsWithoutNameCollisionIsTracked(): void
    {
        $unflagged = [];

        foreach (glob(__DIR__ . '/../config/languages/*.php') as $file) {
            if (basename($file) === 'supported-languages.php') {
                continue;
            }

            $data = require $file;
            foreach ($data['words'] ?? [] as $words) {
                foreach ($words as $word) {
                    if (
                        ($word['riskType'] ?? '') === 'etnico'
                        && ($word['severity'] ?? '') === 'high'
                        && empty($word['nameCollision'])
                    ) {
                        $unflagged[] = basename($file, '.php') . ':' . $word['word'];
                    }
                }
            }
        }

        $this->assertLessThanOrEqual(
            self::REVIEWED_THRESHOLD,
            count($unflagged),
            'Crecieron los términos étnicos de severidad alta sin nameCollision revisado: ' .
                implode(', ', array_slice($unflagged, 0, 10)) . '...'
        );
    }
}
