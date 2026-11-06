<?php

namespace Tests;

use DefamatoryContentReview\Loader;
use PHPUnit\Framework\TestCase;

/** El cargador sin Composer que usan url-routing y sistema-nuevo: se prueba en un proceso aparte, sin el autoload de Composer. */
class LoaderTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testItKnowsWhetherAFolderHasTheLibrary(): void
    {
        $this->assertTrue(Loader::exists(self::ROOT));
        $this->assertFalse(Loader::exists(sys_get_temp_dir()));
    }

    public function testItLoadsTheClassesOfALibraryFolderWithoutComposer(): void
    {
        $codigo = sprintf(
            'require %1$s; use DefamatoryContentReview\Loader; Loader::register(%2$s); Loader::register(%2$s);
             echo count(spl_autoload_functions()), ",", class_exists("DefamatoryContentReview\Chat\ChatLineReviewer") ? "si" : "no",
             ",", class_exists("DefamatoryContentReview\Nada\Existe") ? "si" : "no";',
            var_export(self::ROOT . '/src/Loader.php', true),
            var_export(realpath(self::ROOT), true)
        );

        exec('php -r ' . escapeshellarg($codigo) . ' 2>&1', $salida, $codigo);

        $this->assertSame([0, ['1,si,no']], [$codigo, $salida], 'one loader even if asked twice; a class that is not there is not an error');
    }
}
