<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use PHPUnit\Framework\TestCase;
use Tests\Support\ComandoDePrueba;

/** bin/copias.php as cron runs it: a real subprocess, real SQLite files, its exit code and what it prints. */
class CopiasCliTest extends TestCase
{
    use ComandoDePrueba;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/copias_cli_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/origen', 0777, true);
        foreach (['g', 'p'] as $sitio) {
            $db = new Database('sqlite:' . $this->dir . "/origen/{$sitio}.sqlite");
            $db->execute('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{*,*/*}', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        foreach (['/copias', '/origen', ''] as $sub) {
            @rmdir($this->dir . $sub);
        }
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function correr(string ...$args): array
    {
        return $this->ejecutarComando('copias', $args, [
            'DB_DSN_GENEALOGY' => 'sqlite:' . $this->dir . '/origen/g.sqlite',
            'DB_DSN_POS' => 'sqlite:' . $this->dir . '/origen/p.sqlite',
        ]);
    }

    /** @return list<string> */
    private function copias(): array
    {
        return array_map('basename', glob($this->dir . '/copias/*') ?: []);
    }

    public function testItCopiesBothSitesAndSaysNothing(): void
    {
        [$codigo, $salida, $error] = $this->correr('--destino=' . $this->dir . '/copias');

        $this->assertSame([0, '', ''], [$codigo, $salida, $error], 'cron mails whatever is printed: a good run is silent');
        $hoy = date('Y-m-d');
        $this->assertSame(["genealogy-{$hoy}.sqlite", "pos-{$hoy}.sqlite"], $this->copias());
    }

    public function testItDeletesTheOldCopiesAfterAGoodOne(): void
    {
        mkdir($this->dir . '/copias');
        $vieja = $this->dir . '/copias/genealogy-' . date('Y-m-d', strtotime('-40 days')) . '.sqlite';
        file_put_contents($vieja, 'x');

        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias', '--sitios=genealogy')[0]);
        $this->assertFileDoesNotExist($vieja);

        // another retention keeps it
        file_put_contents($vieja, 'x');
        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias', '--sitios=genealogy', '--dias=60')[0]);
        $this->assertFileExists($vieja);
    }

    public function testAFailingSiteIsReportedButTheOthersAreCopiedAndNothingOfItAgesOut(): void
    {
        mkdir($this->dir . '/copias');
        unlink($this->dir . '/origen/p.sqlite');
        $viejaPos = $this->dir . '/copias/pos-' . date('Y-m-d', strtotime('-40 days')) . '.sqlite';
        file_put_contents($viejaPos, 'la última buena');

        [$codigo, $salida, $error] = $this->correr('--destino=' . $this->dir . '/copias');

        $this->assertSame(1, $codigo);
        $this->assertSame('', $salida);
        $this->assertStringContainsString('Copia de pos: No existe la base SQLite', $error);
        $this->assertFileExists($this->dir . '/copias/genealogy-' . date('Y-m-d') . '.sqlite', 'the other site was still copied');
        $this->assertFileExists($viejaPos, 'a failing backup must not age out the copies that were good');
    }

    public function testTheSendCommandRunsOncePerNewCopy(): void
    {
        $registro = $this->dir . '/copias/enviados.txt';
        mkdir($this->dir . '/copias');

        [$codigo, , $error] = $this->correr('--destino=' . $this->dir . '/copias', '--enviar=basename {archivo} >> ' . $registro);

        $this->assertSame([0, ''], [$codigo, $error]);
        $enviados = file($registro, FILE_IGNORE_NEW_LINES);
        sort($enviados);
        $hoy = date('Y-m-d');
        $this->assertSame(["genealogy-{$hoy}.sqlite", "pos-{$hoy}.sqlite"], $enviados);
    }

    public function testAFailingSendIsAnErrorExitCodeOne(): void
    {
        [$codigo, , $error] = $this->correr('--destino=' . $this->dir . '/copias', '--sitios=genealogy', '--enviar=false {archivo}');

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Copia de genealogy: El comando para enviar la copia falló', $error);
        $this->assertFileExists($this->dir . '/copias/genealogy-' . date('Y-m-d') . '.sqlite', 'the local copy is there');
    }

    public function testUsageErrorsExitWithTwoAndTouchNothing(): void
    {
        foreach ([
            [],
            ['--destino=' . $this->dir . '/copias', '--dias=0'],
            ['--destino=' . $this->dir . '/copias', '--dias=99999'],
            ['--destino=' . $this->dir . '/copias', '--enviar=rsync origen destino'],
            ['--destino=' . $this->dir . '/copias', '--sitios='],
            ['--destino=' . $this->dir . '/copias', '--sitios=../x'],
            ['--destino=' . $this->dir . '/copias', '--raro'],
        ] as $args) {
            $this->assertSame(2, $this->correr(...$args)[0], implode(' ', $args));
        }
        $this->assertDirectoryDoesNotExist($this->dir . '/copias');
    }

    /** @return string a public age key, or skips the test without age */
    private function clavePublicaAge(): string
    {
        if (trim((string)shell_exec('command -v age age-keygen 2>/dev/null')) === '') {
            $this->markTestSkipped('needs age and age-keygen on the PATH');
        }
        $archivo = $this->dir . '/origen/k.txt';
        shell_exec('age-keygen -o ' . escapeshellarg($archivo) . ' 2>/dev/null');
        preg_match('/age1[a-z0-9]{58}/', (string)file_get_contents($archivo), $m);

        return $m[0];
    }

    public function testCifrarParaEncryptsEveryCopyAndSaysNothing(): void
    {
        $publica = $this->clavePublicaAge();

        [$codigo, $salida, $error] = $this->correr('--destino=' . $this->dir . '/copias', '--cifrar-para=' . $publica);

        $this->assertSame([0, '', ''], [$codigo, $salida, $error]);
        $hoy = date('Y-m-d');
        $this->assertSame(["genealogy-{$hoy}.sqlite.age", "pos-{$hoy}.sqlite.age"], $this->copias(), 'only encrypted files');
    }

    public function testTheKeysCanComeFromTheEnvironmentToo(): void
    {
        $publica = $this->clavePublicaAge();

        $r = $this->ejecutarComando('copias', ['--destino=' . $this->dir . '/copias', '--sitios=genealogy'], [
            'DB_DSN_GENEALOGY' => 'sqlite:' . $this->dir . '/origen/g.sqlite',
            'COPIAS_CIFRAR_PARA' => $publica . ', ',
        ]);

        $this->assertSame([0, '', ''], $r);
        $this->assertSame(['genealogy-' . date('Y-m-d') . '.sqlite.age'], $this->copias());
    }

    public function testABadKeyIsAUsageErrorAndFailingAgeIsAFailureWithNoCopy(): void
    {
        [$codigo, , $error] = $this->correr('--destino=' . $this->dir . '/copias', '--cifrar-para=nope');
        $this->assertSame(2, $codigo);
        $this->assertStringContainsString('age1', $error);
        $this->assertDirectoryDoesNotExist($this->dir . '/copias');

        $r = $this->ejecutarComando('copias', ['--destino=' . $this->dir . '/copias', '--sitios=genealogy', '--cifrar-para=age1' . str_repeat('q', 58)], [
            'DB_DSN_GENEALOGY' => 'sqlite:' . $this->dir . '/origen/g.sqlite',
            'PATH' => '/nonexistent',
        ]);
        $this->assertSame(1, $r[0], 'age is not there');
        $this->assertStringContainsString('Copia de genealogy:', $r[2]);
        $this->assertSame([], $this->copias(), 'and no plain copy was left instead');
    }

    public function testVigilarIsSilentWithAFreshCopyAndSaysWhatIsWrongOtherwise(): void
    {
        $this->assertSame(1, $this->correr('--destino=' . $this->dir . '/copias', '--vigilar')[0], 'no folder yet');

        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias')[0]);
        $this->assertSame([0, '', ''], $this->correr('--destino=' . $this->dir . '/copias', '--vigilar'), 'cron mails whatever is printed');

        $vieja = $this->dir . '/copias/genealogy-' . date('Y-m-d', strtotime('-5 days')) . '.sqlite';
        unlink($this->dir . '/copias/genealogy-' . date('Y-m-d') . '.sqlite');
        file_put_contents($vieja, 'x');
        [$codigo, $salida, $error] = $this->correr('--destino=' . $this->dir . '/copias', '--vigilar=3');

        $this->assertSame([1, ''], [$codigo, $salida]);
        $this->assertStringContainsString('Copias de genealogy: la última es del', $error);
        $this->assertStringContainsString('(el límite es 3)', $error);
        $this->assertStringNotContainsString('Copias de pos', $error, 'pos has today\'s copy');
        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias', '--vigilar=7')[0], 'a looser limit');
        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias', '--vigilar', '--sitios=pos')[0], 'only the sites asked for');
    }

    public function testVigilarTouchesNothingAndOptionsThatMakeACopyDoNotGoWithIt(): void
    {
        foreach ([
            ['--destino=' . $this->dir . '/copias', '--vigilar', '--dias=3'],
            ['--destino=' . $this->dir . '/copias', '--vigilar', '--enviar=true {archivo}'],
            ['--destino=' . $this->dir . '/copias', '--vigilar', '--cifrar-para=age1' . str_repeat('q', 58)],
            ['--destino=' . $this->dir . '/copias', '--vigilar', '--verificar=x'],
            ['--destino=' . $this->dir . '/copias', '--vigilar', '--identidad=x'],
            ['--vigilar'],
            ['--destino=' . $this->dir . '/copias', '--vigilar=0'],
            ['--verificar=x', '--dias=3'],
        ] as $args) {
            $this->assertSame(2, $this->correr(...$args)[0], implode(' ', $args));
        }
        $this->assertDirectoryDoesNotExist($this->dir . '/copias', 'nothing was created');
    }

    public function testVerificarPrintsTheRowsAndAnEncryptedCopyNeedsTheKey(): void
    {
        $publica = $this->clavePublicaAge();
        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias', '--sitios=genealogy')[0]);
        $plana = $this->dir . '/copias/genealogy-' . date('Y-m-d') . '.sqlite';

        [$codigo, $salida, $error] = $this->correr('--verificar=' . $plana);
        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertSame("La copia se restauró y tiene 1 tablas (0 filas):\n  t: 0\n", $salida);

        unlink($plana);
        $this->assertSame(0, $this->correr('--destino=' . $this->dir . '/copias', '--sitios=genealogy', '--cifrar-para=' . $publica)[0]);
        $cifrada = $plana . '.age';
        [$codigo, $salida, $error] = $this->correr('--verificar=' . $cifrada);
        $this->assertSame([1, ''], [$codigo, $salida]);
        $this->assertStringContainsString('--identidad', $error);

        [$codigo, $salida] = $this->correr('--verificar=' . $cifrada, '--identidad=' . $this->dir . '/origen/k.txt');
        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('  t: 0', $salida);
        $this->assertSame([basename($cifrada)], $this->copias(), 'verifying leaves no plain file in the folder');
    }

    public function testVerificarOfSomethingThatIsNotACopyFailsWithOne(): void
    {
        [$codigo, $salida, $error] = $this->correr('--verificar=' . $this->dir . '/no-existe.sqlite');

        $this->assertSame([1, ''], [$codigo, $salida]);
        $this->assertStringContainsString('No se pudo verificar:', $error);
    }
}
