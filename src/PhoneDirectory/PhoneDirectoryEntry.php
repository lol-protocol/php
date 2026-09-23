<?php

namespace PhoneDirectory;

class PhoneDirectoryEntry
{
    private int $id;
    private string $fullName;
    private string $street;
    private ?string $phoneNumber;
    private ?\DateTime $recordDate;

    public function __construct(
        string $fullName,
        string $street,
        ?string $phoneNumber = null,
        ?int $id = null,
        ?\DateTime $recordDate = null
    ) {
        $this->id = $id ?? 0;
        $this->fullName = $fullName;
        $this->street = $street;
        $this->phoneNumber = $phoneNumber;
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

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getRecordDate(): \DateTime
    {
        return $this->recordDate;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName,
            'street' => $this->street,
            'phoneNumber' => $this->phoneNumber,
            'recordDate' => $this->recordDate->format('Y-m-d H:i:s'),
        ];
    }
}
