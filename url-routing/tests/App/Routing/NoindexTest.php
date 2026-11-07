<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Routing\ConfigValidator;
use App\Support\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The functional pages (account, search, cart, checkout, asking for a withdrawal) answer with
 * `X-Robots-Tag: noindex`. robots.txt only stops crawlers from walking them; a URL that someone links
 * to can still show up in a search engine.
 */
class NoindexTest extends TestCase
{
    private const RUTAS = __DIR__ . '/../../../config/routes';

    private function router(string $sitio, string $uri): Router
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $router = new Router();
        $router->loadConfig(self::RUTAS . "/{$sitio}.php");

        return $router;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI']);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function rutas(): array
    {
        return [
            'genealogía: cuenta' => ['genealogy', '/0/', true],
            'genealogía: una acción de la cuenta' => ['genealogy', '/0/3/', true],
            'genealogía: búsqueda' => ['genealogy', '/b/10/garcia/2/', true],
            'genealogía: pedir un retiro' => ['genealogy', '/r/582317/', true],
            'genealogía: inicio' => ['genealogy', '/', false],
            'genealogía: una persona' => ['genealogy', '/6128473105/', false],
            'genealogía: un lugar' => ['genealogy', '/mx/jal/', false],
            'genealogía: el carrito no existe' => ['genealogy', '/c/', false],
            'POS: cuenta' => ['pos', '/0/2/', true],
            'POS: búsqueda' => ['pos', '/b/8/gorra/', true],
            'POS: carrito' => ['pos', '/c/', true],
            'POS: pago' => ['pos', '/p/3/', true],
            'POS: inicio' => ['pos', '/', false],
            'POS: un producto' => ['pos', '/81372047/', false],
            'POS: una orden' => ['pos', '/4821937065182734/', false],
        ];
    }

    #[DataProvider('rutas')]
    public function testNoindexPorRuta(string $sitio, string $uri, bool $esperado): void
    {
        $this->assertSame($esperado, $this->router($sitio, $uri)->noindex());
    }

    /** What robots.txt keeps crawlers out of must also be marked noindex: the two lists cannot drift apart. */
    #[DataProvider('sitios')]
    public function testLoQueRobotsExcluyeLlevaNoindex(string $sitio, string $controlador): void
    {
        $excluidas = (new \ReflectionMethod($controlador, 'excluidas'))->invoke((new \ReflectionClass($controlador))->newInstanceWithoutConstructor());

        $this->assertNotSame([], $excluidas);
        foreach ($excluidas as $ruta) {
            $this->assertTrue($this->router($sitio, $ruta)->noindex(), "{$sitio}: robots.txt excluye {$ruta} pero no lleva noindex");
        }
    }

    /** @return array<string, array{string, class-string}> */
    public static function sitios(): array
    {
        return [
            'genealogía' => ['genealogy', \App\Controllers\Genealogy\ArchivosController::class],
            'POS' => ['pos', \App\Controllers\POS\ArchivosController::class],
        ];
    }

    public function testNoindexTieneQueSerUnBooleano(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigValidator::validate(['reserved' => ['b' => ['controller' => 'X\Y', 'noindex' => 'si']]]);
    }
}
