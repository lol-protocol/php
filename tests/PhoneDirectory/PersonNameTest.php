<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PersonName;

class PersonNameTest extends TestCase
{
    public function testParseSimpleName(): void
    {
        $name = new PersonName('John Smith');

        $this->assertEquals('John', $name->getFirstName());
        $this->assertEquals(['Smith'], $name->getLastNames());
        $this->assertEquals('John Smith', $name->getFullName());
    }

    public function testParseThreePartName(): void
    {
        $name = new PersonName('John Michael Smith');

        $this->assertEquals('John', $name->getFirstName());
        $this->assertEquals(['Michael'], $name->getMiddleNames());
        $this->assertEquals(['Smith'], $name->getLastNames());
    }

    public function testParseCompoundLastName(): void
    {
        $name = new PersonName('Juan Carlos García López');

        $this->assertEquals('Juan', $name->getFirstName());
        $this->assertEquals(['García', 'López'], $name->getLastNames());
    }

    public function testParseSpanishParticle(): void
    {
        $name = new PersonName('María de la Cruz');

        $this->assertEquals('María', $name->getFirstName());
        $this->assertContains('de', $name->getLastNames());
        $this->assertContains('la', $name->getLastNames());
        $this->assertContains('Cruz', $name->getLastNames());
    }

    public function testParseItalianParticle(): void
    {
        $name = new PersonName('Giancarlo di Rossi');

        $this->assertEquals('Giancarlo', $name->getFirstName());
        $this->assertContains('di', $name->getLastNames());
        $this->assertContains('Rossi', $name->getLastNames());
    }

    public function testParseGermanParticle(): void
    {
        $name = new PersonName('Hans von Neumann');

        $this->assertEquals('Hans', $name->getFirstName());
        $this->assertContains('von', $name->getLastNames());
        $this->assertContains('Neumann', $name->getLastNames());
    }

    public function testParseFormattedName(): void
    {
        $name = new PersonName('John Michael Smith');

        $this->assertEquals('Smith, John Michael', $name->getFormattedName());
    }

    public function testParseMultipleMiddleNames(): void
    {
        $name = new PersonName('Robert Henry Thomas Brown');

        $this->assertEquals('Robert', $name->getFirstName());
        $this->assertEquals(['Henry', 'Thomas'], $name->getMiddleNames());
        $this->assertEquals(['Brown'], $name->getLastNames());
    }

    public function testToArray(): void
    {
        $name = new PersonName('John David Smith');
        $array = $name->toArray();

        $this->assertArrayHasKey('fullName', $array);
        $this->assertArrayHasKey('firstName', $array);
        $this->assertArrayHasKey('firstNames', $array);
        $this->assertArrayHasKey('middleNames', $array);
        $this->assertArrayHasKey('lastNames', $array);
        $this->assertArrayHasKey('primaryLastName', $array);
        $this->assertArrayHasKey('formattedName', $array);
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PersonName('');
    }

    public function testWhitespaceOnlyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PersonName('   ');
    }

    public function testSingleWordName(): void
    {
        $name = new PersonName('Madonna');

        $this->assertEquals('Madonna', $name->getFirstName());
        $this->assertEmpty($name->getLastNames());
    }

    public function testGetPrimaryLastName(): void
    {
        $name = new PersonName('García López, Juan');

        $this->assertEquals('García', $name->getPrimaryLastName());
    }

    public function testCaseSensitivity(): void
    {
        $name1 = new PersonName('JOHN SMITH');
        $name2 = new PersonName('john smith');
        $name3 = new PersonName('John Smith');

        $this->assertEquals($name1->getFullName(), $name2->getFullName());
        $this->assertEquals($name2->getFullName(), $name3->getFullName());
    }

    public function testMultipleSpaces(): void
    {
        $name = new PersonName('John    Michael    Smith');

        $this->assertEquals('John Michael Smith', $name->getFullName());
    }
}
