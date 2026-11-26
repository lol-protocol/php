<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Exception\InvalidLanguageException;
use PhoneDirectory\Parser\MultiLanguagePhoneDirectoryParser;
use PhoneDirectory\Parser\ParityCorpus;

/**
 * The corpus the Python and Java ports are compared against (see ParityCorpus) must be what the PHP parser gives
 * today, and the parser must not throw on any input of it or on the hostile ones below.
 */
class ParityCorpusTest extends TestCase
{
    public function testTheCommittedCorpusIsWhatTheParserGivesToday(): void
    {
        $this->assertSame(
            file_get_contents(__DIR__ . '/../fixtures/parser-paridad.json'),
            ParityCorpus::encode(ParityCorpus::build()),
            'corre `php bin/generar-corpus-paridad.php`, mira que los puertos sigan dando lo mismo y commitea lo que cambie'
        );
    }

    public function testNoInputOfTheCorpusMakesTheParserThrow(): void
    {
        foreach (ParityCorpus::inputs() as [$language, $input]) {
            (new MultiLanguagePhoneDirectoryParser())->parseContent($input, $language);
        }
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function hostileInputs(): iterable
    {
        yield 'typographic apostrophe at the start' => ["’t Hart Jan 12 Oak Street 555-123-4567"];
        yield 'quotes around a name' => ["“Alfa” Bakery 12 Oak Street 555-123-4567"];
        yield 'a very long line of commas' => [str_repeat('a,', 20000)];
        yield 'a very long line of dots' => [str_repeat('. ', 20000)];
        yield 'a very long line of digits' => [str_repeat('1 ', 20000)];
        yield 'a very long line of parentheses' => [str_repeat('(', 20000)];
        yield 'many records on one line' => [str_repeat('SMITH, John ', 5000)];
        yield 'invalid UTF-8' => ["SMITH, \xff John\n12 Oak Street\n555-123-4567"];
        yield 'a NUL byte' => ["SMITH, John\0\n12 Oak Street\n555-123-4567"];
        yield 'only whitespace' => ["  \n\t\n  "];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostileInputs')]
    public function testHostileInputDoesNotMakeTheParserThrow(string $input): void
    {
        foreach ([null, ...ParityCorpus::LANGUAGES] as $language) {
            try {
                (new MultiLanguagePhoneDirectoryParser())->parseContent($input, $language);
            } catch (InvalidLanguageException) {
                $this->fail('a supported language was refused');
            }
        }
        $this->addToAssertionCount(1);
    }
}
