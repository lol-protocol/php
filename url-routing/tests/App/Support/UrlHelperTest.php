<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Router;
use App\Support\UrlHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlHelperTest extends TestCase
{
    /** @return array<string, array{int, string, int, string}> */
    public static function busquedas(): array
    {
        return [
            'todo' => [10, '', 1, '/b/10/'],
            'todo, pagina' => [10, '', 3, '/b/10/3/'],
            'texto' => [10, 'garcia', 1, '/b/10/garcia/'],
            'texto, pagina' => [10, 'garcia', 2, '/b/10/garcia/2/'],
            'mayusculas y espacios' => [10, '  María   GARCÍA ', 1, '/b/10/mar%c3%ada%20garc%c3%ada/'],
            'punto' => [8, 'p.e.', 1, '/b/8/p%2ee%2e/'],
            'solo digitos lleva su pagina' => [8, '2026', 1, '/b/8/2026/1/'],
            'porcentaje' => [8, '%', 1, '/b/8/%25/'],
        ];
    }

    #[DataProvider('busquedas')]
    public function testBusqueda(int $digitos, string $texto, int $pagina, string $esperada): void
    {
        $this->assertSame($esperada, (new UrlHelper(new Router()))->busqueda($digitos, $texto, $pagina));
    }

    /** Router::canonica() must leave it alone, or the two would bounce a request between them. */
    #[DataProvider('busquedas')]
    public function testBusquedaIsAlreadyCanonical(int $digitos, string $texto, int $pagina): void
    {
        $this->assertNull(Router::canonica((new UrlHelper(new Router()))->busqueda($digitos, $texto, $pagina)));
    }
}
