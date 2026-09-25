<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * End to end: real index.php, router, controllers, repositories and views,
 * against a migrated + seeded SQLite database per site. Each request runs in
 * its own PHP process (see tests/Support/request.php).
 */
class PagesTest extends TestCase
{
    private const GENEALOGIA = 'tudominio.local';
    private const POS = 'contrastocolor.local';

    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/url-routing-pages-' . getmypid();
        @mkdir(self::$dir . '/sessions', 0777, true);

        foreach (['genealogy', 'pos'] as $site) {
            $file = self::$dir . "/{$site}.sqlite";
            @unlink($file);
            $migrator = Migrator::forSite(new Database("sqlite:{$file}"), $site);
            $migrator->migrate();
            $migrator->seed();
        }
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$dir));
    }

    /** @return array{int, string} [status, body] */
    private function get(string $host, string $uri, ?int $usuario = null): array
    {
        $cmd = [PHP_BINARY, '-d', 'session.save_path=' . self::$dir . '/sessions', '-d', 'display_errors=stderr',
            dirname(__DIR__, 2) . '/Support/request.php', $host, $uri];
        if ($usuario !== null) {
            $cmd[] = (string)$usuario;
        }

        $env = [
            'DB_DSN_GENEALOGY' => 'sqlite:' . self::$dir . '/genealogy.sqlite',
            'DB_DSN_POS' => 'sqlite:' . self::$dir . '/pos.sqlite',
            'LOG_FILE' => self::$dir . '/app.log',
            'PATH' => (string)getenv('PATH'),
        ];

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $body = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertMatchesRegularExpression('/STATUS:\d{3}$/', $stderr, "No status for {$uri}; stderr: {$stderr}");
        $this->assertStringNotContainsString('Warning', $stderr, "PHP warning on {$uri}: {$stderr}");
        $this->assertStringNotContainsString('Deprecated', $stderr, "PHP deprecation on {$uri}: {$stderr}");

        return [(int)substr($stderr, -3), $body];
    }

    /** @return array<string, array{string, string, int, list<string>}> */
    public static function paginas(): array
    {
        return [
            'persona' => [self::GENEALOGIA, '/6128473105/', 200, ['Juan García Hernández', '14 feb 1925', 'Elena Martínez Soto', 'Cónyuge']],
            'ascendencia' => [self::GENEALOGIA, '/6128473108/1/', 200, ['Abuelos', 'Antonio García Fernández', 'Bisabuelos', 'José García Álvarez']],
            'descendencia' => [self::GENEALOGIA, '/6128473101/2/', 200, ['Nietos', 'Juan García Hernández', 'Bisnietos', 'Lucía García Martínez']],
            'vinculos' => [self::GENEALOGIA, '/6128473106/3/', 200, ['Padrino/madrina', 'Carlos García Martínez']],
            'cronologia' => [self::GENEALOGIA, '/6128473103/4/', 200, ['Migración', 'Llega a Guadalajara desde Oviedo.']],
            'suceso' => [self::GENEALOGIA, '/412000006/', 200, ['Matrimonio', 'contrayente', 'Acta de matrimonio']],
            'registro fuente' => [self::GENEALOGIA, '/81372001/1/', 200, ['Parroquia de San José de Analco', 'foja 112']],
            'coleccion arbol' => [self::GENEALOGIA, '/1048293/1/', 200, ['class="arbol"', 'Carlos García Martínez']],
            'grupo dispersion' => [self::GENEALOGIA, '/582317/2/', 200, ['Guadalajara', '50.0']],
            'organizacion miembros' => [self::GENEALOGIA, '/10232/1/', 200, ['Elena Martínez Soto', 'feligrés']],
            'lugar' => [self::GENEALOGIA, '/mx/jal/', 200, ['Jalisco', 'Tlaquepaque']],
            'lugar personas' => [self::GENEALOGIA, '/mx/jal/tlq/1/', 200, ['Rosa García Hernández']],
            'listado personas' => [self::GENEALOGIA, '/?t=10&q=mart%C3%ADnez', 200, ['Carlos García Martínez', 'Lucía García Martínez']],
            'producto' => [self::POS, '/81372047/', 200, ['Camiseta básica', '$199.00 MXN', 'Algodón orgánico', 'Agotado']],
            'producto descontinuado' => [self::POS, '/81372050/', 200, ['descontinuado']],
            'variantes' => [self::POS, '/81372048/1/', 200, ['$749.00 MXN', '$699.00 MXN']],
            'atributos' => [self::POS, '/81372047/2/', 200, ['Color', 'Talla', 'Azul marino']],
            'atributo productos' => [self::POS, '/48215/1/', 200, ['Sudadera con capucha']],
            'coleccion' => [self::POS, '/7300001/', 200, ['Primavera 2026', 'Sudadera con capucha']],
            'grupo' => [self::POS, '/1001/', 200, ['Camisetas', 'Sudaderas', 'Camiseta básica']],
            'listado productos' => [self::POS, '/?t=8', 200, ['Gorra de seis paneles']],
        ];
    }

    #[DataProvider('paginas')]
    public function testPageRendersFromTheDatabase(string $host, string $uri, int $status, array $esperado): void
    {
        [$codigo, $body] = $this->get($host, $uri);

        $this->assertSame($status, $codigo, $body);
        foreach ($esperado as $texto) {
            $this->assertStringContainsString($texto, $body, "Missing «{$texto}» on {$uri}");
        }
    }

    /** @return array<string, array{string, string, int}> */
    public static function errores(): array
    {
        return [
            'persona inexistente' => [self::GENEALOGIA, '/6128473199/', 404],
            'accion inexistente' => [self::GENEALOGIA, '/6128473105/9/', 404],
            'lugar inexistente' => [self::GENEALOGIA, '/zz/', 404],
            'coleccion privada ajena' => [self::GENEALOGIA, '/1048294/', 404],
            'listado tipo invalido' => [self::GENEALOGIA, '/?t=99', 400],
            'cuenta sin sesion' => [self::GENEALOGIA, '/0/', 401],
            'editar sin sesion' => [self::GENEALOGIA, '/1048293/2/', 401],
            'producto inexistente' => [self::POS, '/99999999/', 404],
            'orden sin sesion' => [self::POS, '/order/8137204719000/', 401],
        ];
    }

    #[DataProvider('errores')]
    public function testErrorStatus(string $host, string $uri, int $status): void
    {
        [$codigo] = $this->get($host, $uri);

        $this->assertSame($status, $codigo);
    }

    public function testSearchTreatsPercentLiterally(): void
    {
        [, $body] = $this->get(self::POS, '/?t=8&q=%25');

        $this->assertStringContainsString('Sin productos disponibles', $body);
    }

    public function testOwnerSeesPrivateCollection(): void
    {
        [$codigo, $body] = $this->get(self::GENEALOGIA, '/1048294/', usuario: 2);

        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Borrador de Luis', $body);
    }

    public function testOnlyTheAuthorCanOpenEditar(): void
    {
        $this->assertSame(200, $this->get(self::GENEALOGIA, '/1048293/2/', usuario: 1)[0]);
        $this->assertSame(403, $this->get(self::GENEALOGIA, '/1048293/2/', usuario: 2)[0]);
    }

    public function testOrderIsVisibleOnlyToItsOwner(): void
    {
        [$codigo, $body] = $this->get(self::POS, '/order/8137204719000/', usuario: 1);
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('$647.00 MXN', $body);
        $this->assertStringContainsString('MX123456789', $this->get(self::POS, '/order/8137204719000/2/', usuario: 1)[1]);

        // Another user's order is a 404, not a 403: ids are sequential.
        $this->assertSame(404, $this->get(self::POS, '/order/8137204719000/', usuario: 2)[0]);
    }

    public function testAccountPagesAreScopedToTheUser(): void
    {
        [, $ordenes] = $this->get(self::POS, '/0/2/', usuario: 1);
        $this->assertStringContainsString('8137204719000', $ordenes);
        $this->assertStringNotContainsString('8137204719001', $ordenes);

        [, $aportes] = $this->get(self::GENEALOGIA, '/0/2/', usuario: 2);
        $this->assertStringContainsString('Carlos García Martínez', $aportes);
        $this->assertStringNotContainsString('José García Álvarez', $aportes);
    }

    public function testExportIsValidGedcom(): void
    {
        [$codigo, $gedcom] = $this->get(self::GENEALOGIA, '/1048293/3/');

        $this->assertSame(200, $codigo);
        $this->assertStringStartsWith("0 HEAD\r\n", $gedcom);
        $this->assertStringEndsWith("0 TRLR\r\n", $gedcom);
        $this->assertSame(9, substr_count($gedcom, ' INDI'));
        $this->assertStringContainsString("1 NAME Juan /García Hernández/", $gedcom);
        $this->assertStringContainsString("2 DATE 14 FEB 1925", $gedcom);

        // Every pointer used must be defined in the file.
        preg_match_all('/^\d (?:FAMS|FAMC|HUSB|WIFE|CHIL) (@\w+@)/m', $gedcom, $usados);
        preg_match_all('/^0 (@\w+@) /m', $gedcom, $definidos);
        $this->assertSame([], array_values(array_diff(array_unique($usados[1]), $definidos[1])));
    }

    public function testDatabaseErrorsDoNotLeakDetails(): void
    {
        $roto = self::$dir . '/roto.sqlite';
        file_put_contents($roto, 'this is not a database');

        $cmd = [PHP_BINARY, dirname(__DIR__, 2) . '/Support/request.php', self::GENEALOGIA, '/6128473105/'];
        $env = ['DB_DSN_GENEALOGY' => "sqlite:{$roto}", 'LOG_FILE' => self::$dir . '/app.log', 'PATH' => (string)getenv('PATH')];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $body = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertStringEndsWith('STATUS:500', $stderr);
        $this->assertStringContainsString('Error interno del servidor', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString($roto, $body);
    }
}
