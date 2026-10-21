<?php

namespace Tests\Dictionary;

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
     * está en el CHANGELOG. Desde que las frases (consignas como «heil
     * hitler», «14/88») no cuentan —una frase no es un apellido—, son 165.
     * Antes de subirlo, revisa las entradas nuevas y anota el motivo en el CHANGELOG.
     */
    private const REVIEWED_THRESHOLD = 165;

    public function testHighSeverityEthnicTermsWithoutNameCollisionIsTracked(): void
    {
        $unflagged = [];

        foreach (glob(__DIR__ . '/../../config/languages/*.php') as $file) {
            $data = require $file;
            foreach ($data['words'] ?? [] as $words) {
                foreach ($words as $word) {
                    if (
                        ($word['riskType'] ?? '') === 'etnico'
                        && ($word['severity'] ?? '') === 'high'
                        && empty($word['nameCollision'])
                        && preg_match('~[\s/]~u', $word['word']) !== 1 // «heil hitler», «14/88»: una frase no es un apellido
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
