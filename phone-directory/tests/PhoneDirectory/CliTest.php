<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;

/** Exercises bin/phonedir as a real subprocess, the way a user would run it. */
class CliTest extends TestCase
{
    private string $binary;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->binary = dirname(__DIR__, 2) . '/bin/phonedir';
        $base = tempnam(sys_get_temp_dir(), 'phonedir_cli_');
        unlink($base); // tempnam() creates the file; we only want a unique, currently-free path
        $this->dbFile = $base . '.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->dbFile);
    }

    private function runCli(string $command): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['php', $this->binary, ...explode(' ', $command)], $descriptors, $pipes);
        $this->assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }

    private function writeSampleFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phonedir_sample_') . '.txt';
        file_put_contents($path, $content);

        return $path;
    }

    public function testHelpWithNoArguments(): void
    {
        [$exitCode, $stdout] = $this->runCli('help');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('phonedir import', $stdout);
        $this->assertStringContainsString('phonedir search', $stdout);
    }

    public function testUnknownCommandFailsWithHelp(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli('bogus');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Comando desconocido', $stderr);
        $this->assertStringContainsString('Uso:', $stdout);
    }

    public function testImportMissingFileFails(): void
    {
        [$exitCode, , $stderr] = $this->runCli("import /no/such/file.txt --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('no se encontró el archivo', $stderr);
    }

    public function testImportSearchAndStats(): void
    {
        $file = $this->writeSampleFile("SMITH, John\n123 Main Street\n555-123-4567\n\nJONES, Ann\n456 Oak Avenue");

        [$importExit, $importOut] = $this->runCli("import {$file} --country=US --dsn=sqlite:{$this->dbFile}");
        $this->assertSame(0, $importExit);
        $this->assertStringContainsString('Personas insertadas: 2', $importOut);
        $this->assertStringContainsString('Errores: 0', $importOut);

        [$searchExit, $searchOut] = $this->runCli("search --name=smith --dsn=sqlite:{$this->dbFile}");
        $this->assertSame(0, $searchExit);
        $this->assertStringContainsString('Smith, John', $searchOut);
        $this->assertStringContainsString('1 resultado(s)', $searchOut);

        [$statsExit, $statsOut] = $this->runCli("stats --dsn=sqlite:{$this->dbFile}");
        $this->assertSame(0, $statsExit);
        $this->assertStringContainsString('Total: 2', $statsOut);

        unlink($file);
    }

    public function testImportFromCatalogSetsCountryAndLanguage(): void
    {
        $file = $this->writeSampleFile("GARCÍA LÓPEZ, José\nCalle Mayor 12\n555-123-4567");

        [$exitCode, $stdout] = $this->runCli("import {$file} --catalog=es_1930_madrid --dsn=sqlite:{$this->dbFile}");
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Idioma detectado: es', $stdout);

        [, $searchOut] = $this->runCli("search --surname-sound=garcia --language=es --dsn=sqlite:{$this->dbFile}");
        $this->assertStringContainsString('García López, José', $searchOut);

        unlink($file);
    }

    public function testImportUnknownCatalogIdFails(): void
    {
        $file = $this->writeSampleFile("SMITH, John\n123 Main Street");

        [$exitCode, , $stderr] = $this->runCli("import {$file} --catalog=xx_0000_nowhere --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('directorio desconocido', $stderr);

        unlink($file);
    }

    public function testLinkAcrossTwoDirectories(): void
    {
        $first = $this->writeSampleFile("GARCÍA LÓPEZ, José\nCalle Mayor 12\n555-123-4567");
        $second = $this->writeSampleFile("GARCÍA LÓPEZ, José\nAvenida Nueva 5\n555-999-8888");

        $this->runCli("import {$first} --catalog=es_1930_madrid --dsn=sqlite:{$this->dbFile}");
        $this->runCli("import {$second} --catalog=es_1975_national --dsn=sqlite:{$this->dbFile}");

        [$exitCode, $stdout] = $this->runCli("link --sources=es_1930_madrid,es_1975_national --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('1 enlace(s) propuesto(s)', $stdout);
        $this->assertStringContainsString('es_1930_madrid', $stdout);
        $this->assertStringContainsString('es_1975_national', $stdout);

        unlink($first);
        unlink($second);
    }

    public function testLinkRequiresAtLeastTwoSources(): void
    {
        [$exitCode, , $stderr] = $this->runCli("link --sources=only_one --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('al menos dos', $stderr);
    }

    public function testClearWipesExistingDataBeforeImport(): void
    {
        $file = $this->writeSampleFile("SMITH, John\n123 Main Street");

        $this->runCli("import {$file} --dsn=sqlite:{$this->dbFile}");
        $this->runCli("import {$file} --dsn=sqlite:{$this->dbFile} --clear");

        [, $statsOut] = $this->runCli("stats --dsn=sqlite:{$this->dbFile}");

        $this->assertStringContainsString('Total: 1', $statsOut);

        unlink($file);
    }

    public function testCatalogListAndShow(): void
    {
        [$listExit, $listOut] = $this->runCli('catalog list --country=ES');
        $this->assertSame(0, $listExit);
        $this->assertStringContainsString('es_1930_madrid', $listOut);

        [$showExit, $showOut] = $this->runCli('catalog show es_1930_madrid');
        $this->assertSame(0, $showExit);
        $this->assertStringContainsString('Madrid', $showOut);
    }

    public function testJuridicalEntityIsClassifiedAndSearchable(): void
    {
        $file = $this->writeSampleFile("Farmacia Central\nCalle Luna 3\n555-444-5555");

        $this->runCli("import {$file} --country=ES --language=es --dsn=sqlite:{$this->dbFile}");

        [$exitCode, $stdout] = $this->runCli("search --name=farmacia --type=juridical --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[empresa]', $stdout);
        $this->assertStringContainsString('Farmacia Central', $stdout);

        unlink($file);
    }

    // --- the visitor's view (--publico) -------------------------------------------------------------

    private function importOldAndRecent(): array
    {
        $old = $this->writeSampleFile("GARCÍA LÓPEZ, José\nCalle Mayor 12\n555-123-4567");
        $recent = $this->writeSampleFile("GARCÍA RUIZ, José\nAvenida Nueva 5\n555-999-8888");
        $this->runCli("import {$old} --catalog=es_1930_madrid --dsn=sqlite:{$this->dbFile}");
        $this->runCli("import {$recent} --catalog=es_1975_national --dsn=sqlite:{$this->dbFile}");

        return [$old, $recent];
    }

    public function testTheOwnerSeesEverythingByDefault(): void
    {
        [$old, $recent] = $this->importOldAndRecent();

        [$exitCode, $stdout] = $this->runCli("search --name=garcia --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('2 resultado(s)', $stdout);
        $this->assertStringNotContainsString('Vista pública', $stdout);

        unlink($old);
        unlink($recent);
    }

    public function testThePublicViewHidesRecentDirectoriesAndSaysSo(): void
    {
        [$old, $recent] = $this->importOldAndRecent();

        [$exitCode, $stdout] = $this->runCli("search --name=garcia --publico --hoy=2026-10-20 --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('García López, José', $stdout);
        $this->assertStringNotContainsString('García Ruiz', $stdout);
        $this->assertStringNotContainsString('555-999-8888', $stdout);
        $this->assertStringContainsString('1 resultado(s)', $stdout);
        $this->assertStringContainsString('Vista pública: solo guías hasta 1933', $stdout);

        [, $byPhone] = $this->runCli("search --phone=555-999-8888 --publico --hoy=2026-10-20 --dsn=sqlite:{$this->dbFile}");
        $this->assertStringContainsString('0 resultado(s)', $byPhone);

        unlink($old);
        unlink($recent);
    }

    public function testThePublicViewLinksNothingThatInvolvesARecentDirectory(): void
    {
        [$old, $recent] = $this->importOldAndRecent();

        [$exitCode, $stdout] = $this->runCli("link --sources=es_1930_madrid,es_1975_national --publico --hoy=2026-10-20 --dsn=sqlite:{$this->dbFile}");

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('0 enlace(s) propuesto(s)', $stdout);
        $this->assertStringContainsString('Vista pública', $stdout);

        unlink($old);
        unlink($recent);
    }

    public function testTheLimitOfThePublicViewFollowsTheReferenceDate(): void
    {
        [$old, $recent] = $this->importOldAndRecent();

        // In 2070 the 1975 directory is old enough (1975 + 92 < 2070): a visitor then sees both.
        [, $stdout] = $this->runCli("search --name=garcia --publico --hoy=2070-01-01 --dsn=sqlite:{$this->dbFile}");

        $this->assertStringContainsString('2 resultado(s)', $stdout);
        $this->assertStringContainsString('solo guías hasta 1977', $stdout);

        unlink($old);
        unlink($recent);
    }

    public function testAnInvalidReferenceDateFails(): void
    {
        foreach (['2026-13-40', 'ayer', '2026-1-1'] as $date) {
            [$exitCode, , $stderr] = $this->runCli("search --name=x --publico --hoy={$date} --dsn=sqlite:{$this->dbFile}");

            $this->assertSame(1, $exitCode, $date);
            $this->assertStringContainsString('--hoy debe ser una fecha AAAA-MM-DD', $stderr, $date);
        }
    }
}
