<?php

namespace PhoneDirectory;

class PhoneDirectoryEntry
{
    private int $id;
    private PersonName $personName;
    private GeoLocation $geoLocation;
    private ?string $phoneNumber;
    private ?\DateTime $recordDate;
    private ?string $sourceDirectoryId;

    public function __construct(
        string $fullName,
        string $countryCode,
        string $street,
        ?string $phoneNumber = null,
        ?string $zone = null,
        ?string $city = null,
        ?int $id = null,
        ?\DateTime $recordDate = null,
        ?string $sourceDirectoryId = null
    ) {
        $this->id = $id ?? 0;
        $this->personName = new PersonName($fullName);
        $this->geoLocation = new GeoLocation($countryCode, $street, $zone, $city);
        $this->phoneNumber = $phoneNumber;
        $this->recordDate = $recordDate ?? new \DateTime();
        $this->sourceDirectoryId = $sourceDirectoryId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPersonName(): PersonName
    {
        return $this->personName;
    }

    public function getFullName(): string
    {
        return $this->personName->getFullName();
    }

    public function getFirstName(): string
    {
        return $this->personName->getFirstName();
    }

    public function getLastNames(): array
    {
        return $this->personName->getLastNames();
    }

    public function getPrimaryLastName(): string
    {
        return $this->personName->getPrimaryLastName();
    }

    public function getGeoLocation(): GeoLocation
    {
        return $this->geoLocation;
    }

    public function getCountryCode(): string
    {
        return $this->geoLocation->getCountryCode();
    }

    public function getZone(): ?string
    {
        return $this->geoLocation->getZone();
    }

    public function getCity(): ?string
    {
        return $this->geoLocation->getCity();
    }

    public function getStreet(): string
    {
        return $this->geoLocation->getStreet();
    }

    public function getFullAddress(): string
    {
        return $this->geoLocation->getFullAddress();
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getRecordDate(): \DateTime
    {
        return $this->recordDate;
    }

    public function getSourceDirectoryId(): ?string
    {
        return $this->sourceDirectoryId;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'person' => $this->personName->toArray(),
            'location' => $this->geoLocation->toArray(),
            'phoneNumber' => $this->phoneNumber,
            'recordDate' => $this->recordDate->format('Y-m-d H:i:s'),
            'sourceDirectoryId' => $this->sourceDirectoryId,
        ];
    }
}
