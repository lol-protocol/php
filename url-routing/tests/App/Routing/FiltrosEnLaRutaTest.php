<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\ByLengthMatchStrategy;
use App\Routing\ConfigValidator;
use App\Support\Router;
use App\Support\UrlHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * /1001/48213/ is the category 1001 filtered by the atributo 48213: a second segment of 2 or more digits is a
 * filter, and the entity it names comes from its width, like any other id. One digit stays an action.
 */
class FiltrosEnLaRutaTest extends TestCase
{
    private ByLengthMatchStrategy $estrategia;
    /** @var array<string, mixed> */
    private array $config;

    protected function setUp(): void
    {
        $this->estrategia = new ByLengthMatchStrategy();
        $this->config = require __DIR__ . '/../../../config/routes/pos.php';
    }

    /** @return array<string, array{list<string>, array<string, string>|null}> */
    public static function rutas(): array
    {
        return [
            'un filtro' => [['1001', '48213'], ['atributo' => '48213']],
            'otro tipo de filtro' => [['1001', '520002'], ['etiqueta' => '520002']],
            'los dos' => [['1001', '48213', '520002'], ['atributo' => '48213', 'etiqueta' => '520002']],
            'en el otro orden se reconoce igual (el controlador lo redirige)' => [['1001', '520002', '48213'], ['etiqueta' => '520002', 'atributo' => '48213']],
            'el mismo tipo dos veces' => [['1001', '48213', '48214'], null],
            'un tipo que no filtra una categoría (producto)' => [['1001', '81372047'], null],
            'un tipo que no filtra una categoría (colección)' => [['1001', '1048293'], null],
            'un ancho que no es ningún tipo' => [['1001', '482'], null],
            'más filtros que tipos' => [['1001', '48213', '520002', '48214'], null],
            'un filtro y luego algo que no es un filtro' => [['1001', '48213', '1'], null],
            'letras' => [['1001', 'rojo'], null],
        ];
    }

    /**
     * @param list<string> $segmentos
     * @param array<string, string>|null $filtros
     */
    #[DataProvider('rutas')]
    public function testFiltros(array $segmentos, ?array $filtros): void
    {
        $match = $this->estrategia->resolve($segmentos, $this->config);

        if ($filtros === null) {
            $this->assertNull($match);
            return;
        }
        $this->assertSame('POS\GrupoController', $match['controller']);
        $this->assertSame('show', $match['method']);
        $this->assertSame(['id' => '1001', 'filtros' => $filtros], $match['params']);
    }

    /** Only a type that declares filtros takes them; for every other type 2+ digits after the id is still a 404. */
    public function testOtherTypesDoNotTakeFilters(): void
    {
        $this->assertNull($this->estrategia->resolve(['48213', '1001'], $this->config), 'an atributo');
        $this->assertNull($this->estrategia->resolve(['81372047', '48213'], $this->config), 'a producto');
        $this->assertNotNull($this->estrategia->resolve(['48213', '1'], $this->config), 'its action still works');
    }

    public function testOneDigitAfterAFilterTypeIsStillAnAction(): void
    {
        $this->assertNull($this->estrategia->resolve(['1001', '1'], $this->config), 'a category has no actions: a 404, not a filter');
    }

    public function testWithoutFiltersTheOldShapesAreUntouched(): void
    {
        $this->assertSame(['id' => '1001'], $this->estrategia->resolve(['1001'], $this->config)['params'] ?? null);
        $this->assertSame(['id' => '81372047'], $this->estrategia->resolve(['81372047', '1'], $this->config)['params'] ?? null);
    }

    public function testTheConfigAcceptsFiltersOfOtherTypes(): void
    {
        ConfigValidator::validate($this->config);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function configsMalas(): array
    {
        $tipos = [
            5 => ['type' => 'atributo', 'controller' => 'X\A'],
            4 => ['type' => 'grupo', 'controller' => 'X\G'],
            1 => ['type' => 'uno', 'controller' => 'X\U'],
        ];

        return [
            'una lista vacía' => [[4 => $tipos[4] + ['filtros' => []]] + $tipos],
            'un tipo que no existe' => [[4 => $tipos[4] + ['filtros' => ['marciano']]] + $tipos],
            'el tipo mismo' => [[4 => $tipos[4] + ['filtros' => ['grupo']]] + $tipos],
            'repetido' => [[4 => $tipos[4] + ['filtros' => ['atributo', 'atributo']]] + $tipos],
            'un tipo de un dígito (sería una acción)' => [[4 => $tipos[4] + ['filtros' => ['uno']]] + $tipos],
            'no es una lista' => [[4 => $tipos[4] + ['filtros' => 'atributo']] + $tipos],
        ];
    }

    /** @param array<int, array<string, mixed>> $porLargo */
    #[DataProvider('configsMalas')]
    public function testBadFilterConfigIsRejected(array $porLargo): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ConfigValidator::validate(['by_length' => $porLargo]);
    }

    private function ayudante(): UrlHelper
    {
        $_SERVER['REQUEST_URI'] = '/';
        $router = new Router();
        $router->loadConfig(__DIR__ . '/../../../config/routes/pos.php');

        return new UrlHelper($router);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI']);
    }

    /** @return array<string, array{array<string, int|string>, string}> */
    public static function enlaces(): array
    {
        return [
            'sin filtros' => [[], '/1001/'],
            'un atributo' => [['atributo' => 48213], '/1001/48213/'],
            'una etiqueta (rellena con ceros)' => [['etiqueta' => 7], '/1001/000007/'],
            'un atributo corto se rellena' => [['atributo' => 5], '/1001/00005/'],
            'siempre el ancho menor primero' => [['etiqueta' => 520002, 'atributo' => 48213], '/1001/48213/520002/'],
            'ya en orden' => [['atributo' => 48213, 'etiqueta' => 520002], '/1001/48213/520002/'],
        ];
    }

    /** @param array<string, int|string> $filtros */
    #[DataProvider('enlaces')]
    public function testFiltrado(array $filtros, string $esperado): void
    {
        $this->assertSame($esperado, $this->ayudante()->filtrado('grupo', 1001, $filtros));
    }

    public function testFiltradoRejectsAnUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->ayudante()->filtrado('grupo', 1001, ['marciano' => 1]);
    }

    /** The link and the router agree: what filtrado() writes is what the router reads back, and it is canonical. */
    public function testWhatFiltradoWritesIsCanonicalAndReadsBack(): void
    {
        $url = $this->ayudante()->filtrado('grupo', 1001, ['etiqueta' => 520002, 'atributo' => 48213]);

        $this->assertNull(Router::canonica($url));
        $match = $this->estrategia->resolve(array_values(array_filter(explode('/', $url))), $this->config);
        $this->assertSame(['atributo' => '48213', 'etiqueta' => '520002'], $match['params']['filtros'] ?? null);
    }
}
