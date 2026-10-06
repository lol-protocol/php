<?php

declare(strict_types=1);

namespace App\Support;

class Logger
{
    private static ?Logger $instance = null;
    private string $logFile;
    private string $logLevel;
    private array $batch = [];
    private int $batchSize = 100;
    private float $lastFlush = 0;
    private float $flushInterval = 5.0;

    private function __construct()
    {
        $this->logFile = getenv('LOG_FILE') ?: sys_get_temp_dir() . '/app.log';
        $this->logLevel = getenv('LOG_LEVEL') ?: 'INFO';
        $this->lastFlush = microtime(true);
        register_shutdown_function([$this, 'flush']);
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

        $this->batch[] = $logMessage;

        if (count($this->batch) >= $this->batchSize) {
            $this->flush();
        } elseif (microtime(true) - $this->lastFlush >= $this->flushInterval) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->batch)) {
            return;
        }

        $content = implode('', $this->batch);
        error_log($content, 3, $this->logFile);

        $this->batch = [];
        $this->lastFlush = microtime(true);
    }

    public function setBatchSize(int $size): void
    {
        $this->batchSize = max(1, $size);
    }

    public function setFlushInterval(float $seconds): void
    {
        $this->flushInterval = max(0.1, $seconds);
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
