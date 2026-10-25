<?php

declare(strict_types=1);

namespace Tests\App\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The answers to a pedido de retiro (docs/respuestas-tipo/): every language has the same four texts
 * with the same placeholders, and nobody typed a placeholder wrong. They are copied by hand into an
 * e-mail, so a missing {numero} or a misspelled {firma} would go out as it is.
 */
class RespuestasTipoTest extends TestCase
{
    private const IDIOMAS = ['es', 'en', 'pt-BR', 'fr', 'de', 'it', 'nl', 'pl', 'ru', 'tr', 'ar', 'he', 'hi', 'ja', 'ko', 'zh-Hans'];
    private const TITULOS = ['1. Se oculta mientras se revisa', '2. Se retira', '3. Se rechaza', '4. Falta información'];
    /** What each text must carry, by title (the others are in all of them). */
    private const EXIGE = [0 => ['{pagina}', '{dias}'], 1 => ['{pagina}'], 2 => ['{pagina}', '{razon}'], 3 => ['{que_falta}']];
    private const MARCAS = ['numero', 'pagina', 'dias', 'razon', 'que_falta', 'firma'];

    private static function carpeta(): string
    {
        return dirname(__DIR__, 3) . '/docs/respuestas-tipo';
    }

    /** @return array<string, array{string}> */
    public static function idiomas(): array
    {
        return array_combine(self::IDIOMAS, array_map(static fn(string $i): array => [$i], self::IDIOMAS));
    }

    /** @return list<string> the four texts of a language, in order */
    private static function textos(string $idioma): array
    {
        $md = (string)file_get_contents(self::carpeta() . "/respuestas.{$idioma}.md");
        preg_match_all('/^## (.+)\n\n> (.+)$/mu', $md, $m, PREG_SET_ORDER);
        self::assertSame(self::TITULOS, array_column($m, 1), "{$idioma}: los cuatro títulos, en orden");

        return array_column($m, 2);
    }

    #[DataProvider('idiomas')]
    public function testEveryLanguageHasTheFourTextsWithTheirPlaceholders(string $idioma): void
    {
        foreach (self::textos($idioma) as $i => $texto) {
            foreach (['{numero}', '{firma}', ...self::EXIGE[$i]] as $marca) {
                $this->assertStringContainsString($marca, $texto, "{$idioma}, texto " . ($i + 1));
            }
            preg_match_all('/\{([^}]*)\}/u', $texto, $usadas);
            $this->assertSame([], array_diff($usadas[1], self::MARCAS), "{$idioma}, texto " . ($i + 1) . ': una marca que no existe');
            $this->assertStringNotContainsString('  ', $texto, "{$idioma}, texto " . ($i + 1));
        }
    }

    public function testNoLanguageIsLeftOutNorAddedWithoutBeingListed(): void
    {
        $archivos = array_map(
            static fn(string $f): string => basename($f, '.md'),
            glob(self::carpeta() . '/respuestas.*.md') ?: []
        );
        sort($archivos);
        $esperados = array_map(static fn(string $i): string => "respuestas.{$i}", self::IDIOMAS);
        sort($esperados);

        $this->assertSame($esperados, $archivos);
        $readme = (string)file_get_contents(self::carpeta() . '/README.md');
        $this->assertStringContainsString('16 idiomas', $readme);
    }

    public function testTheTextsDoNotRepeatAnAccusation(): void
    {
        // A reply travels in the clear: it names the page and the number, never what was said about the person.
        foreach (self::IDIOMAS as $idioma) {
            foreach (self::textos($idioma) as $texto) {
                $this->assertDoesNotMatchRegularExpression('/\b(ladr[oó]n|estaf|thief|fraud|puta|whore)/iu', $texto, $idioma);
            }
        }
    }
}
