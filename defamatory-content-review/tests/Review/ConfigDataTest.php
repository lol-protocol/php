<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\ConfigData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Un archivo de config/ es sólo datos, y se comprueba sin ejecutarlo. */
class ConfigDataTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function configFiles(): array
    {
        $files = [];
        foreach (glob(__DIR__ . '/../../config/*/*.php') ?: [] as $path) {
            $files[basename(dirname($path)) . '/' . basename($path)] = [$path];
        }
        // config/ también tiene archivos sueltos (p. ej. language-families.php)
        foreach (glob(__DIR__ . '/../../config/*.php') ?: [] as $path) {
            $files[basename($path)] = [$path];
        }

        return $files;
    }

    #[DataProvider('configFiles')]
    public function testEveryRealConfigFileIsData(string $path): void
    {
        $this->assertNull(ConfigData::foreign((string) file_get_contents($path)));
    }

    public function testThereAreConfigFilesToCheck(): void
    {
        $this->assertGreaterThan(60, count(self::configFiles()), 'si el glob no encuentra nada, la prueba de arriba no prueba nada');
    }

    /** @return array<string,array{string}> */
    public static function notData(): array
    {
        $open = "<?php\nreturn ";

        return [
            'llamada a función' => [$open . "['words' => strtoupper('x')];"],
            'define' => ["<?php\ndefine('X', 1);\nreturn ['words' => []];"],
            'variable' => [$open . "['words' => \$x];"],
            'new' => [$open . "['words' => new stdClass()];"],
            'include' => [$open . "include 'otro.php';"],
            'acento grave' => [$open . "['words' => `ls`];"],
            'texto con interpolación' => [$open . "['words' => \"a {\$b}\"];"],
            'heredoc' => [$open . "['words' => <<<X\nabc\nX];"],
            'cierre y HTML' => ["<?php return ['words' => []]; ?>\n<b>hola</b>"],
            'cierre dentro de un comentario' => ["<?php // fin ?> <?php echo 1;\nreturn ['words' => []];"],
            'etiqueta de eco' => ["<?= 'x' ?>"],
            'función anónima' => [$open . "['words' => fn() => 1];"],
            'constante de clase' => [$open . "['words' => Foo::BAR];"],
            'operador' => [$open . "['words' => 1 + 2];"],
        ];
    }

    #[DataProvider('notData')]
    public function testAnythingThatIsNotAPlainLiteralIsReported(string $text): void
    {
        $this->assertNotNull(ConfigData::foreign($text));
    }

    public function testReportsWhereTheFirstForeignThingIs(): void
    {
        $this->assertSame('T_STRING «strtoupper» en la línea 2', ConfigData::foreign("<?php\nreturn ['words' => strtoupper('x')];"));
    }

    public function testASyntaxErrorStillThrows(): void
    {
        $this->expectException(\ParseError::class);

        ConfigData::foreign("<?php\nreturn ['words' => ;");
    }

    public function testAcceptsWhatTheConfigsUse(): void
    {
        $text = "<?php\n// comentario ?? sin cierre\n/** doc */\nreturn [\n    'a' => ['x' => true, 'y' => false, 'z' => null, 'n' => -1.5, 'm' => 3],\n    'b' => 'uno ' . 'dos',\n];\n";

        $this->assertNull(ConfigData::foreign($text));
    }
}
