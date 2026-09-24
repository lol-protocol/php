<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\MultiLanguagePhoneDirectoryParser;

class MultiLanguagePhoneDirectoryParserTest extends TestCase
{
    private MultiLanguagePhoneDirectoryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MultiLanguagePhoneDirectoryParser();
    }

    public function testSpanishNaturalPersonGetsTwoSurnames(): void
    {
        $content = "JOSÉ GARCÍA LÓPEZ\nCalle Mayor 12\n555-123-4567\n\nANA MARÍA PÉREZ RUIZ\nAvenida del Sol 5\n555-222-3333";

        $entries = $this->parser->parseContent($content);

        $this->assertEquals('es', $this->parser->getDetectedLanguage());
        $this->assertCount(0, $this->parser->getErrors());
        $this->assertCount(2, $entries);
        $this->assertEquals('natural', $entries[0]['type']);
        $this->assertEquals(['García', 'López'], $entries[0]['entity']->getLastNames());
        $this->assertEquals('Pérez Ruiz, Ana María', $entries[1]['entity']->getFormattedName());
    }

    public function testEnglishAddressContainingAmbiguousWordIsNotMisdetected(): void
    {
        // "Plaza" is a genuine Spanish street marker, but also an ordinary English street name/word;
        // an otherwise-English directory shouldn't be misdetected as Spanish just because of it.
        $content = "John Michael Smith\n40 West Plaza\n555-111-2222\n\nRobert Allen Carter\n12 East Plaza\n555-333-4444";

        $entries = $this->parser->parseContent($content);

        $this->assertEquals('en', $this->parser->getDetectedLanguage());
        $this->assertEquals(['Smith'], $entries[0]['entity']->getLastNames());
        $this->assertEquals('Smith, John Michael', $entries[0]['entity']->getFormattedName());
    }

    public function testNoRecognizedMarkersDefaultsToEnglish(): void
    {
        $content = "Robert Miller\n123 Somewhere\n\nJohn Carter\n40 Elsewhere";

        $this->parser->parseContent($content);

        $this->assertEquals('en', $this->parser->getDetectedLanguage());
    }

    public function testGenuineSpanishContentIsStillDetectedWithOnlyOneMarker(): void
    {
        $this->parser->parseContent("Juan García López\nCalle Mayor 12");

        $this->assertEquals('es', $this->parser->getDetectedLanguage());
    }

    public function testGermanCompoundStreetIsDetected(): void
    {
        $entries = $this->parser->parseContent("Hans Müller\nHauptstraße 5\n\nKarl Weber\nLindenallee 9", 'de');

        $this->assertCount(2, $entries);
        $this->assertEquals('Hauptstraße 5', $entries[0]['entity']->getStreet());
        $this->assertEquals('Lindenallee 9', $entries[1]['entity']->getStreet());
    }

    public function testSurnameContainingStreetAbbreviationIsNotStreet(): void
    {
        $entries = $this->parser->parseContent("Robert Johnston\n12 Oak Avenue\n555-111-2222", 'en');

        $this->assertCount(1, $entries);
        $this->assertEquals('12 Oak Avenue', $entries[0]['entity']->getStreet());
        $this->assertEquals(['Johnston'], $entries[0]['entity']->getLastNames());
    }

    public function testNameContainingEntityMarkerIsNotJuridical(): void
    {
        $entries = $this->parser->parseContent("Sara Espinosa\nCalle Luna 3\n555-444-5555", 'es');

        $this->assertCount(1, $entries);
        $this->assertEquals('natural', $entries[0]['type']);
    }

    public function testBusinessIsJuridical(): void
    {
        $entries = $this->parser->parseContent("Farmacia Central\nCalle Luna 3\n555-444-5555", 'es');

        $this->assertCount(1, $entries);
        $this->assertEquals('juridical', $entries[0]['type']);
    }

    public function testCatalogDirectorySetsCountrySourceAndLine(): void
    {
        $parser = MultiLanguagePhoneDirectoryParser::forCatalogDirectory('es_1975_national');

        $entries = $parser->parseContent("\nJuan García López\nCalle Mayor 12");

        $entry = $entries[0]['entity'];
        $this->assertEquals('ES', $entry->getCountryCode());
        $this->assertEquals('es_1975_national', $entry->getSourceDirectoryId());
        $this->assertEquals('es', $entry->getLanguage());
        $this->assertSame(2, $entry->getSourceLine());
    }

    public function testBusinessGetsCountrySourceAndLine(): void
    {
        $parser = MultiLanguagePhoneDirectoryParser::forCatalogDirectory('mx_1960_mexico');

        $entries = $parser->parseContent("Juan Pérez Soto\nCalle Hidalgo 4\n\nFarmacia Guadalajara\nAvenida Juárez 10", 'es');

        $business = $entries[1]['entity'];
        $this->assertEquals('juridical', $entries[1]['type']);
        $this->assertEquals('MX', $business->getCountryCode());
        $this->assertEquals('mx_1960_mexico', $business->getSourceDirectoryId());
        $this->assertSame(4, $business->getSourceLine());
    }

    public function testStreetFirstLayoutIsNotReadAsTheName(): void
    {
        $entries = $this->parser->parseContent("Calle Mayor 12\nJuan García López\n555-123-4567", 'es');

        $this->assertCount(1, $entries);
        $entity = $entries[0]['entity'];
        $this->assertEquals('García López, Juan', $entity->getFormattedName());
        $this->assertEquals('Calle Mayor 12', $entity->getStreet());
    }

    public function testPhoneOnlyLineIsNotAlsoReadAsStreet(): void
    {
        $entries = $this->parser->parseContent("JONES, Ann\n555 234 5678\n45 Pine Street", 'en');

        $this->assertCount(1, $entries);
        $entity = $entries[0]['entity'];
        $this->assertEquals('45 Pine Street', $entity->getStreet());
        $this->assertEquals('555 234 5678', $entity->getPhoneNumber());
    }

    public function testSingleLineEntryCommaDelimited(): void
    {
        $entries = $this->parser->parseContent('García, Juan, Calle Mayor 12, 555-123-4567', 'es');

        $this->assertCount(1, $entries);
        $entity = $entries[0]['entity'];
        $this->assertEquals('García, Juan', $entity->getFormattedName());
        $this->assertEquals('Calle Mayor 12', $entity->getStreet());
        $this->assertEquals('555-123-4567', $entity->getPhoneNumber());
    }

    public function testSingleLineEntryWithTwoSpanishSurnamesAndNoDelimiter(): void
    {
        $entries = $this->parser->parseContent('GARCIA LOPEZ Juan 12 Calle Mayor 555-123-4567', 'es');

        $this->assertCount(1, $entries);
        $entity = $entries[0]['entity'];
        $this->assertEquals(['Garcia', 'Lopez'], $entity->getLastNames());
        $this->assertEquals('Juan', $entity->getFirstName());
        $this->assertEquals('12 Calle Mayor', $entity->getStreet());
    }

    public function testGermanCompoundStreetInSingleLineEntry(): void
    {
        $entries = $this->parser->parseContent('Hans Müller, Hauptstraße 5, 555-111-2222', 'de');

        $this->assertCount(1, $entries);
        $entity = $entries[0]['entity'];
        $this->assertEquals('Hauptstraße 5', $entity->getStreet());
    }
}
