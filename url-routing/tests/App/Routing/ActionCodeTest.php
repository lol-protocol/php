<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Support\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router::actionCode(): the digit after the id that a type's action has in the routes config, so a controller
 * that builds the URL of one of its own pages doesn't repeat the number.
 */
class ActionCodeTest extends TestCase
{
    private function router(string $sitio): Router
    {
        $router = new Router();
        $router->loadConfig(__DIR__ . "/../../../config/routes/{$sitio}.php");

        return $router;
    }

    public function testDevuelveElCodigoQueDiceLaConfiguracion(): void
    {
        $router = $this->router('genealogy');

        $config = require __DIR__ . '/../../../config/routes/genealogy.php';
        foreach ($config['by_length'] as $entrada) {
            foreach ($entrada['actions'] as $codigo => $metodo) {
                $this->assertSame($codigo, $router->actionCode($entrada['type'], $metodo), "{$entrada['type']} {$metodo}");
            }
        }
    }

    public function testLasPestanasDeUnaPersonaTienenSusCodigos(): void
    {
        $router = $this->router('genealogy');

        $this->assertSame(
            ['ascendencia' => 1, 'descendencia' => 2, 'vinculos' => 3, 'cronologia' => 4, 'viva' => 5],
            array_combine(
                ['ascendencia', 'descendencia', 'vinculos', 'cronologia', 'viva'],
                array_map(fn(string $accion): ?int => $router->actionCode('persona', $accion), ['ascendencia', 'descendencia', 'vinculos', 'cronologia', 'viva'])
            ),
            'the personas\' URLs that already circulate (/{id}/1/ ... /{id}/5/) must keep their meaning'
        );
    }

    public function testNullSiElTipoOLaAccionNoExisten(): void
    {
        $router = $this->router('genealogy');

        $this->assertNull($router->actionCode('persona', 'no_existe'));
        $this->assertNull($router->actionCode('no_existe', 'ascendencia'));
        $this->assertNull($router->actionCode('suceso', 'ascendencia'), 'a type with no actions');
        $this->assertNull($this->router('pos')->actionCode('persona', 'ascendencia'), 'the other site has no personas');
    }
}
