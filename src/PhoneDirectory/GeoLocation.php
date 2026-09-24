<?php

namespace PhoneDirectory;

use PhoneDirectory\Validation\InputValidator;

class GeoLocation
{
    use InputValidator;

    private string $countryCode;
    private ?string $zone;
    private ?string $city;
    private string $street;

    /**
     * Create geographic location information
     *
     * @param string $countryCode Two-letter ISO 3166-1 alpha-2 country code
     * @param string $street Street address (required, cannot be empty)
     * @param string|null $zone Geographic zone/region/state
     * @param string|null $city City name
     * @throws \InvalidArgumentException If country code is invalid or street is empty
     */
    public function __construct(
        string $countryCode,
        string $street,
        ?string $zone = null,
        ?string $city = null
    ) {
        $this->validateCountryCode($countryCode);
        $this->validateNotEmpty($street, 'Street');

        $this->countryCode = strtoupper($countryCode);
        $this->zone = $zone;
        $this->city = $city;
        $this->street = $street;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getFullAddress(): string
    {
        $parts = [$this->street];

        if ($this->city) {
            $parts[] = $this->city;
        }

        if ($this->zone) {
            $parts[] = $this->zone;
        }

        $parts[] = $this->countryCode;

        return implode(', ', $parts);
    }

    public function toArray(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'zone' => $this->zone,
            'city' => $this->city,
            'street' => $this->street,
            'fullAddress' => $this->getFullAddress(),
        ];
    }
}
