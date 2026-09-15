<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Apellidos compuestos con guion/conector (cubierto vía literal — la vía
 * fonética se prueba en PhoneticFusionRegistryTest), y el gap conocido y
 * deliberado de la distancia de edición.
 */
class StructuralEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testHyphenatedSurnameChecksBothParts(): void
    {
        $result = $this->reviewer->validateFullName('Ana', 'Pérez-Cerda');

        $this->assertFalse($result->isValid());
        $this->assertSame(['Cerda'], array_column($result->getFlaggedTerms(), 'term'));
    }

    public function testCompoundSurnameWithConnectorWordsIsNotDisrupted(): void
    {
        // "de la Cruz", "del Bosque": el conector no es un término del
        // diccionario, no debería interferir con el resto de la comprobación.
        $result = $this->reviewer->validateFullName('Juan', 'de la Cruz');

        $this->assertTrue($result->isValid());
    }

    /**
     * "Cerrda" (con letra duplicada) no coincide con la entrada "cerda". Se
     * podría cerrar colapsando letras dobles en normalize(), pero eso
     * generaría falsos positivos impredecibles a través de ~4.600 palabras en
     * 30 idiomas: p. ej. colapsar "rr" convertiría el apellido real
     * "Serrano" en "Serano", y no hay forma de verificar a mano, entrada por
     * entrada, qué otras colisiones no deseadas produciría en cada idioma.
     * Se documenta como límite deliberado en vez de implementarse a medias.
     */
    public function testEditDistanceEvasionIsADeliberateGap(): void
    {
        $result = $this->reviewer->validateName('Cerrda');

        $this->assertTrue(
            $result->isValid(),
            'Las variantes por distancia de edición no están cubiertas (ver el docblock de este test).'
        );
    }
}
