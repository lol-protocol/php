<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\WordListSync;

/** The word lists of the three parsers come from data/palabras.json: none can drift from it unnoticed. */
class WordListSyncTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private const GENERATED_FILES = [
        'src/PhoneDirectory/Parser/StreetRules.php',
        'src/PhoneDirectory/Parser/MultiLanguagePhoneDirectoryParser.php',
        'ports/python/phone_directory_parser.py',
        'ports/java/PhoneDirectoryParser.java',
    ];

    private string $copy = '';

    protected function tearDown(): void
    {
        if ($this->copy !== '' && is_dir($this->copy)) {
            foreach (array_merge(self::GENERATED_FILES, ['data/palabras.json']) as $file) {
                @unlink("{$this->copy}/{$file}");
            }
            exec('rm -rf ' . escapeshellarg($this->copy));
        }
    }

    /** A copy of what the generator reads and writes, to change without touching the real files. */
    private function copyOfTheProject(): string
    {
        $this->copy = sys_get_temp_dir() . '/phonedir_words_' . bin2hex(random_bytes(4));
        foreach (array_merge(self::GENERATED_FILES, ['data/palabras.json']) as $file) {
            if (!is_dir(dirname("{$this->copy}/{$file}"))) {
                mkdir(dirname("{$this->copy}/{$file}"), 0777, true);
            }
            copy(self::ROOT . "/{$file}", "{$this->copy}/{$file}");
        }

        return $this->copy;
    }

    public function testEveryGeneratedRegionIsUpToDate(): void
    {
        $this->assertSame(
            [],
            (new WordListSync(self::ROOT))->sync(write: false),
            'corre `php bin/generar-palabras.php` y commitea lo que cambie'
        );
    }

    /** @return array<string, mixed> */
    private static function lists(): array
    {
        return json_decode((string) file_get_contents(self::ROOT . '/data/palabras.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testTheListsAreCoherent(): void
    {
        $lists = self::lists();
        $languages = $lists['idiomas'];

        $this->assertSame($languages, array_values(array_unique($languages)));
        $this->assertSame([], array_diff($lists['numero_al_final'], $languages), 'a number-last language that is not supported');

        foreach (['calle', 'sufijos_de_calle', 'abreviaturas_de_nombre', 'deteccion', 'empresa'] as $section) {
            $this->assertSame([], array_diff(array_keys($lists[$section]), $languages), "{$section}: a language that is not supported");
            foreach ($lists[$section] as $language => $words) {
                $this->assertSame($words, array_values(array_unique($words)), "{$section}/{$language} repeats a word");
                foreach ($words as $word) {
                    $this->assertSame(mb_strtolower($word, 'UTF-8'), $word, "{$section}/{$language}: '{$word}' is not lowercase");
                    $this->assertSame(trim($word), $word, "{$section}/{$language}: '{$word}' has spaces around it");
                    $this->assertNotSame('', $word, "{$section}/{$language} has an empty word");
                }
            }
        }

        foreach ($languages as $language) {
            $this->assertNotEmpty($lists['calle'][$language] ?? [], "{$language} has no street words");
            $this->assertNotEmpty($lists['empresa'][$language] ?? [], "{$language} has no business words");
        }

        // The abbreviations that open a name must be street words, or listing them there changes nothing.
        foreach ($lists['abreviaturas_de_nombre'] as $language => $words) {
            $this->assertSame([], array_diff($words, $lists['calle'][$language]), "{$language}: an abbreviation that is not a street word");
        }
    }

    public function testAChangeInTheListIsWrittenToThePhpPythonAndJavaParsers(): void
    {
        $root = $this->copyOfTheProject();
        $path = "{$root}/data/palabras.json";
        file_put_contents($path, str_replace('"c/", ', '"c/", "cl.", ', (string) file_get_contents($path), $count));
        $this->assertSame(1, $count);

        // A street word is in StreetRules and in the two ports; the multi-language parser only has detection and business words.
        $streetFiles = [self::GENERATED_FILES[0], self::GENERATED_FILES[2], self::GENERATED_FILES[3]];

        $sync = new WordListSync($root);
        $this->assertSame($streetFiles, $sync->sync(write: false));
        $this->assertSame($streetFiles, $sync->sync(write: false), 'checking writes nothing');

        $sync->sync(write: true);

        foreach ($streetFiles as $file) {
            $this->assertStringContainsString("cl.", (string) file_get_contents("{$root}/{$file}"), $file);
        }
        $this->assertSame([], $sync->sync(write: false), 'a second run changes nothing');
    }

    public function testAFileWithoutItsMarkersIsRefused(): void
    {
        $root = $this->copyOfTheProject();
        $file = "{$root}/ports/python/phone_directory_parser.py";
        file_put_contents($file, str_replace('END generated: palabras/empresas', 'FIN', (string) file_get_contents($file)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("no region 'palabras/empresas'");
        (new WordListSync($root))->sync(write: false);
    }
}
