<?php

namespace Tests;

use DefamatoryContentReview\SpanishPhoneticFolder;
use PHPUnit\Framework\TestCase;

/** Reglas de plegado del español, una por una: qué se unifica y qué se protege. */
class SpanishPhoneticFolderTest extends TestCase
{
    public function testFoldUnifiesBAndV(): void
    {
        $this->assertSame(SpanishPhoneticFolder::fold('vaca'), SpanishPhoneticFolder::fold('baca'));
    }

    public function testFoldUnifiesSZAndSoftC(): void
    {
        $this->assertSame(SpanishPhoneticFolder::fold('zapato'), SpanishPhoneticFolder::fold('sapato'));
        $this->assertSame(SpanishPhoneticFolder::fold('cielo'), SpanishPhoneticFolder::fold('sielo'));
    }

    public function testFoldUnifiesLlAndY(): void
    {
        $this->assertSame(SpanishPhoneticFolder::fold('calle'), SpanishPhoneticFolder::fold('caye'));
    }

    public function testFoldDropsSilentH(): void
    {
        $this->assertSame(SpanishPhoneticFolder::fold('hola'), SpanishPhoneticFolder::fold('ola'));
    }

    public function testFoldPreservesChDigraph(): void
    {
        $this->assertNotSame(SpanishPhoneticFolder::fold('chino'), SpanishPhoneticFolder::fold('cino'));
    }

    public function testFoldUnifiesJAndSoftG(): void
    {
        $this->assertSame(SpanishPhoneticFolder::fold('cojes'), SpanishPhoneticFolder::fold('coges'));
    }

    public function testFoldStripsSpacesForFusion(): void
    {
        $this->assertSame(SpanishPhoneticFolder::fold('el gato'), SpanishPhoneticFolder::fold('elgato'));
    }
}
