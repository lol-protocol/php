<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Csrf;
use App\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private ?string $metodoOriginal = null;

    protected function setUp(): void
    {
        $this->metodoOriginal = $_SERVER['REQUEST_METHOD'] ?? null;
        unset($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    protected function tearDown(): void
    {
        if ($this->metodoOriginal === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->metodoOriginal;
        }
        unset($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    public function testPaginaDesconocidaDa404YNoLlamaAlHandler(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $llamado = false;
        $router = new Router();
        $router->add('existe', function () use (&$llamado): void {
            $llamado = true;
        });

        ob_start();
        $router->dispatch('no-existe');
        $salida = ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertStringContainsString('404', $salida);
        self::assertFalse($llamado);
    }

    public function testGetLlamaAlHandlerSinImportarElCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $llamado = false;
        $router = new Router();
        $router->add('pagina', function () use (&$llamado): void {
            $llamado = true;
        });

        $router->dispatch('pagina');

        self::assertTrue($llamado, 'un GET no deberia depender del token CSRF');
    }

    public function testPostSinTokenCsrfDa403YNoLlamaAlHandler(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['csrf_token']);
        $llamado = false;
        $router = new Router();
        $router->add('pagina', function () use (&$llamado): void {
            $llamado = true;
        });

        ob_start();
        $router->dispatch('pagina');
        $salida = ob_get_clean();

        self::assertSame(403, http_response_code());
        self::assertNotSame('', $salida);
        self::assertFalse($llamado, 'sin token valido, el handler no debe ejecutarse');
    }

    public function testPostConTokenCsrfValidoLlamaAlHandler(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = Csrf::token();
        $llamado = false;
        $router = new Router();
        $router->add('pagina', function () use (&$llamado): void {
            $llamado = true;
        });

        $router->dispatch('pagina');

        self::assertTrue($llamado);
    }
}
