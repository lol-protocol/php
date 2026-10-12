<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\TargetWindow;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/** La clave `targeted` de config/chat-topics/*.php: tipos de riesgo reales y marcas que se pueden encontrar. */
class TargetMarkersConfigTest extends TestCase
{
    #[DataProviderExternal(ChatTopicsConfigTest::class, 'topicFiles')]
    public function testTargetedRulesAreWellFormed(string $file): void
    {
        $config = require $file;
        $riskTypes = array_keys(require dirname(__DIR__, 2) . '/config/risk-categories.php');
        $this->addToAssertionCount(1);

        foreach ($config['targeted']['types'] ?? [] as $type) {
            $this->assertContains($type, $riskTypes, "{$type} no es un riskType");
        }
        foreach (['before', 'after'] as $side) {
            $markers = $config['targeted'][$side] ?? [];
            $this->assertSame(array_values(array_unique($markers)), $markers, "{$side}: marcas repetidas");
            foreach ($markers as $marker) {
                $this->assertNotSame([], TargetWindow::tokens($marker, $config['meta']['code']), "{$side}: «{$marker}» no tiene letras");
            }
        }
    }
}
