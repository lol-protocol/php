<?php

namespace Tests\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** El nombre, la familia y el código de dos letras viven solo en config/supported-languages.php: el `meta` de cada diccionario no los repite. */
class LanguageMetaTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../../config';
    private const DUPLICATED = ['iso639_1', 'name', 'nativeName', 'family'];

    /** @return array<string,array{string}> */
    public static function dictionaries(): array
    {
        $codes = array_keys(require self::CONFIG . '/supported-languages.php');

        return array_combine($codes, array_map(static fn(string $c): array => [$c], $codes));
    }

    #[DataProvider('dictionaries')]
    public function testMetaDoesNotRepeatTheRegistry(string $code): void
    {
        $meta = (require self::CONFIG . "/languages/{$code}.php")['meta'];

        $this->assertSame($code, $meta['code']);
        $this->assertSame([], array_values(array_intersect(self::DUPLICATED, array_keys($meta))), $code);
    }
}
