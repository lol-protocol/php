<?php

declare(strict_types=1);

namespace App\Support;

class Container
{
    private static $instance;
    private $bindings = [];
    private $singletons = [];
    private $resolved = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $key, callable $factory): self
    {
        $this->bindings[$key] = $factory;
        return $this;
    }

    public function singleton(string $key, callable $factory): self
    {
        $this->bind($key, $factory);
        $this->singletons[$key] = true;
        return $this;
    }

    public function get(string $key): mixed
    {
        if (!isset($this->bindings[$key])) {
            throw new \Exception("Binding not found: {$key}");
        }

        if (isset($this->singletons[$key])) {
            if (!array_key_exists($key, $this->resolved)) {
                $this->resolved[$key] = $this->bindings[$key]($this);
            }
            return $this->resolved[$key];
        }

        return $this->bindings[$key]($this);
    }
}
