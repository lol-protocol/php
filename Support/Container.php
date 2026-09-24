<?php

namespace App\Support;

class Container
{
    private static $instance;
    private $bindings = [];
    private $singletons = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind($key, callable $factory)
    {
        $this->bindings[$key] = $factory;
        return $this;
    }

    public function singleton($key, callable $factory)
    {
        $this->bind($key, $factory);
        $this->singletons[$key] = true;
        return $this;
    }

    public function get($key)
    {
        if (!isset($this->bindings[$key])) {
            throw new \Exception("Binding not found: {$key}");
        }

        if (isset($this->singletons[$key])) {
            if (!isset($this->_resolved[$key])) {
                $this->_resolved[$key] = $this->bindings[$key]($this);
            }
            return $this->_resolved[$key];
        }

        return $this->bindings[$key]($this);
    }

    private $_resolved = [];
}
