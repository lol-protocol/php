<?php

namespace Tests\Bin;

use PHPUnit\Framework\TestCase;

/** bin/inflection-check.php --min-precision=N: sale con 1 si la precisión de algún tipo baja de N %. */
class InflectionCheckTest extends TestCase
{
    private string $tsv;

    protected function setUp(): void
    {
        $this->tsv = sys_get_temp_dir() . '/inflection-check-' . bin2hex(random_bytes(4)) . '.tsv';
    }

    protected function tearDown(): void
    {
        @unlink($this->tsv);
    }

    /** @return array{int,string} código de salida y salida estándar */
    private function check(string $unimorph, string ...$options): array
    {
        file_put_contents($this->tsv, $unimorph);
        $command = array_merge([PHP_BINARY, __DIR__ . '/../../bin/inflection-check.php', 'spa', $this->tsv, '-', '10'], $options);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

        return [proc_close($process), $output];
    }

    public function testPassesWhenPrecisionReachesTheMinimum(): void
    {
        [$code, $output] = $this->check("gato\tgato\tN;NOM;SG\ngato\tgatos\tN;NOM;PL\n", '--min-precision=50');

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('precisión=100%', $output);
    }

    public function testFailsAndNamesTheTypeBelowTheMinimum(): void
    {
        [$code, $output] = $this->check("gato\tgato\tN;NOM;SG\ngato\tzzz\tN;NOM;PL\n", '--min-precision=50');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Precisión por debajo del mínimo de 50 %: spa N', $output);
    }

    public function testWithoutTheOptionItNeverFails(): void
    {
        [$code] = $this->check("gato\tgato\tN;NOM;SG\ngato\tzzz\tN;NOM;PL\n");

        $this->assertSame(0, $code);
    }
}
