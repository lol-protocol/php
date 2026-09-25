<?php

namespace Tests;

use DefamatoryContentReview\PhoneticFolder;
use PHPUnit\Framework\TestCase;

/** Reglas de plegado del español, una por una: qué se unifica y qué se protege. */
class PhoneticFolderRulesTest extends TestCase
{
    public function testFoldUnifiesBAndV(): void
    {
        $this->assertSame(PhoneticFolder::fold('vaca'), PhoneticFolder::fold('baca'));
    }

    public function testFoldUnifiesSZAndSoftC(): void
    {
        $this->assertSame(PhoneticFolder::fold('zapato'), PhoneticFolder::fold('sapato'));
        $this->assertSame(PhoneticFolder::fold('cielo'), PhoneticFolder::fold('sielo'));
    }

    public function testFoldUnifiesLlAndY(): void
    {
        $this->assertSame(PhoneticFolder::fold('calle'), PhoneticFolder::fold('caye'));
    }

    public function testFoldDropsSilentH(): void
    {
        $this->assertSame(PhoneticFolder::fold('hola'), PhoneticFolder::fold('ola'));
    }

    public function testFoldPreservesChDigraph(): void
    {
        $this->assertNotSame(PhoneticFolder::fold('chino'), PhoneticFolder::fold('cino'));
    }

    public function testFoldUnifiesJAndSoftG(): void
    {
        $this->assertSame(PhoneticFolder::fold('cojes'), PhoneticFolder::fold('coges'));
    }

    public function testFoldStripsSpacesForFusion(): void
    {
        $this->assertSame(PhoneticFolder::fold('el gato'), PhoneticFolder::fold('elgato'));
    }
}
