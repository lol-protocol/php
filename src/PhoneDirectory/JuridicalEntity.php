<?php

namespace PhoneDirectory;

class JuridicalEntity
{
    private int $id;
    private string $businessName;
    private ?string $legalName;
    private string $street;
    private ?string $phoneNumber;
    private ?string $businessType;
    private ?\DateTime $recordDate;

    public function __construct(
        string $businessName,
        string $street,
        ?string $legalName = null,
        ?string $businessType = null,
        ?string $phoneNumber = null,
        ?int $id = null,
        ?\DateTime $recordDate = null
    ) {
        $this->id = $id ?? 0;
        $this->businessName = $businessName;
        $this->legalName = $legalName;
        $this->street = $street;
        $this->phoneNumber = $phoneNumber;
        $this->businessType = $businessType;
        $this->recordDate = $recordDate ?? new \DateTime();
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

    public function getBusinessName(): string
    {
        return $this->businessName;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getBusinessType(): ?string
    {
        return $this->businessType;
    }

    public function getRecordDate(): \DateTime
    {
        return $this->recordDate;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessName' => $this->businessName,
            'legalName' => $this->legalName,
            'street' => $this->street,
            'phoneNumber' => $this->phoneNumber,
            'businessType' => $this->businessType,
            'recordDate' => $this->recordDate->format('Y-m-d H:i:s'),
        ];
    }
}
