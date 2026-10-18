<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\ArchivoMatchStrategy;
use PHPUnit\Framework\TestCase;

class ArchivoMatchStrategyTest extends TestCase
{
    private const CONFIG = [
        'archivos' => [
            'robots.txt' => ['controller' => 'X\Archivos', 'method' => 'robots'],
            'sitemap' => ['controller' => 'X\Archivos', 'method' => 'parte'],
        ],
    ];

    private function resolver(array $segmentos): ?array
    {
        $estrategia = new ArchivoMatchStrategy();
        if (!$estrategia->matches($segmentos, self::CONFIG)) {
            return null;
        }

        return $estrategia->resolve($segmentos, self::CONFIG);
    }

    public function testExactFile(): void
    {
        $this->assertSame(
            ['controller' => 'X\Archivos', 'method' => 'robots', 'params' => ['ruta' => []]],
            $this->resolver(['robots.txt'])
        );
    }

    public function testFolderPassesTheRestOfThePath(): void
    {
        $this->assertSame(['ruta' => ['10', '1.xml']], $this->resolver(['sitemap', '10', '1.xml'])['params']);
    }

    public function testRejectsWhatIsNotAKnownFile(): void
    {
        $this->assertNull($this->resolver(['otro.txt']));
        $this->assertNull($this->resolver(['robots.txt', 'x.txt']));
        $this->assertNull($this->resolver(['sitemap']), 'a folder alone is not a file');
        $this->assertNull($this->resolver(['sitemap', '10', '1']), 'no extension');
        $this->assertNull($this->resolver(['sitemap', 'a', 'b', 'c.xml']), 'too deep');
    }
}
