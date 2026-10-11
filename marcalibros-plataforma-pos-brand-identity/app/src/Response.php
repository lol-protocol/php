<?php

namespace MarcalibrosApp;

/** Respuesta HTTP: un texto en memoria o un archivo que se envía directo desde el disco. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly ?string $file = null,
    ) {
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers, $body);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self($status, ['Location' => $location]);
    }

    public static function text(string $body, int $status): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    /** @param array<string, string> $headers */
    public static function file(string $path, string $contentType, array $headers = []): self
    {
        return new self(200, ['Content-Type' => $contentType] + $headers, '', $path);
    }

    /** Agrega cabeceras que la respuesta no haya fijado ella misma. @param array<string, string> $defaults */
    public function withDefaultHeaders(array $defaults): self
    {
        return new self($this->status, $this->headers + $defaults, $this->body, $this->file);
    }

    public function send(bool $headOnly = false): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        if ($this->file !== null) {
            $size = filesize($this->file);
            if ($size !== false) {
                header('Content-Length: ' . $size);
            }
            if (!$headOnly) {
                readfile($this->file);
            }
            return;
        }
        header('Content-Length: ' . strlen($this->body));
        if (!$headOnly) {
            echo $this->body;
        }
    }
}
