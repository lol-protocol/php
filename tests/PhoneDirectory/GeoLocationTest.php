<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\GeoLocation;

class GeoLocationTest extends TestCase
{
    public function testCreateBasicLocation(): void
    {
        $location = new GeoLocation('US', '123 Main Street');

        $this->assertEquals('US', $location->getCountryCode());
        $this->assertEquals('123 Main Street', $location->getStreet());
        $this->assertNull($location->getCity());
        $this->assertNull($location->getZone());
    }

    public function testCreateCompleteLocation(): void
    {
        $location = new GeoLocation(
            'US',
            '123 Main Street',
            'New York',
            'New York'
        );

        $this->assertEquals('US', $location->getCountryCode());
        $this->assertEquals('123 Main Street', $location->getStreet());
        $this->assertEquals('New York', $location->getZone());
        $this->assertEquals('New York', $location->getCity());
    }

    public function testCountryCodeUppercase(): void
    {
        $location1 = new GeoLocation('us', '123 Main Street');
        $location2 = new GeoLocation('US', '123 Main Street');

        $this->assertEquals('US', $location1->getCountryCode());
        $this->assertEquals('US', $location2->getCountryCode());
    }

    public function testInvalidCountryCodeLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GeoLocation('USA', '123 Main Street');
    }

    public function testInvalidCountryCodeTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GeoLocation('U', '123 Main Street');
    }

    public function testGetFullAddress(): void
    {
        $location = new GeoLocation(
            'ES',
            'Calle Principal 456',
            'Madrid',
            'Madrid'
        );

        $address = $location->getFullAddress();
        $this->assertStringContainsString('Calle Principal 456', $address);
        $this->assertStringContainsString('Madrid', $address);
        $this->assertStringContainsString('ES', $address);
    }

    public function testGetFullAddressWithoutCity(): void
    {
        $location = new GeoLocation(
            'FR',
            '123 Rue de Rivoli',
            'Île-de-France'
        );

        $address = $location->getFullAddress();
        $this->assertStringContainsString('123 Rue de Rivoli', $address);
        $this->assertStringContainsString('Île-de-France', $address);
        $this->assertStringContainsString('FR', $address);
    }

    public function testGetFullAddressOnlyStreetAndCountry(): void
    {
        $location = new GeoLocation('DE', 'Hauptstraße 789');

        $address = $location->getFullAddress();
        $this->assertEquals('Hauptstraße 789, DE', $address);
    }

    public function testToArray(): void
    {
        $location = new GeoLocation(
            'IT',
            'Via Roma 100',
            'Lazio',
            'Rome'
        );

        $array = $location->toArray();

        $this->assertEquals('IT', $array['countryCode']);
        $this->assertEquals('Via Roma 100', $array['street']);
        $this->assertEquals('Lazio', $array['zone']);
        $this->assertEquals('Rome', $array['city']);
        $this->assertArrayHasKey('fullAddress', $array);
    }

    public function testMultipleCountryCodes(): void
    {
        $countries = ['US', 'GB', 'FR', 'DE', 'ES', 'IT', 'MX', 'AR', 'CA', 'AU'];

        foreach ($countries as $country) {
            $location = new GeoLocation($country, 'Test Street');
            $this->assertEquals($country, $location->getCountryCode());
        }
    }

    public function testSpecialCharactersInStreet(): void
    {
        $street = "123 Rue d'Arcole";
        $location = new GeoLocation('FR', $street);

        $this->assertEquals($street, $location->getStreet());
    }

    public function testSpecialCharactersInCity(): void
    {
        $city = "São Paulo";
        $location = new GeoLocation('BR', '123 Main St', null, $city);

        $this->assertEquals($city, $location->getCity());
    }
}
