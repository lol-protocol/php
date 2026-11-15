<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Textos;
use PHPUnit\Framework\TestCase;

/** The texts in each language (lang/{código}.php): what t() says, and that no table drifts from Spanish, the source. */
class TextosTest extends TestCase
{
    public function testItSaysTheTextInTheLanguageAskedFor(): void
    {
        $this->assertSame('Página no encontrada', Textos::t('error.404', [], 'spa'));
        $this->assertSame('Page not found', Textos::t('error.404', [], 'eng'));
    }

    public function testWithoutALanguageItIsSpanishOutsideTheSite(): void
    {
        $this->assertSame('Página no encontrada', Textos::t('error.404'), 'a unit test, a CLI script: no request, no subdomain');
    }

    public function testTheMarksAreFilledIn(): void
    {
        $this->assertSame('Recibimos tu pedido de retiro n.º 7. Lo revisa una persona y te responde a a@b.cl.', Textos::t('retiro.flash.ok', ['numero' => 7, 'correo' => 'a@b.cl'], 'spa'));
        $this->assertStringContainsString('no. 7.', Textos::t('retiro.flash.ok', ['numero' => 7, 'correo' => 'a@b.cl'], 'eng'));
        $this->assertStringContainsString('at a@b.cl', Textos::t('retiro.flash.ok', ['numero' => 7, 'correo' => 'a@b.cl'], 'eng'));
    }

    public function testALanguageWithoutATableSaysItInSpanish(): void
    {
        $this->assertSame('Página no encontrada', Textos::t('error.404', [], 'fra'), 'a language that is active but not translated yet');
        $this->assertSame('Página no encontrada', Textos::t('error.404', [], '../lang/eng'), 'a code that is not three letters never reaches the path');
    }

    public function testAKeyNobodyWroteIsShownAsItself(): void
    {
        $this->assertSame('error.que_no_existe', Textos::t('error.que_no_existe', [], 'eng'), 'a typo is seen, it is not silent');
    }

    public function testEnglishHasExactlyTheKeysOfSpanish(): void
    {
        $spa = Textos::claves('spa');
        $eng = Textos::claves('eng');

        $this->assertNotSame([], $spa);
        $this->assertSame([], array_values(array_diff($spa, $eng)), 'a Spanish text with no English one');
        $this->assertSame([], array_values(array_diff($eng, $spa)), 'an English text that Spanish does not have');
    }

    public function testEachTextHasTheSameMarksInEveryLanguageAndIsNotEmpty(): void
    {
        foreach (Textos::claves('spa') as $clave) {
            $spa = Textos::t($clave, [], 'spa');
            $eng = Textos::t($clave, [], 'eng');
            $this->assertNotSame('', trim($spa), $clave);
            $this->assertNotSame('', trim($eng), $clave);
            preg_match_all('/\{(\w+)\}/', $spa, $deSpa);
            preg_match_all('/\{(\w+)\}/', $eng, $deEng);
            $this->assertEqualsCanonicalizing($deSpa[1], $deEng[1], "{$clave}: the marks to fill in must be the same");
        }
    }

    /** A key written in a view or in code that Spanish does not have would show up as itself on the page. */
    public function testEveryKeyTheCodeAsksForExists(): void
    {
        $raiz = dirname(__DIR__, 3);
        $existentes = array_flip(Textos::claves('spa'));
        $pedidas = [];
        foreach (['Controllers', 'Support', 'views', 'public'] as $carpeta) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$raiz}/{$carpeta}", \FilesystemIterator::SKIP_DOTS)) as $archivo) {
                if ($archivo->getExtension() !== 'php') {
                    continue;
                }
                $texto = (string)file_get_contents($archivo->getPathname());
                if (preg_match_all("/(?:Textos::t|\\bt)\\('([a-z0-9_.]+)'/", $texto, $m) > 0) {
                    foreach ($m[1] as $clave) {
                        $pedidas[$clave][] = substr($archivo->getPathname(), strlen($raiz) + 1);
                    }
                }
            }
        }

        $this->assertNotSame([], $pedidas, 'the scan finds the calls (otherwise this test proves nothing)');
        // A key that ends in a dot is a prefix the code completes (retiro.motivo. + the reason): some text must start with it.
        $faltan = array_filter(
            array_keys($pedidas),
            static fn(string $clave): bool => str_ends_with($clave, '.')
                ? array_filter(array_keys($existentes), static fn(string $e): bool => str_starts_with($e, $clave)) === []
                : !isset($existentes[$clave])
        );
        $this->assertSame([], array_values($faltan), 'keys used and not in lang/spa.php');
    }

    /** The reasons of a pedido de retiro are a list in the code and the form completes its key with each one: none may lack its text. */
    public function testEveryReasonOfAWithdrawalHasItsTextInEveryLanguage(): void
    {
        foreach (array_keys(\App\Repositories\Genealogy\RetiroRepository::MOTIVOS) as $motivo) {
            foreach (['spa', 'eng'] as $idioma) {
                $this->assertNotSame("retiro.motivo.{$motivo}", Textos::t("retiro.motivo.{$motivo}", [], $idioma), "{$idioma}: the reason {$motivo}");
            }
        }
    }
}
