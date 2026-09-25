<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\PersonName;

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
        $name = new PersonName('Juan Carlos García López', 'es');

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

    public function testSpanishThreePartNameHasTwoSurnames(): void
    {
        $name = new PersonName('Juan García López', 'es');

        $this->assertEquals(['Juan'], $name->getFirstNames());
        $this->assertEquals(['García', 'López'], $name->getLastNames());
    }

    public function testSpanishTwoPartNameKeepsGivenName(): void
    {
        $name = new PersonName('Juan García', 'es');

        $this->assertEquals(['Juan'], $name->getFirstNames());
        $this->assertEquals(['García'], $name->getLastNames());
    }

    public function testSpanishParticleInGivenNameIsNotSurname(): void
    {
        $name = new PersonName('María del Carmen García López', 'es');

        $this->assertEquals(['María', 'del', 'Carmen'], $name->getFirstNames());
        $this->assertEquals(['García', 'López'], $name->getLastNames());
    }

    public function testSpanishConnectorJoinsSurnames(): void
    {
        $name = new PersonName('José Ortega y Gasset', 'es');

        $this->assertEquals(['José'], $name->getFirstNames());
        $this->assertEquals(['Ortega', 'y', 'Gasset'], $name->getLastNames());
    }

    public function testPortugueseParticlesAndConnector(): void
    {
        $name = new PersonName('João da Silva e Souza', 'pt');

        $this->assertEquals(['João'], $name->getFirstNames());
        $this->assertEquals(['da', 'Silva', 'e', 'Souza'], $name->getLastNames());
    }

    public function testChainedGermanParticles(): void
    {
        $name = new PersonName('Ludwig Mies van der Rohe', 'de');

        $this->assertEquals(['Ludwig', 'Mies'], $name->getFirstNames());
        $this->assertEquals(['van', 'der', 'Rohe'], $name->getLastNames());
    }

    public function testMiddleInitialIsNotConnectorWithoutLanguage(): void
    {
        $name = new PersonName('JOHN E SMITH');

        $this->assertEquals(['John', 'E'], $name->getFirstNames());
        $this->assertEquals(['Smith'], $name->getLastNames());
    }

    public function testUppercaseAccentedNamesAreNormalized(): void
    {
        $name = new PersonName('GARCÍA LÓPEZ, JOSÉ ÁNGEL');

        $this->assertEquals('García López, José Ángel', $name->getFormattedName());
    }

    public function testUppercaseParticlesAreLowercasedInSurname(): void
    {
        $name = new PersonName('DE LA CRUZ, MARÍA');

        $this->assertEquals('de la Cruz, María', $name->getFormattedName());
    }

    public function testSingleWordNameIsNormalized(): void
    {
        $name = new PersonName('MADONNA');

        $this->assertEquals('Madonna', $name->getFullName());
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

    public function testTitleStrippedFromCommaFormat(): void
    {
        $name = new PersonName('SMITH, Mrs. John');

        $this->assertEquals('John', $name->getFirstName());
        $this->assertEquals(['Smith'], $name->getLastNames());
        $this->assertEquals('Mrs.', $name->getTitle());
    }

    public function testTitleStrippedFromNoCommaFormat(): void
    {
        $name = new PersonName('Mrs. John Smith');

        $this->assertEquals('John', $name->getFirstName());
        $this->assertEquals(['Smith'], $name->getLastNames());
        $this->assertEquals('Mrs.', $name->getTitle());
    }

    public function testTitleIsCaseInsensitiveAndPeriodOptional(): void
    {
        $name = new PersonName('DR MARIA FERNANDEZ');

        $this->assertEquals('Maria', $name->getFirstName());
        $this->assertEquals('Dr', $name->getTitle());
    }

    public function testWidowPhraseIsCapturedAsTitle(): void
    {
        $name = new PersonName('GARCÍA, Vda. de Juan Pérez', 'es');

        $this->assertEquals('Vda. de', $name->getTitle());
        $this->assertEquals(['Juan', 'Pérez'], $name->getFirstNames());
    }

    public function testWidowPhraseVariantsAreRecognized(): void
    {
        $this->assertEquals('Viuda de', (new PersonName('Viuda de Pedro Ruiz', 'es'))->getTitle());
        $this->assertEquals('Wid. of', (new PersonName('Wid. of Robert Brown'))->getTitle());
        $this->assertEquals('Veuve de', (new PersonName('Veuve de Jean Dupont', 'fr'))->getTitle());
    }

    public function testNoTitleMeansNullTitle(): void
    {
        $name = new PersonName('SMITH, John');

        $this->assertNull($name->getTitle());
    }

    public function testMiddleInitialIsNotMistakenForFrenchTitle(): void
    {
        // Bare "M" (Monsieur) is deliberately not treated as a title: it is indistinguishable from a
        // middle initial such as this one.
        $name = new PersonName('John M Smith');

        $this->assertEquals(['John', 'M'], $name->getFirstNames());
        $this->assertNull($name->getTitle());
    }

    public function testTitleAppearsInToArray(): void
    {
        $name = new PersonName('SMITH, Mrs. John');

        $this->assertSame('Mrs.', $name->toArray()['title']);
    }
}
