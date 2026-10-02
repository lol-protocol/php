<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\POS\CatalogoRepository;
use App\Repositories\POS\OrdenRepository;
use App\Repositories\POS\ProductoRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Runs against the demo seed (database/pos/seeds) on every available engine. */
class PosRepositoriesTest extends TestCase
{
    private const CAMISETA = 81372047;
    private const SUDADERA = 81372048;
    private const GORRA = 81372049;
    private const DESCONTINUADA = 81372050;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private function db(string $driver): Database
    {
        return TestDatabases::seeded($driver, 'pos');
    }

    /** @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn(array $r) => (int)$r['id'], $rows);
    }

    #[DataProvider('drivers')]
    public function testFindProductoNormalisesActivo(string $driver): void
    {
        $repo = new ProductoRepository($this->db($driver));

        $this->assertTrue($repo->find(self::CAMISETA)['activo']);
        $this->assertSame('Camisetas', $repo->find(self::CAMISETA)['grupo_nombre']);
        $this->assertFalse($repo->find(self::DESCONTINUADA)['activo']);
        $this->assertNull($repo->find(99999999));
    }

    #[DataProvider('drivers')]
    public function testVariantesInheritProductPriceUnlessOverridden(string $driver): void
    {
        $precios = array_column(
            (new ProductoRepository($this->db($driver)))->variantes(self::SUDADERA),
            'precio_centavos',
            'sku'
        );

        $this->assertSame(['SUD-AZ-L' => 74900, 'SUD-RO-M' => 69900], array_map('intval', $precios));
    }

    #[DataProvider('drivers')]
    public function testAtributosAndEtiquetas(string $driver): void
    {
        $repo = new ProductoRepository($this->db($driver));

        $tipos = array_count_values(array_column($repo->atributos(self::CAMISETA), 'tipo'));
        $this->assertSame(['color' => 2, 'material' => 1, 'talla' => 3], $tipos);
        $this->assertSame(['Algodón orgánico', 'Nuevo'], array_column($repo->etiquetas(self::CAMISETA), 'nombre'));
    }

    #[DataProvider('drivers')]
    public function testBuscarExcludesDiscontinuedProducts(string $driver): void
    {
        $this->assertSame(
            [self::CAMISETA],
            self::ids((new ProductoRepository($this->db($driver)))->buscar('camiseta'))
        );
    }

    #[DataProvider('drivers')]
    public function testBuscarMatchesWildcardsLiterally(string $driver): void
    {
        $repo = new ProductoRepository($this->db($driver));

        $this->assertSame([], $repo->buscar('%'), 'A bare "%" must not match every product');
        $this->assertSame([], $repo->buscar('_'));
        $this->assertCount(3, $repo->buscar(''), 'An empty query lists every active product');
    }

    #[DataProvider('drivers')]
    public function testCatalogGroupingsListOnlyActiveProducts(string $driver): void
    {
        $repo = new CatalogoRepository($this->db($driver));

        $this->assertSame('Azul marino', $repo->atributo(48213)['nombre']);
        $this->assertSame(
            [self::CAMISETA, self::GORRA, self::SUDADERA],
            self::ids($repo->productosConAtributo(48213))
        );
        $this->assertSame([self::GORRA], self::ids($repo->productosConEtiqueta(520003)));
        $this->assertSame([self::SUDADERA, self::CAMISETA], self::ids($repo->productosDeColeccion(7300001)));
    }

    #[DataProvider('drivers')]
    public function testGrupoIncludesDirectSubcategories(string $driver): void
    {
        $repo = new CatalogoRepository($this->db($driver));

        $this->assertSame('Ropa', $repo->grupo(1002)['padre_nombre']);
        $this->assertSame([1002, 1003], self::ids($repo->subgrupos(1001)));
        $this->assertSame([self::CAMISETA, self::SUDADERA], self::ids($repo->productosDeGrupo(1001)));
    }

    #[DataProvider('drivers')]
    public function testCatalogSearchRejectsUnknownType(string $driver): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CatalogoRepository($this->db($driver)))->buscar('usuarios', 'x');
    }

    #[DataProvider('drivers')]
    public function testOrdenItemsTrackingAndOwnerListing(string $driver): void
    {
        $repo = new OrdenRepository($this->db($driver));

        $orden = $repo->find(8137204719000);
        $this->assertSame(1, (int)$orden['usuario_id']);

        $items = $repo->items(8137204719000);
        $this->assertSame(
            (int)$orden['total_centavos'],
            array_sum(array_map('intval', array_column($items, 'subtotal_centavos'))),
            'Line items add up to the stored total'
        );

        $this->assertSame(['pagada', 'enviada'], array_column($repo->eventos(8137204719000), 'estado'));
        $this->assertSame([8137204719000], self::ids($repo->deUsuario(1)));
    }

    #[DataProvider('drivers')]
    public function testUsuarioDeseosAndDirecciones(string $driver): void
    {
        $repo = new UsuarioRepository($this->db($driver));

        $this->assertSame([self::SUDADERA], self::ids($repo->deseos(1)));

        $direcciones = $repo->direcciones(1);
        $this->assertSame('Casa', $direcciones[0]['alias']);
        $this->assertTrue($direcciones[0]['principal']);
        $this->assertFalse($direcciones[1]['principal']);
        $this->assertSame([], $repo->direcciones(2));
    }
}
