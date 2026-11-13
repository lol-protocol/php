<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;
use Tests\Support\ComandoDePrueba;

/** bin/persona.php as the owner runs it: a real subprocess, a real SQLite file, and its exit code. */
class PersonaCliTest extends TestCase
{
    use ComandoDePrueba;

    private const ELLA = 6128479601;
    private const HIJO = 6128479602;

    private string $archivo;
    private Database $db;

    protected function setUp(): void
    {
        $this->archivo = tempnam(sys_get_temp_dir(), 'persona_cli_') . '.sqlite';
        $this->db = new Database('sqlite:' . $this->archivo);
        (new Migrator($this->db, dirname(__DIR__, 3) . '/database/genealogy', 'genealogy'))->migrate();

        $this->db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Rosa Elena', 'Quintana Soto')", [self::ELLA]);
        $this->db->execute("INSERT INTO personas (id, nombres, apellidos, madre_id) VALUES (?, 'Hijo', 'Prueba', ?)", [self::HIJO, self::ELLA]);
        $this->db->execute("INSERT INTO sucesos (id, tipo, fecha, descripcion) VALUES (412979601, 'nacimiento', '1850-03-04', 'Hija de Rosa Elena Quintana')");
        $this->db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (412979601, ?, 'principal')", [self::ELLA]);
        $this->db->execute("INSERT INTO registros (id, titulo, tipo, fecha, fuente) VALUES (97960101, 'Acta de bautismo de Rosa Elena Quintana', 'acta', '1850-03-10', 'Parroquia')");
        $this->db->execute(
            "INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (7, 'persona', ?, 'me_nombra', 'Soy yo', 'ella@example.com', 'pendiente', ?)",
            [self::ELLA, date('Y-m-d H:i:s')]
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->archivo, $this->archivo . '-wal', $this->archivo . '-shm'] as $f) {
            @unlink($f);
        }
        @unlink(substr($this->archivo, 0, -7));
    }

    /** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr */
    private function correr(string ...$args): array
    {
        return $this->ejecutarComando('persona', $args, ['DB_DSN_GENEALOGY' => 'sqlite:' . $this->archivo]);
    }

    private function existe(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM personas WHERE id = ?', [$id]) !== null;
    }

    public function testAccessPrintsWhatIsHeldAsJsonAndChangesNothing(): void
    {
        [$codigo, $salida, $error] = $this->correr('acceso', (string)self::ELLA);

        $this->assertSame([0, ''], [$codigo, $error]);
        $datos = json_decode($salida, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Rosa Elena', $datos['persona']['nombres']);
        $this->assertSame(1, $datos['familiares']['hijos']);
        $this->assertSame(412979601, (int)$datos['sucesos'][0]['id']);
        $this->assertSame([7], array_map('intval', array_column($datos['pedidos_de_retiro'], 'id')));
        $this->assertArrayHasKey('generado_en', $datos);
        $this->assertStringNotContainsString('ella@example.com', $salida, 'who asked for a retiro is the requester\'s data');
        $this->assertStringContainsString('Rosa Elena', $salida, 'unicode and accents are printed as they are');
        $this->assertTrue($this->existe(self::ELLA));
    }

    public function testWithoutConfirmarItOnlyShowsWhatItWouldRemove(): void
    {
        [$codigo, $salida] = $this->correr('suprimir', (string)self::ELLA);

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('Persona ' . self::ELLA . ': Rosa Elena Quintana Soto', $salida);
        $this->assertStringContainsString('sucesos_que_se_borran: 1', $salida);
        $this->assertStringContainsString('hijos_que_pierden_el_enlace: 1', $salida);
        $this->assertStringContainsString('sucesos.descripcion: 1 (ids 412979601)', $salida, 'the free text that names her, to read by hand');
        $this->assertStringContainsString('registros.titulo: 1 (ids 97960101)', $salida);
        $this->assertStringContainsString('Nada cambió', $salida);
        $this->assertTrue($this->existe(self::ELLA));
        $this->assertSame(0, (int)$this->db->fetchValue('SELECT COUNT(*) FROM supresiones_persona'));
    }

    public function testConfirmarErasesHerRecordsItAndClosesThePedido(): void
    {
        [$codigo, $salida, $error] = $this->correr('suprimir', (string)self::ELLA, '--pedido=7', '--confirmar');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertStringContainsString('Suprimida', $salida);
        $this->assertStringContainsString('El pedido 7 quedó como «retirado»', $salida);
        $this->assertStringContainsString('php bin/persona.php reaplicar --confirmar', $salida, 'how to redo it after restoring a backup');
        $this->assertFalse($this->existe(self::ELLA));
        $this->assertTrue($this->existe(self::HIJO));
        $this->assertSame(7, (int)$this->db->fetchValue('SELECT pedido_id FROM supresiones_persona WHERE id = ?', [self::ELLA]));

        $pedido = $this->db->fetchOne('SELECT estado FROM pedidos_retiro WHERE id = 7');
        $this->assertSame('retirado', $pedido['estado']);
        $evento = $this->db->fetchOne("SELECT estado, nota FROM pedido_retiro_eventos WHERE pedido_id = 7 AND estado = 'retirado'");
        $this->assertStringContainsString('Datos de la persona suprimidos el ' . date('Y-m-d'), (string)$evento['nota']);
        $this->assertStringContainsString('registros.titulo: 1 (ids 97960101)', $salida, 'the record that still names her is listed: it stays');
        $this->assertStringNotContainsString('sucesos.descripcion', $salida, 'the event that named her was only hers and went with her');
    }

    public function testAPersonaThatDoesNotExistExitsWithOne(): void
    {
        foreach ([['acceso', '6128479777'], ['suprimir', '6128479777', '--confirmar']] as $args) {
            [$codigo, $salida, $error] = $this->correr(...$args);

            $this->assertSame(1, $codigo, implode(' ', $args));
            $this->assertSame('', $salida);
            $this->assertStringContainsString('No existe la persona 6128479777', $error);
        }
    }

    public function testAPedidoAboutSomeoneElseIsRefusedAndNothingChanges(): void
    {
        $this->db->execute("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (8, 'suceso', 412979601, 'otro', 'x', 'x@example.com', 'pendiente', ?)", [date('Y-m-d H:i:s')]);

        [$codigo, , $error] = $this->correr('suprimir', (string)self::ELLA, '--pedido=8', '--confirmar');

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('El pedido de retiro 8 no es sobre la persona', $error);
        $this->assertTrue($this->existe(self::ELLA));
    }

    public function testReaplicarRemovesAgainWhoCameBackWithABackup(): void
    {
        $this->assertStringContainsString('no hay nada que volver a quitar', $this->correr('reaplicar')[1]);
        $this->correr('suprimir', (string)self::ELLA, '--confirmar');
        $this->db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Rosa Elena', 'Quintana Soto')", [self::ELLA]);

        [$codigo, $salida] = $this->correr('reaplicar');
        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('volvió con la copia de seguridad: ' . self::ELLA, $salida);
        $this->assertStringContainsString('Nada cambió', $salida);
        $this->assertTrue($this->existe(self::ELLA), 'a dry run changes nothing');

        [$codigo, $salida] = $this->correr('reaplicar', '--confirmar');
        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('Quitadas otra vez', $salida);
        $this->assertFalse($this->existe(self::ELLA));
    }

    public function testRefusesWhatItDoesNotUnderstand(): void
    {
        $casos = [
            [], ['hola'], ['acceso'], ['acceso', 'abc'], ['acceso', '1', '2'], ['acceso', '1', '--confirmar'],
            ['suprimir'], ['suprimir', '1', '--pedido=x'], ['suprimir', '1', '--borrar'], ['reaplicar', '5'], ['reaplicar', '--pedido=1'],
        ];
        foreach ($casos as $args) {
            [$codigo, $salida, $error] = $this->correr(...$args);

            $this->assertSame(2, $codigo, implode(' ', $args));
            $this->assertSame('', $salida, implode(' ', $args));
            $this->assertStringContainsString('Uso: php bin/persona.php', $error, implode(' ', $args));
        }
        $this->assertTrue($this->existe(self::ELLA));
    }

    public function testSaysSoWhenTheDatabaseIsNotMigrated(): void
    {
        $vacia = tempnam(sys_get_temp_dir(), 'persona_vacia_') . '.sqlite';
        try {
            $proceso = proc_open(
                [PHP_BINARY, dirname(__DIR__, 3) . '/bin/persona.php', 'acceso', (string)self::ELLA],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                ['PATH' => (string)getenv('PATH'), 'DB_DSN_GENEALOGY' => 'sqlite:' . $vacia]
            );
            $this->assertIsResource($proceso);
            $salida = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $this->assertSame(2, proc_close($proceso));
            $this->assertSame('', $salida);
            $this->assertStringContainsString('bin/migrate.php genealogy', (string)$error);
        } finally {
            foreach ([$vacia, $vacia . '-wal', $vacia . '-shm', substr($vacia, 0, -7)] as $f) {
                @unlink($f);
            }
        }
    }

    public function testRectificacionRecordsTheFieldAndSaysNoValueWasKept(): void
    {
        [$codigo, $salida, $error] = $this->correr('rectificacion', (string)self::ELLA, 'personas.apellidos', '--pedido=7');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertStringContainsString('Anotada la corrección 1: personas.apellidos de la persona ' . self::ELLA, $salida);
        $fila = $this->db->fetchOne('SELECT persona_id, que, pedido_id FROM rectificaciones_persona');
        $this->assertSame([self::ELLA, 'personas.apellidos', 7], [(int)$fila['persona_id'], $fila['que'], (int)$fila['pedido_id']]);
        $this->assertTrue($this->existe(self::ELLA), 'recording a correction changes nothing else');
        $this->assertStringContainsString('personas.apellidos', $this->correr('acceso', (string)self::ELLA)[1]);
    }

    public function testRectificacionNeedsAnIdAndAField(): void
    {
        foreach ([['rectificacion'], ['rectificacion', (string)self::ELLA], ['rectificacion', 'personas.apellidos'],
            ['rectificacion', (string)self::ELLA, 'personas.apellidos', '--confirmar'], ['acceso', (string)self::ELLA, 'personas.apellidos']] as $args) {
            $this->assertSame(2, $this->correr(...$args)[0], implode(' ', $args));
        }
        $this->assertSame(0, (int)$this->db->fetchValue('SELECT COUNT(*) FROM rectificaciones_persona'));
    }

    public function testRectificacionOfAnUnknownPersonaOrFieldExitsWithOne(): void
    {
        foreach ([['6128479777', 'personas.apellidos'], [(string)self::ELLA, 'apellidos']] as $args) {
            [$codigo, $salida, $error] = $this->correr('rectificacion', ...$args);
            $this->assertSame([1, ''], [$codigo, $salida], implode(' ', $args));
            $this->assertNotSame('', $error);
        }
    }

    public function testRectificarWithoutConfirmarShowsWhatWouldHappenAndChangesNothing(): void
    {
        [$codigo, $salida, $error] = $this->correr('rectificar', (string)self::ELLA, 'personas.apellidos=Quintana Pérez');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertStringContainsString('Se corregiría personas.apellidos de la persona ' . self::ELLA, $salida);
        $this->assertStringContainsString('No cambia lo que ve un visitante', $salida);
        $this->assertStringContainsString('Nada cambió', $salida);
        $this->assertSame('Quintana Soto', $this->db->fetchValue('SELECT apellidos FROM personas WHERE id = ?', [self::ELLA]), 'the setUp surname');
        $this->assertSame(0, (int)$this->db->fetchValue('SELECT COUNT(*) FROM rectificaciones_persona'));
    }

    public function testRectificarConfirmarChangesRecordsAndSaysWhoSeesHer(): void
    {
        [$codigo, $salida, $error] = $this->correr('rectificar', (string)self::ELLA, 'sucesos.fecha=' . date('Y-m-d', strtotime('-20 years')), '--suceso=412979601', '--pedido=7', '--confirmar');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertStringContainsString('Corregido sucesos.fecha de la persona ' . self::ELLA . '. Anotada la corrección 1 (sin el valor).', $salida);
        $this->assertStringContainsString('CAMBIA lo que ve un visitante: la ve visible y ahora oculta', $salida);
        $this->assertStringContainsString('Mira la página como visitante y como propietario.', $salida);
        $this->assertStringNotContainsString(date('Y', strtotime('-20 years')), $salida, 'the value is not echoed');
        $fila = $this->db->fetchOne('SELECT persona_id, que, pedido_id FROM rectificaciones_persona');
        $this->assertSame([self::ELLA, 'sucesos.fecha', 7], [(int)$fila['persona_id'], $fila['que'], (int)$fila['pedido_id']]);
    }

    public function testRectificarValueWithEqualsSignsAndSpacesArrivesWhole(): void
    {
        $this->assertSame(0, $this->correr('rectificar', (string)self::ELLA, 'personas.nombres=Rosa = Elena', '--confirmar')[0]);

        $this->assertSame('Rosa = Elena', $this->db->fetchValue('SELECT nombres FROM personas WHERE id = ?', [self::ELLA]));
    }

    public function testRectificarErrorsExitWithOneOrTwo(): void
    {
        foreach ([
            ['6128479777', 'personas.nombres=X'],
            [(string)self::ELLA, 'personas.padre_id=1'],
            [(string)self::ELLA, 'sucesos.fecha=1850-01-01'],
            [(string)self::ELLA, 'personas.sexo=Z'],
            [(string)self::ELLA, 'personas.nombres=X', '--pedido=99'],
        ] as $args) {
            [$codigo, $salida, $error] = $this->correr('rectificar', ...$args, ...['--confirmar']);
            $this->assertSame([1, ''], [$codigo, $salida], implode(' ', $args));
            $this->assertNotSame('', $error);
        }
        foreach ([['rectificar'], ['rectificar', (string)self::ELLA], ['rectificar', 'personas.nombres=X'], ['rectificar', (string)self::ELLA, 'sin-igual'], ['rectificar', (string)self::ELLA, 'a=b', '--suceso=x']] as $args) {
            $this->assertSame(2, $this->correr(...$args)[0], implode(' ', $args));
        }
        $this->assertSame(0, (int)$this->db->fetchValue('SELECT COUNT(*) FROM rectificaciones_persona'));
        $this->assertSame('Rosa Elena', $this->db->fetchValue('SELECT nombres FROM personas WHERE id = ?', [self::ELLA]));
    }

    public function testEntregaPrintsALinkAndAKeyThatOpenWhatTheyPromise(): void
    {
        [$codigo, $salida, $error] = $this->correr('entrega', (string)self::ELLA, '--dias=3');

        $this->assertSame([0, ''], [$codigo, $error]);
        $this->assertMatchesRegularExpression('#https://TU-DOMINIO/e/([0-9a-f]{64})/#', $salida);
        $this->assertMatchesRegularExpression('/\b[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}\b/', $salida);
        $this->assertStringContainsString('OTRO medio', $salida);
        $this->assertStringContainsString('comprobar quién pide', $salida);
        preg_match('#/e/([0-9a-f]{64})/#', $salida, $token);
        preg_match('/\b([A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4})\b/', $salida, $clave);
        $this->assertSame(self::ELLA, (new \App\Repositories\Genealogy\EntregaRepository($this->db))->abrir($token[1], $clave[1]), 'what it printed opens it');
        $guardado = json_encode($this->db->fetchAll('SELECT * FROM entregas_persona'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token[1], $guardado);
        $this->assertStringNotContainsString($clave[1], $guardado);
    }

    public function testEntregaRevocarEndsThemAndEntregaErrorsExitWithOneOrTwo(): void
    {
        $this->correr('entrega', (string)self::ELLA);
        $this->correr('entrega', (string)self::ELLA);

        $this->assertStringContainsString('Se terminaron 2 entregas vigentes', $this->correr('entrega', (string)self::ELLA, '--revocar')[1]);
        $this->assertStringContainsString('no tenía entregas vigentes', $this->correr('entrega', (string)self::ELLA, '--revocar')[1]);
        [$codigo, $salida, $error] = $this->correr('entrega', '6128479777');
        $this->assertSame([1, ''], [$codigo, $salida]);
        $this->assertStringContainsString('No existe la persona', $error);
        foreach ([['entrega'], ['entrega', (string)self::ELLA, '--confirmar'], ['entrega', (string)self::ELLA, '--dias=2', '--revocar'], ['acceso', (string)self::ELLA, '--revocar'], ['entrega', (string)self::ELLA, '--dias=x']] as $args) {
            $this->assertSame(2, $this->correr(...$args)[0], implode(' ', $args));
        }
        $this->assertSame(2, (int)$this->db->fetchValue('SELECT COUNT(*) FROM entregas_persona'), 'only the two of before; the rest changed nothing');
    }
}
