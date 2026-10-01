<?php

namespace PhoneDirectory\Logger;

class StreamLogger implements LoggerInterface
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private $stream;
    private int $minLevel;
    private string $format;

    public function __construct($stream = 'php://stderr', string $minLevel = 'info', string $format = '[{level}] {message}')
    {
        if (is_string($stream)) {
            $this->stream = fopen($stream, 'a');
            if ($this->stream === false) {
                throw new \RuntimeException("Cannot open stream: {$stream}");
            }
        } else {
            $this->stream = $stream;
        }

        if (!isset(self::LEVELS[$minLevel])) {
            throw new \InvalidArgumentException("Invalid log level: {$minLevel}");
        }

        $this->minLevel = self::LEVELS[$minLevel];
        $this->format = $format;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        if (self::LEVELS[$level] < $this->minLevel) {
            return;
        }

        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $formatted = strtr($this->format, [
            '{level}' => strtoupper($level),
            '{message}' => $message,
            '{date}' => date('Y-m-d H:i:s'),
        ]) . $contextStr . PHP_EOL;

        fwrite($this->stream, $formatted);
    }

    public function __destruct()
    {
        if (is_resource($this->stream) && $this->stream !== STDERR && $this->stream !== STDOUT) {
            fclose($this->stream);
        }
    }
}
