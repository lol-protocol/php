<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\RequestValidator;
use PHPUnit\Framework\TestCase;

class RequestValidatorTest extends TestCase
{
    public function testValidateNumericIdAcceptsDigits(): void
    {
        $this->assertSame('1234', RequestValidator::validateNumericId('1234'));
        $this->assertSame('0', RequestValidator::validateNumericId(0));
    }

    public function testValidateNumericIdRejectsNonDigits(): void
    {
        $this->assertNull(RequestValidator::validateNumericId('12a4'));
        $this->assertNull(RequestValidator::validateNumericId('-1'));
        $this->assertNull(RequestValidator::validateNumericId(null));
        $this->assertNull(RequestValidator::validateNumericId(''));
    }

    public function testValidateAlphaCodeAcceptsLetters(): void
    {
        $this->assertSame('mx', RequestValidator::validateAlphaCode('mx'));
    }

    public function testValidateAlphaCodeRejectsNonLetters(): void
    {
        $this->assertNull(RequestValidator::validateAlphaCode('mx1'));
        $this->assertNull(RequestValidator::validateAlphaCode(''));
        $this->assertNull(RequestValidator::validateAlphaCode(null));
    }

    public function testValidateCodesAcceptsUpToThreeAlphaCodes(): void
    {
        $this->assertSame(['mx', 'jal'], RequestValidator::validateCodes(['mx', 'jal']));
    }

    public function testValidateCodesRejectsMoreThanThree(): void
    {
        $this->assertNull(RequestValidator::validateCodes(['a', 'b', 'c', 'd']));
    }

    public function testValidateCodesRejectsEmptyOrNonArray(): void
    {
        $this->assertNull(RequestValidator::validateCodes([]));
        $this->assertNull(RequestValidator::validateCodes('mx'));
    }

    public function testValidateCodesRejectsNonAlphaEntry(): void
    {
        $this->assertNull(RequestValidator::validateCodes(['mx', '123']));
    }

    public function testValidateStringTrimsAndTruncates(): void
    {
        $this->assertSame('hello', RequestValidator::validateString('  hello  '));
        $this->assertSame('hel', RequestValidator::validateString('hello', 3));
    }

    public function testValidateStringRejectsNonString(): void
    {
        $this->assertNull(RequestValidator::validateString(123));
        $this->assertNull(RequestValidator::validateString(null));
    }

    public function testValidateEmailNormalisesAndValidates(): void
    {
        $this->assertSame('user@example.com', RequestValidator::validateEmail(' User@Example.com '));
    }

    public function testValidateEmailRejectsInvalid(): void
    {
        $this->assertNull(RequestValidator::validateEmail('not-an-email'));
        $this->assertNull(RequestValidator::validateEmail(123));
    }

    public function testValidateUrlAcceptsValidUrl(): void
    {
        $this->assertSame('https://example.com/x', RequestValidator::validateUrl('https://example.com/x'));
    }

    public function testValidateUrlRejectsInvalid(): void
    {
        $this->assertNull(RequestValidator::validateUrl('not a url'));
        $this->assertNull(RequestValidator::validateUrl(123));
    }
}
