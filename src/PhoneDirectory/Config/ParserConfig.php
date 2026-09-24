<?php

namespace PhoneDirectory\Config;

class ParserConfig
{
    private int $maxSurnameLength;
    private int $minCountryCodeLength;
    private int $maxCountryCodeLength;

    public function __construct(
        int $maxSurnameLength = 1000,
        int $minCountryCodeLength = 2,
        int $maxCountryCodeLength = 2
    ) {
        if ($maxSurnameLength <= 0) {
            throw new \InvalidArgumentException('maxSurnameLength must be greater than 0');
        }

        $this->maxSurnameLength = $maxSurnameLength;
        $this->minCountryCodeLength = $minCountryCodeLength;
        $this->maxCountryCodeLength = $maxCountryCodeLength;
    }

    public function getMaxSurnameLength(): int
    {
        return $this->maxSurnameLength;
    }

    public function getMinCountryCodeLength(): int
    {
        return $this->minCountryCodeLength;
    }

    public function getMaxCountryCodeLength(): int
    {
        return $this->maxCountryCodeLength;
    }

    public static function default(): self
    {
        return new self();
    }
}
