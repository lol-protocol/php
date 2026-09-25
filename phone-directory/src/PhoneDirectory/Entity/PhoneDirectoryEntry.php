<?php

namespace PhoneDirectory\Entity;

class PhoneDirectoryEntry
{
    private int $id;
    private PersonName $personName;
    private GeoLocation $geoLocation;
    private ?string $phoneNumber;
    private ?\DateTime $recordDate;
    private ?string $sourceDirectoryId;
    private string $rawName;
    private ?int $sourceLine;

    /**
     * Create a phone directory entry for a natural person
     *
     * @param string $fullName Person's full name (supports "LastName, FirstName" format)
     * @param string $countryCode Two-letter ISO 3166-1 alpha-2 country code
     * @param string $street Street address
     * @param string|null $phoneNumber Phone number
     * @param string|null $zone Geographic zone/region
     * @param string|null $city City name
     * @param int|null $id Database record ID (for existing records)
     * @param \DateTime|null $recordDate Date the entry was recorded (defaults to now)
     * @param string|null $sourceDirectoryId Source directory identifier
     * @param string|null $language Language code (es, en, fr, pt, de, it) for name parsing
     * @param int|null $sourceLine Line number in source file
     * @throws \InvalidArgumentException If fullName or street are empty
     */
    public function __construct(
        string $fullName,
        string $countryCode,
        string $street,
        ?string $phoneNumber = null,
        ?string $zone = null,
        ?string $city = null,
        ?int $id = null,
        ?\DateTime $recordDate = null,
        ?string $sourceDirectoryId = null,
        ?string $language = null,
        ?int $sourceLine = null
    ) {
        if (empty(trim($fullName))) {
            throw new \InvalidArgumentException('Full name cannot be empty');
        }

        if (empty(trim($street))) {
            throw new \InvalidArgumentException('Street cannot be empty');
        }

        $this->id = $id ?? 0;
        $this->rawName = trim($fullName);
        $this->sourceLine = $sourceLine;
        $this->personName = new PersonName($fullName, $language);
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

    public function getRawName(): string
    {
        return $this->rawName;
    }

    public function getLanguage(): ?string
    {
        return $this->personName->getLanguage();
    }

    public function getFormattedName(): string
    {
        return $this->personName->getFormattedName();
    }

    public function getTitle(): ?string
    {
        return $this->personName->getTitle();
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

    public function getSourceLine(): ?int
    {
        return $this->sourceLine;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rawName' => $this->rawName,
            'language' => $this->getLanguage(),
            'person' => $this->personName->toArray(),
            'location' => $this->geoLocation->toArray(),
            'phoneNumber' => $this->phoneNumber,
            'recordDate' => $this->recordDate->format('Y-m-d H:i:s'),
            'sourceDirectoryId' => $this->sourceDirectoryId,
            'sourceLine' => $this->sourceLine,
        ];
    }
}
