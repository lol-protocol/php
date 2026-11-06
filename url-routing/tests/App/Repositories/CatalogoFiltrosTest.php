<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\POS\CatalogoRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** The category narrowed by an atributo and/or an etiqueta (/1001/48213/). The demo: Ropa (1001) holds Camisetas and Sudaderas. */
class CatalogoFiltrosTest extends TestCase
{
    private const ROPA = 1001;
    private const CAMISETA = 81372047;   // azul marino, blanco, tallas S-M-L, algodón; orgánico, nuevo
    private const SUDADERA = 81372048;   // azul marino, rojo, tallas M-L;               nuevo
    private const AZUL = 48213;
    private const BLANCO = 48214;
    private const ROJO = 48215;
    private const ALGODON = 48400;
    private const NUEVO = 520002;
    private const OFERTA = 520003;       // only the Gorra, which is an Accesorio, not Ropa

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /** @return list<int> */
    private function ids(string $driver, array $filtros): array
    {
        $repo = new CatalogoRepository(TestDatabases::fresh($driver, 'pos', seed: true));

        return array_map(static fn(array $p): int => (int)$p['id'], $repo->productosDeGrupo(self::ROPA, $filtros));
    }

    #[DataProvider('drivers')]
    public function testWithoutFiltersTheCategoryIsWhatItAlwaysWas(string $driver): void
    {
        $this->assertSame([self::CAMISETA, self::SUDADERA], $this->ids($driver, []));
    }

    #[DataProvider('drivers')]
    public function testAnAtributoNarrowsTheCategory(string $driver): void
    {
        $this->assertSame([self::CAMISETA, self::SUDADERA], $this->ids($driver, ['atributo' => self::AZUL]));
        $this->assertSame([self::SUDADERA], $this->ids($driver, ['atributo' => self::ROJO]));
        $this->assertSame([self::CAMISETA], $this->ids($driver, ['atributo' => self::BLANCO]));
        $this->assertSame([self::CAMISETA], $this->ids($driver, ['atributo' => self::ALGODON]));
    }

    #[DataProvider('drivers')]
    public function testAnEtiquetaNarrowsTheCategory(string $driver): void
    {
        $this->assertSame([self::CAMISETA, self::SUDADERA], $this->ids($driver, ['etiqueta' => self::NUEVO]));
        $this->assertSame([], $this->ids($driver, ['etiqueta' => self::OFERTA]), 'the only product with it is in another category');
    }

    #[DataProvider('drivers')]
    public function testBothFiltersMustHold(string $driver): void
    {
        $this->assertSame([self::SUDADERA], $this->ids($driver, ['atributo' => self::ROJO, 'etiqueta' => self::NUEVO]));
        $this->assertSame([], $this->ids($driver, ['atributo' => self::ROJO, 'etiqueta' => self::OFERTA]));
    }

    #[DataProvider('drivers')]
    public function testADiscontinuedProductNeverShows(string $driver): void
    {
        $this->assertNotContains(81372050, $this->ids($driver, []));
    }

    public function testOnlyTheFiltersOfACategoryAreAccepted(): void
    {
        $repo = new CatalogoRepository(TestDatabases::fresh('sqlite', 'pos', seed: true));

        $this->expectException(\InvalidArgumentException::class);
        $repo->productosDeGrupo(self::ROPA, ['coleccion' => 1]);
    }

    /**
     * A value that is not a number cannot become SQL: it is cast and bound. Rojo is only on the sudadera, so if the
     * text got into the query («OR 1=1») both products would come back.
     */
    public function testAFilterValueIsBoundNotWrittenIntoTheQuery(): void
    {
        $repo = new CatalogoRepository(TestDatabases::fresh('sqlite', 'pos', seed: true));

        $filas = $repo->productosDeGrupo(self::ROPA, ['atributo' => '48215 OR 1=1']);

        $this->assertSame([self::SUDADERA], array_map(static fn(array $p): int => (int)$p['id'], $filas));
    }

    #[DataProvider('drivers')]
    public function testOnlyFiltersThatLeadSomewhereAreOffered(string $driver): void
    {
        $repo = new CatalogoRepository(TestDatabases::fresh($driver, 'pos', seed: true));

        $ofrecidos = $repo->filtrosDeGrupo(self::ROPA);

        $this->assertEqualsCanonicalizing([self::AZUL, self::BLANCO, self::ROJO, self::ALGODON, 48300, 48301, 48302], array_map('intval', array_column($ofrecidos['atributo'], 'id')));
        $this->assertEqualsCanonicalizing([520001, self::NUEVO], array_map('intval', array_column($ofrecidos['etiqueta'], 'id')), 'Oferta is only on a product outside Ropa');
    }
}
