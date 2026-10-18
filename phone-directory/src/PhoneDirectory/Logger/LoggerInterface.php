<?php

namespace PhoneDirectory\Logger;

interface LoggerInterface
{
    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void;
    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;
    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;
    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;
}
