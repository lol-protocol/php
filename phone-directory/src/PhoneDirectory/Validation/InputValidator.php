<?php

namespace PhoneDirectory\Validation;

use PhoneDirectory\Exception\InvalidCountryCodeException;

trait InputValidator
{
    protected function validateCountryCode(string $countryCode): void
    {
        if (empty(trim($countryCode))) {
            throw new InvalidCountryCodeException('Country code cannot be empty');
        }

        if (!preg_match('/^[A-Za-z]{2}$/', $countryCode)) {
            throw new InvalidCountryCodeException("Country code must be 2 letters (ISO 3166-1 alpha-2): {$countryCode}");
        }
    }

    protected function validateNotEmpty(string $value, string $fieldName): void
    {
        if (empty(trim($value))) {
            throw new \InvalidArgumentException("{$fieldName} cannot be empty");
        }
    }

    protected function validateOptionalNotEmpty(?string $value, string $fieldName): void
    {
        if ($value !== null && empty(trim($value))) {
            throw new \InvalidArgumentException("{$fieldName} cannot be empty string when provided");
        }
    }
}
