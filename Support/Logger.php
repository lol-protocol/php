<?php

declare(strict_types=1);

namespace App\Support;

class Logger
{
    private static ?Logger $instance = null;
    private string $logFile;
    private string $logLevel;

    private function __construct()
    {
        $this->logFile = getenv('LOG_FILE') ?: sys_get_temp_dir() . '/app.log';
        $this->logLevel = getenv('LOG_LEVEL') ?: 'INFO';
    }

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        error_log($logMessage, 3, $this->logFile);
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
