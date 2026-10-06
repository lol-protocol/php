<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Exception\InvalidEncodingException;
use PhoneDirectory\TextFolding;

class TextFoldingTest extends TestCase
{
    public function testFoldsCaseAndAccents(): void
    {
        $this->assertEquals(TextFolding::fold('garcia lopez'), TextFolding::fold('GARCÍA LÓPEZ'));
    }

    public function testRejectsInvalidUtf8BeforeLowercasingHidesIt(): void
    {
        // Latin-1 "Muñoz": mb_strtolower() would silently turn the ñ byte into '?'.
        $this->expectException(InvalidEncodingException::class);

        TextFolding::fold("Mu\xF1oz");
    }
}
