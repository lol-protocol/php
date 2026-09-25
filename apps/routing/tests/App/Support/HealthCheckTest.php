<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\HealthCheck;
use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    public function testMatchesHealthPath(): void
    {
        $this->assertTrue(HealthCheck::isHealthCheckRequest('/health'));
        $this->assertTrue(HealthCheck::isHealthCheckRequest('/health/'));
        $this->assertTrue(HealthCheck::isHealthCheckRequest('/health?verbose=1'));
    }

    public function testDoesNotMatchOtherPaths(): void
    {
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/healthcheck'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/1234567890'));
        $this->assertFalse(HealthCheck::isHealthCheckRequest('/health/extra'));
    }
}
