<?php

declare(strict_types=1);

namespace Tests\App\Routing;

use App\Support\TablaDeRutas;
use PHPUnit\Framework\TestCase;

/** docs/RUTAS.md must be exactly what the route config says. */
class RutasDocumentadasTest extends TestCase
{
    public function testRutasMdMatchesTheRouteConfig(): void
    {
        $raiz = dirname(__DIR__, 3);

        $this->assertSame(
            TablaDeRutas::markdown("{$raiz}/config/routes"),
            (string)file_get_contents("{$raiz}/docs/RUTAS.md"),
            'docs/RUTAS.md is out of date: run `php bin/rutas.php > docs/RUTAS.md`'
        );
    }
}
