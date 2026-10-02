<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /** @var string[] */
    private array $envKeysToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->envKeysToClean as $key) {
            putenv($key);
        }
        $this->envKeysToClean = [];
    }

    private function uniqueKey(string $suffix): string
    {
        $key = 'TEST_CFG_' . strtoupper($suffix) . '_' . str_replace('.', '', uniqid());
        $this->envKeysToClean[] = $key;
        return $key;
    }

    public function testGetReturnsDefaultWhenUnset(): void
    {
        Config::load([]);
        $key = $this->uniqueKey('missing');

        $this->assertSame('fallback', Config::get($key, 'fallback'));
    }

    public function testGetPrefersDefaultsArrayOverBareDefault(): void
    {
        $key = $this->uniqueKey('array');
        Config::load([$key => 'from-defaults']);

        $this->assertSame('from-defaults', Config::get($key, 'fallback'));
    }

    public function testGetPrefersEnvironmentOverDefaultsArray(): void
    {
        $key = $this->uniqueKey('env');
        Config::load([$key => 'from-defaults']);
        putenv("{$key}=from-env");

        $this->assertSame('from-env', Config::get($key));
    }

    public function testGetIntCastsNumericStrings(): void
    {
        $key = $this->uniqueKey('int');
        putenv("{$key}=42");

        $this->assertSame(42, Config::getInt($key));
    }

    public function testGetIntFallsBackOnNonNumeric(): void
    {
        $key = $this->uniqueKey('badint');
        putenv("{$key}=not-a-number");

        $this->assertSame(7, Config::getInt($key, 7));
    }

    public function testGetBoolRecognisesTruthyStrings(): void
    {
        $key = $this->uniqueKey('bool');

        putenv("{$key}=true");
        $this->assertTrue(Config::getBool($key));

        putenv("{$key}=1");
        $this->assertTrue(Config::getBool($key));

        putenv("{$key}=yes");
        $this->assertTrue(Config::getBool($key));

        putenv("{$key}=false");
        $this->assertFalse(Config::getBool($key));
    }

    public function testGetArraySplitsCommaSeparatedString(): void
    {
        $key = $this->uniqueKey('array2');
        putenv("{$key}=a, b ,c");

        $this->assertSame(['a', 'b', 'c'], Config::getArray($key));
    }

    public function testGetArrayReturnsDefaultWhenUnset(): void
    {
        $key = $this->uniqueKey('array3');

        $this->assertSame(['x', 'y'], Config::getArray($key, ['x', 'y']));
    }

    public function testDotEnvFillsUnsetVariablesButRealEnvironmentWins(): void
    {
        $unset = $this->uniqueKey('fromfile');
        $set = $this->uniqueKey('fromenv');
        putenv("{$set}=real");

        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\n{$unset}=\"from file\"\n{$set}=from file\nDSN_{$unset}=pgsql:host=x;dbname=y\n");
        $this->envKeysToClean[] = "DSN_{$unset}";

        try {
            Config::load([], $file);
        } finally {
            unlink($file);
        }

        $this->assertSame('from file', getenv($unset), 'Quotes are stripped');
        $this->assertSame('real', getenv($set), 'An existing variable is never overwritten');
        $this->assertSame('pgsql:host=x;dbname=y', getenv("DSN_{$unset}"), 'Values may contain "="');
    }
}
