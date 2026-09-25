<?php

declare(strict_types=1);

namespace App\Support;

class RequestValidator
{
    public static function validateNumericId(mixed $id): string|null
    {
        if (!isset($id) || !ctype_digit((string)$id)) {
            return null;
        }
        return (string)$id;
    }

    public static function validateAlphaCode(mixed $code): string|null
    {
        if (!isset($code) || !ctype_alpha((string)$code)) {
            return null;
        }
        return (string)$code;
    }

    public static function validateCodes(mixed $codes): array|null
    {
        if (!is_array($codes) || empty($codes)) {
            return null;
        }
        if (count($codes) > 3) {
            return null;
        }
        foreach ($codes as $code) {
            if (!ctype_alpha((string)$code)) {
                return null;
            }
        }
        return $codes;
    }

    public static function validateString(mixed $value, int $maxLength = 1000): string|null
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }
        return $value;
    }

    public static function validateEmail(mixed $email): string|null
    {
        if (!is_string($email)) {
            return null;
        }
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $email;
    }

    public static function validateUrl(mixed $url): string|null
    {
        if (!is_string($url)) {
            return null;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        return $url;
    }
}
