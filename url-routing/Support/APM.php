<?php

declare(strict_types=1);

namespace App\Support;

class APM
{
    private static ?APM $instance = null;
    private bool $enabled = false;
    private array $config = [];
    private array $transactions = [];
    private array $errors = [];

    private function __construct()
    {
        $this->enabled = getenv('APM_ENABLED') === 'true';
        $this->config = [
            'service_name' => getenv('APM_SERVICE_NAME') ?: 'php-app',
            'environment' => getenv('APM_ENVIRONMENT') ?: 'development',
            'sample_rate' => (float)getenv('APM_SAMPLE_RATE') ?: 1.0,
            'endpoint' => getenv('APM_ENDPOINT') ?: '',
            'api_key' => getenv('APM_API_KEY') ?: '',
        ];

        if ($this->enabled) {
            register_shutdown_function([$this, 'flush']);
        }
    }

    public static function getInstance(): APM
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function startTransaction(string $name): void
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return;
        }

        $this->transactions[$name] = [
            'start' => microtime(true),
            'name' => $name,
            'duration' => 0,
            'status' => 'ongoing',
            'metadata' => [],
        ];
    }

    public function endTransaction(string $name, string $status = 'success'): void
    {
        if (!$this->enabled || !isset($this->transactions[$name])) {
            return;
        }

        $this->transactions[$name]['duration'] = (microtime(true) - $this->transactions[$name]['start']) * 1000;
        $this->transactions[$name]['status'] = $status;
    }

    public function addMetadata(string $transaction, string $key, mixed $value): void
    {
        if (!$this->enabled || !isset($this->transactions[$transaction])) {
            return;
        }

        $this->transactions[$transaction]['metadata'][$key] = $value;
    }

    public function recordError(\Throwable $exception, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->errors[] = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'timestamp' => microtime(true),
        ];

        ServiceLocator::getInstance()->getLogger()->error('APM Error recorded', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
        ]);
    }

    public function recordCustomMetric(string $name, float $value, array $tags = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
        ];

        if (count($this->transactions) > 0) {
            $lastTransaction = array_key_last($this->transactions);
            if ($lastTransaction !== null) {
                $this->transactions[$lastTransaction]['metadata']['metrics'][$name] = $value;
            }
        }
    }

    public function flush(): void
    {
        if (!$this->enabled || (empty($this->transactions) && empty($this->errors))) {
            return;
        }

        $payload = [
            'service' => $this->config['service_name'],
            'environment' => $this->config['environment'],
            'transactions' => array_values($this->transactions),
            'errors' => $this->errors,
            'timestamp' => microtime(true),
        ];

        $this->send($payload);
        $this->transactions = [];
        $this->errors = [];
    }

    private function send(array $payload): void
    {
        if (empty($this->config['endpoint'])) {
            return;
        }

        $url = parse_url($this->config['endpoint']);
        if ($url === false || !isset($url['host'])) {
            return;
        }

        $isSecure = ($url['scheme'] ?? 'http') === 'https';
        $port = $url['port'] ?? ($isSecure ? 443 : 80);
        $path = ($url['path'] ?? '/') . (isset($url['query']) ? '?' . $url['query'] : '');
        $body = (string)json_encode($payload);

        $socket = @fsockopen(($isSecure ? 'ssl://' : '') . $url['host'], (int)$port, $errno, $errstr, 2);
        if ($socket === false) {
            ServiceLocator::getInstance()->getLogger()->warning('APM send failed', [
                'endpoint' => $this->config['endpoint'],
                'error' => $errstr,
            ]);
            return;
        }

        // Fire-and-forget: write the request and close without waiting for a
        // response, so exporting metrics never adds latency to the request
        // that triggered this flush.
        $request = "POST {$path} HTTP/1.1\r\n" .
            "Host: {$url['host']}\r\n" .
            "Content-Type: application/json\r\n" .
            "Authorization: Bearer {$this->config['api_key']}\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "Connection: Close\r\n\r\n" .
            $body;

        fwrite($socket, $request);
        fclose($socket);
    }

    private function shouldSample(): bool
    {
        return mt_rand(0, 1000) / 1000 <= $this->config['sample_rate'];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getConfig(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}
