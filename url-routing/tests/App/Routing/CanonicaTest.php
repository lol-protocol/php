<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Support\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CanonicaTest extends TestCase
{
    /** @return array<string, array{string, ?string}> uri => canonical (null = already canonical / no redirect) */
    public static function casos(): array
    {
        return [
            'raiz' => ['/', null],
            'raiz con busqueda' => ['/?t=10&q=garc%C3%ADa', null],
            'ya canonica' => ['/6128473105/1/', null],
            'lugar canonico' => ['/mx/jal/', null],
            'sin barra final' => ['/6128473105', '/6128473105/'],
            'mayusculas' => ['/MX/Jal/', '/mx/jal/'],
            'mayusculas sin barra' => ['/MX/JAL', '/mx/jal/'],
            'barras dobles' => ['//mx//jal/', '/mx/jal/'],
            'conserva la busqueda' => ['/MX?p=2', '/mx/?p=2'],
            'archivo sin barra' => ['/robots.txt', null],
            'archivo con barra' => ['/robots.txt/', '/robots.txt'],
            'archivo en mayusculas' => ['/SITEMAP.XML', '/sitemap.xml'],
            'barra invertida' => ['/\\otro.example/', null],
            'control' => ["/mx\n/", null],
        ];
    }

    #[DataProvider('casos')]
    public function testCanonica(string $uri, ?string $esperada): void
    {
        $this->assertSame($esperada, Router::canonica($uri));
    }

    public function testNeverPointsAtAnotherHost(): void
    {
        foreach (['//otro.example', '///otro.example/', '//otro.example//x'] as $uri) {
            $destino = Router::canonica($uri);
            $this->assertNotNull($destino);
            $this->assertStringStartsNotWith('//', $destino, $uri);
        }
    }
}
