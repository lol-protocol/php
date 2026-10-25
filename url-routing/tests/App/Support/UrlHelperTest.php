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

    /** @return array<string, array{list<string>, string}> */
    public static function lugaresValidos(): array
    {
        return [
            'solo pais' => [['ar'], '/ar/'],
            'pais y subdivision' => [['ar', 'c'], '/ar/c/'],
            'pais, subdivision y ciudad' => [['ar', 'b', 'lpz'], '/ar/b/lpz/'],
            'se pasa a minusculas' => [['AR', 'C'], '/ar/c/'],
        ];
    }

    #[DataProvider('lugaresValidos')]
    public function testEnlaceLugar(array $codes, string $esperada): void
    {
        $this->assertSame($esperada, (new UrlHelper(new Router()))->enlaceLugar($codes));
    }

    /** @return array<string, array{list<string>}> */
    public static function lugaresInvalidos(): array
    {
        return [
            'pais de 3 letras' => [['arg']],
            'pais de 1 letra' => [['a']],
            'subdivision de 4 letras' => [['ar', 'abcd']],
            'sin codigos' => [[]],
            'mas de 3 codigos' => [['ar', 'b', 'lpz', 'x']],
            'codigo con numeros' => [['a1']],
        ];
    }

    /**
     * PlaceMatchStrategy solo reconoce un pais de 2 letras seguido de hasta
     * dos codigos de 1 a 3 letras; un enlace que no respete eso generaria
     * una URL que la propia app no podria resolver.
     */
    #[DataProvider('lugaresInvalidos')]
    public function testEnlaceLugarRejectsMalformedPlaces(array $codes): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new UrlHelper(new Router()))->enlaceLugar($codes);
    }
}
