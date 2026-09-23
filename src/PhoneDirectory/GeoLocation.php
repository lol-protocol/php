<?php

namespace PhoneDirectory;

class GeoLocation
{
    private string $countryCode;
    private ?string $zone;
    private ?string $city;
    private string $street;

    public function __construct(
        string $countryCode,
        string $street,
        ?string $zone = null,
        ?string $city = null
    ) {
        if (strlen($countryCode) !== 2) {
            throw new \InvalidArgumentException("Country code must be 2 letters (ISO 3166-1 alpha-2)");
        }

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
