<?php

declare(strict_types=1);

namespace App\Support;

class ServiceLocator
{
    private static ?ServiceLocator $instance = null;
    private Container $container;
    private string $locale;

    private function __construct(Container $container, string $locale = 'spa')
    {
        $this->container = $container;
        $this->locale = $locale;
    }

    public static function initialize(Container $container, string $locale = 'spa'): ServiceLocator
    {
        self::$instance = new self($container, $locale);
        return self::$instance;
    }

    public static function getInstance(): ServiceLocator
    {
        if (self::$instance === null) {
            throw new \RuntimeException('ServiceLocator not initialized. Call initialize() first.');
        }
        return self::$instance;
    }

    public function getRouter(): Router
    {
        return $this->container->get('router');
    }

    public function getUrlHelper(): UrlHelper
    {
        return $this->container->get('url');
    }

    public function getLogger(): Logger
    {
        return Logger::getInstance();
    }

    public function getSessionManager(): SessionManager
    {
        return SessionManager::getInstance();
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
