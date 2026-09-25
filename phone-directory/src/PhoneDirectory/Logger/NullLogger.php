<?php

namespace PhoneDirectory\Logger;

class NullLogger implements LoggerInterface
{
    public function debug(string $message, array $context = []): void
    {
    }

    public function info(string $message, array $context = []): void
    {
    }

    public function warning(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
