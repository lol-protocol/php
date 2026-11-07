<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A persona id that was merged into another (persona_alias, bin/fusionar.php) leads to the same page of the
 * persona it became: every page of the persona answers 301 for the old id, and the old id of a persona that
 * never existed is a 404. The Location itself isn't observable here (see Support/request.php): what pins
 * the destination is Tests\App\Routing\ActionCodeTest (the code each page asks the router for) and the
 * 200 of the pages the alias points at.
 */
class PersonaAliasTest extends TestCase
{
    private const HOST = 'tudominio.local';
    private const TOKEN = 'token-de-prueba-0123456789';
    private const VIEJO = '6128479799';
    private const VIGENTE = '6128473105';

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-persona-alias-' . getmypid();
        @mkdir(self::$dir, 0777, true);
        $db = new Database('sqlite:' . self::$dir . '/genealogy.sqlite');
        $migrator = Migrator::forSite($db, 'genealogy');
        $migrator->migrate();
        $migrator->seed();
        $db->execute('INSERT INTO persona_alias (id, persona_id) VALUES (?, ?)', [self::VIEJO, self::VIGENTE]);
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$dir));
    }

    private function estado(string $uri, bool $propietario = false): int
    {
        $autorizacion = $propietario ? 'Basic ' . base64_encode('propietario:' . self::TOKEN) : '';
        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/request.php',
            self::HOST, $uri, 'GET', '', '', $autorizacion];
        $env = [
            'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
            'LOG_FILE' => self::$dir . '/app.log',
            'OWNER_TOKEN' => self::TOKEN,
            'PATH' => (string)getenv('PATH'),
        ];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertMatchesRegularExpression('/STATUS:\d{3}$/', $stderr, "No status for {$uri}; stderr: {$stderr}");
        $this->assertStringNotContainsString('Warning', $stderr, $stderr);

        return (int)substr($stderr, -3);
    }

    /** @return array<string, array{string}> */
    public static function paginasPublicas(): array
    {
        return [
            'la persona' => [''],
            'ascendencia' => ['1/'],
            'descendencia' => ['2/'],
            'vínculos' => ['3/'],
            'cronología' => ['4/'],
        ];
    }

    #[DataProvider('paginasPublicas')]
    public function testElIdViejoLlevaALaMismaPaginaDeLaPersonaVigente(string $pagina): void
    {
        $this->assertSame(200, $this->estado('/' . self::VIGENTE . '/' . $pagina), 'the page the alias leads to exists');
        $this->assertSame(301, $this->estado('/' . self::VIEJO . '/' . $pagina), 'the old id redirects');
    }

    public function testLaPaginaDelPropietarioTambienSigueAlAlias(): void
    {
        $this->assertSame(200, $this->estado('/' . self::VIGENTE . '/5/', propietario: true));
        $this->assertSame(301, $this->estado('/' . self::VIEJO . '/5/', propietario: true));
        $this->assertSame(401, $this->estado('/' . self::VIEJO . '/5/'), 'a visitor is asked for the credentials first, as for the live persona');
    }

    public function testUnIdQueNuncaExistioSigueSiendo404(): void
    {
        $this->assertSame(404, $this->estado('/6128479798/'));
        $this->assertSame(404, $this->estado('/6128479798/1/'));
    }
}
