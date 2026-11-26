<?php

namespace MarcalibrosApp\Tests;

use Marcalibros\Catalog;
use MarcalibrosApp\FuncionesApagables;

/** Lo que se puede apagar con los interruptores del repositorio (opcional, ver FuncionesApagables). */
final class FuncionesApagablesTest extends AppTestCase
{
    /** @var list<string> */
    private array $carpetas = [];

    protected function tearDown(): void
    {
        foreach ($this->carpetas as $carpeta) {
            $this->borrar($carpeta);
        }
        parent::tearDown();
    }

    private function borrar(string $ruta): void
    {
        if (is_file($ruta) || is_link($ruta)) {
            unlink($ruta);
            return;
        }
        foreach (scandir($ruta) ?: [] as $e) {
            if ($e !== '.' && $e !== '..') {
                $this->borrar("$ruta/$e");
            }
        }
        rmdir($ruta);
    }

    private function interruptoresReales(): string
    {
        return dirname(__DIR__, 3) . '/interruptores';
    }

    /**
     * Una copia de interruptores/ (código y catálogo) con el estado que se pida; $catalogo puede cambiar el catálogo.
     *
     * @param array<string, bool> $estado
     */
    private function interruptores(array $estado, ?callable $catalogo = null): FuncionesApagables
    {
        $dir = sys_get_temp_dir() . '/marcalibros-interruptores-' . bin2hex(random_bytes(4));
        mkdir($dir . '/src', 0775, true);
        mkdir($dir . '/var', 0775, true);
        $this->carpetas[] = $dir;
        $crudo = (string) file_get_contents($this->interruptoresReales() . '/catalogo.json');
        if ($catalogo !== null) {
            $crudo = json_encode($catalogo(json_decode($crudo, true)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        file_put_contents($dir . '/catalogo.json', $crudo);
        foreach (['Interruptores.php', 'Excepciones.php', 'RegistroJsonl.php'] as $archivo) {
            copy($this->interruptoresReales() . '/src/' . $archivo, $dir . '/src/' . $archivo);
        }
        file_put_contents($dir . '/var/estado.json', json_encode($estado, JSON_THROW_ON_ERROR));
        return FuncionesApagables::fromDirectory($dir);
    }

    private function manifest(): string
    {
        return dirname(__DIR__, 2) . '/catalog/manifest.json';
    }

    public function testWithoutTheVariableNothingIsSwitchedOff(): void
    {
        $this->assertNull(FuncionesApagables::fromEnvironment([]));
        $this->assertNull(FuncionesApagables::fromEnvironment(['MARCALIBROS_INTERRUPTORES' => '']));
    }

    public function testEverythingIsOnWithTheFactoryState(): void
    {
        $f = $this->interruptores([]);
        $this->assertTrue($f->appEnabled());
        $this->assertSame('ocultar', $f->whenOff());
        $this->assertCount(27, (new Catalog($this->manifest(), $f->categoryAllowed(...)))->categories());
    }

    public function testASwitchedOffAppAnswersWithNotFound(): void
    {
        $app = $this->app(null, PHP_INT_MAX, $this->interruptores(['pos.mb-h-app' => false]));
        foreach (['/', '/crear', '/imprimir'] as $path) {
            $this->assertSame(404, $this->get($app, $path)->status, $path);
        }
    }

    public function testTheNoDisponiblePolicyAnswers503(): void
    {
        $f = $this->interruptores(['pos.mb-h-app' => false], static function (array $catalogo): array {
            foreach ($catalogo['funciones'] as &$funcion) {
                if ($funcion['id'] === 'pos.mb-h-app') {
                    $funcion['al_apagar'] = 'no_disponible';
                }
            }
            return $catalogo;
        });
        $respuesta = $this->get($this->app(null, PHP_INT_MAX, $f), '/');
        $this->assertSame(503, $respuesta->status);
        $this->assertStringContainsString('no están disponibles', $respuesta->body);
    }

    public function testACategorySwitchedOffDisappearsWithItsBookmarks(): void
    {
        $todo = new Catalog($this->manifest());
        $f = $this->interruptores(['pos.mb-h-movimientos:romanticismo' => false]);
        $catalogo = new Catalog($this->manifest(), $f->categoryAllowed(...));

        $this->assertFalse($catalogo->hasCategory('romanticismo'));
        $this->assertTrue($catalogo->hasCategory('realismo'));
        $this->assertCount(count($todo->ids()) - 50, $catalogo->ids());

        $app = $this->app(null, PHP_INT_MAX, $f);
        $apagado = $todo->search('romanticismo')[0]['id'];
        $encendido = $todo->search('realismo')[0]['id'];
        $this->assertSame(404, $this->get($app, '/marcalibro/' . $apagado)->status);
        $this->assertSame(200, $this->get($app, '/marcalibro/' . $encendido)->status);
    }

    public function testSwitchingOffAFunctionHidesAllItsCategories(): void
    {
        $f = $this->interruptores(['pos.mb-h-tipos-texto' => false]);
        $this->assertCount(11, (new Catalog($this->manifest(), $f->categoryAllowed(...)))->categories());
    }

    public function testEveryCategoryOfTheManifestHasItsSwitch(): void
    {
        $manifiesto = json_decode((string) file_get_contents($this->manifest()), true);
        $catalogo = json_decode((string) file_get_contents($this->interruptoresReales() . '/catalogo.json'), true);
        $opciones = [];
        foreach ($catalogo['funciones'] as $funcion) {
            if (in_array($funcion['id'], ['pos.mb-h-movimientos', 'pos.mb-h-tipos-texto'], true)) {
                foreach ($funcion['opciones'] as $opcion) {
                    $opciones[] = $opcion['id'];
                }
            }
        }
        $slugs = array_column($manifiesto['categorias'], 'slug');
        sort($slugs);
        sort($opciones);
        $this->assertSame($slugs, $opciones, 'cada categoría del manifiesto necesita su opción en interruptores/catalogo.json, y al revés');
    }

    public function testUnreadableSwitchesAreAnError(): void
    {
        $this->expectException(\RuntimeException::class);
        FuncionesApagables::fromDirectory(sys_get_temp_dir() . '/marcalibros-no-existe-' . bin2hex(random_bytes(4)));
    }
}
