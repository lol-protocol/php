<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\RectificacionRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Correcting a persona's ficha with bin/persona.php rectificar, on every available engine. */
class RectificacionRepositoryTest extends TestCase
{
    private const ELLA = 6128479601;
    private const OTRA = 6128479604;
    private const NACIMIENTO = 412979601;   // only hers
    private const MATRIMONIO = 412979602;   // two principals

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /** Ella (dead long ago, in the group García), her birth, a wedding with someone else and a pedido about each. */
    private function mundo(string $driver): Database
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $db->execute("INSERT INTO grupos (id, apellido) VALUES (582317, 'García')");
        $db->execute("INSERT INTO personas (id, nombres, apellidos, sexo, grupo_id) VALUES (?, 'Rosa Elena', 'García Soto', 'F', 582317)", [self::ELLA]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Otro', 'Esposo')", [self::OTRA]);
        $db->execute("INSERT INTO sucesos (id, tipo, fecha, descripcion) VALUES (?, 'nacimiento', '1850-03-04', 'Nació en casa')", [self::NACIMIENTO]);
        $db->execute("INSERT INTO sucesos (id, tipo, fecha) VALUES (?, 'matrimonio', '1870-06-01')", [self::MATRIMONIO]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::NACIMIENTO, self::ELLA]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::MATRIMONIO, self::ELLA]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::MATRIMONIO, self::OTRA]);
        $ahora = date('Y-m-d H:i:s');
        $db->execute("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (1, 'persona', ?, 'me_nombra', 'x', 'a@example.com', 'pendiente', ?)", [self::ELLA, $ahora]);
        $db->execute("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (2, 'persona', ?, 'otro', 'x', 'b@example.com', 'pendiente', ?)", [self::OTRA, $ahora]);

        return $db;
    }

    private static function valor(Database $db, string $sql, array $params = []): mixed
    {
        return $db->fetchValue($sql, $params);
    }

    #[DataProvider('drivers')]
    public function testWithoutConfirmingNothingIsLeftBehindButTheReportIsTheRealOne(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new RectificacionRepository($db);

        $r = $repo->rectificar(self::ELLA, 'personas.apellidos', 'García Quintana', pedido: 1);

        $this->assertSame(['campo' => 'personas.apellidos', 'aplicada' => false, 'antes_oculta' => false, 'despues_oculta' => false, 'correccion' => null, 'aviso_grupo' => null], $r);
        $this->assertSame('García Soto', self::valor($db, 'SELECT apellidos FROM personas WHERE id = ?', [self::ELLA]));
        $this->assertNull(self::valor($db, 'SELECT actualizado_en FROM personas WHERE id = ?', [self::ELLA]));
        $this->assertSame(0, (int)self::valor($db, 'SELECT COUNT(*) FROM rectificaciones_persona'));
    }

    #[DataProvider('drivers')]
    public function testConfirmingChangesOneRowStampsItAndRecordsTheFieldWithoutTheValues(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new RectificacionRepository($db);

        $r = $repo->rectificar(self::ELLA, 'personas.apellidos', 'García Quintana', pedido: 1, aplicar: true);

        $this->assertTrue($r['aplicada']);
        $this->assertSame(1, $r['correccion']);
        $this->assertSame('García Quintana', self::valor($db, 'SELECT apellidos FROM personas WHERE id = ?', [self::ELLA]));
        $this->assertNotNull(self::valor($db, 'SELECT actualizado_en FROM personas WHERE id = ?', [self::ELLA]), 'the page changed: its lastmod moves');
        $fila = $db->fetchOne('SELECT persona_id, que, pedido_id FROM rectificaciones_persona');
        $this->assertSame([self::ELLA, 'personas.apellidos', 1], [(int)$fila['persona_id'], $fila['que'], (int)$fila['pedido_id']]);
        $this->assertStringNotContainsString('Quintana', json_encode($db->fetchAll('SELECT * FROM rectificaciones_persona'), JSON_THROW_ON_ERROR));
        $this->assertSame('Esposo', self::valor($db, 'SELECT apellidos FROM personas WHERE id = ?', [self::OTRA]), 'nobody else changed');
    }

    #[DataProvider('drivers')]
    public function testAChangeOfTheSurnameThatLeavesItsGrupoIsFlagged(string $driver): void
    {
        $r = (new RectificacionRepository($this->mundo($driver)))->rectificar(self::ELLA, 'personas.apellidos', 'Pérez Soto');

        $this->assertSame('Sigue en el grupo «García», que ya no está en sus apellidos: revisa personas.grupo_id.', $r['aviso_grupo']);
    }

    #[DataProvider('drivers')]
    public function testACorrectionThatMakesHerPassForALivingPersonSaysSo(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new RectificacionRepository($db);
        $reciente = date('Y-m-d', strtotime('-20 years'));

        $simulada = $repo->rectificar(self::ELLA, 'sucesos.fecha', $reciente, suceso: self::NACIMIENTO);
        $this->assertSame([false, true], [$simulada['antes_oculta'], $simulada['despues_oculta']], 'a birth 20 years ago with no death: presumed alive, so hidden');
        $this->assertSame('1850-03-04', substr((string)self::valor($db, 'SELECT fecha FROM sucesos WHERE id = ?', [self::NACIMIENTO]), 0, 10), 'the simulation changed nothing');

        $hecha = $repo->rectificar(self::ELLA, 'sucesos.fecha', $reciente, suceso: self::NACIMIENTO, aplicar: true);
        $this->assertSame([false, true], [$hecha['antes_oculta'], $hecha['despues_oculta']]);
        $this->assertSame($reciente, substr((string)self::valor($db, 'SELECT fecha FROM sucesos WHERE id = ?', [self::NACIMIENTO]), 0, 10));
        $this->assertNotNull(self::valor($db, 'SELECT actualizado_en FROM sucesos WHERE id = ?', [self::NACIMIENTO]));
        $this->assertNull(self::valor($db, 'SELECT actualizado_en FROM personas WHERE id = ?', [self::ELLA]), 'the persona row did not change');
    }

    #[DataProvider('drivers')]
    public function testTheSexAndTheDescriptionOfHerOwnEventCanBeCorrected(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new RectificacionRepository($db);

        $repo->rectificar(self::ELLA, 'personas.sexo', 'X', aplicar: true);
        $repo->rectificar(self::ELLA, 'sucesos.descripcion', 'Nació en la casa de su abuela', suceso: self::NACIMIENTO, aplicar: true);
        $repo->rectificar(self::ELLA, 'personas.nombres', '  Rosa Elena María  ', aplicar: true);

        $this->assertSame('X', trim((string)self::valor($db, 'SELECT sexo FROM personas WHERE id = ?', [self::ELLA])));
        $this->assertSame('Nació en la casa de su abuela', self::valor($db, 'SELECT descripcion FROM sucesos WHERE id = ?', [self::NACIMIENTO]));
        $this->assertSame('Rosa Elena María', self::valor($db, 'SELECT nombres FROM personas WHERE id = ?', [self::ELLA]), 'trimmed');
        $this->assertSame(3, (int)self::valor($db, 'SELECT COUNT(*) FROM rectificaciones_persona'));
    }

    /** @return array<string, array{string, string, array<string, mixed>, string}> */
    public static function rechazos(): array
    {
        return [
            'un campo que no está en la lista' => [self::ELLA, 'personas.padre_id', ['valor' => '1'], 'no se corrige con este comando'],
            'ni una tabla con SQL' => [self::ELLA, 'personas.nombres; DROP TABLE personas', ['valor' => 'x'], 'no se corrige con este comando'],
            'una persona que no existe' => [6128479777, 'personas.nombres', ['valor' => 'X'], 'No existe la persona'],
            'un nombre vacío' => [self::ELLA, 'personas.nombres', ['valor' => '   '], 'lleva de 1 a 200'],
            'un nombre larguísimo' => [self::ELLA, 'personas.apellidos', ['valor' => str_repeat('a', 201)], 'lleva de 1 a 200'],
            'un carácter de control' => [self::ELLA, 'personas.nombres', ['valor' => "Rosa\x00"], 'caracteres de control'],
            'un salto de línea' => [self::ELLA, 'personas.nombres', ['valor' => "Rosa\nElena"], 'caracteres de control'],
            'un sexo que no es' => [self::ELLA, 'personas.sexo', ['valor' => 'Z'], 'es F, M o X'],
            'una fecha que no existe' => [self::ELLA, 'sucesos.fecha', ['valor' => '1850-02-30', 'suceso' => self::NACIMIENTO], 'AAAA-MM-DD'],
            'una fecha con otra forma' => [self::ELLA, 'sucesos.fecha', ['valor' => '4/3/1850', 'suceso' => self::NACIMIENTO], 'AAAA-MM-DD'],
            'una fecha en el futuro' => [self::ELLA, 'sucesos.fecha', ['valor' => '2999-01-01', 'suceso' => self::NACIMIENTO], 'AAAA-MM-DD'],
            'un año imposible' => [self::ELLA, 'sucesos.fecha', ['valor' => '0999-01-01', 'suceso' => self::NACIMIENTO], 'AAAA-MM-DD'],
            'el mismo valor' => [self::ELLA, 'personas.apellidos', ['valor' => 'García Soto'], 'ya tiene ese valor'],
            'un campo de suceso sin suceso' => [self::ELLA, 'sucesos.descripcion', ['valor' => 'x'], 'falta --suceso'],
            'un suceso con un campo de persona' => [self::ELLA, 'personas.nombres', ['valor' => 'X', 'suceso' => self::NACIMIENTO], '--suceso solo va con'],
            'un suceso que no existe' => [self::ELLA, 'sucesos.descripcion', ['valor' => 'x', 'suceso' => 999999999], 'No existe el suceso'],
            'un suceso de otra persona' => [self::OTRA, 'sucesos.descripcion', ['valor' => 'x', 'suceso' => self::NACIMIENTO], 'no es el sujeto'],
            'un suceso compartido' => [self::ELLA, 'sucesos.fecha', ['valor' => '1871-01-01', 'suceso' => self::MATRIMONIO], 'más de un sujeto'],
            'un pedido que no existe' => [self::ELLA, 'personas.apellidos', ['valor' => 'Nuevo', 'pedido' => 99], 'No existe el pedido de retiro 99'],
            'un pedido de otra persona' => [self::ELLA, 'personas.apellidos', ['valor' => 'Nuevo', 'pedido' => 2], 'no es sobre la persona'],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[DataProvider('rechazos')]
    public function testWhatCannotBeCorrectedIsRefusedAndNothingChanges(int $id, string $campo, array $extra, string $mensaje): void
    {
        foreach (self::drivers() as [$driver]) {
            $db = $this->mundo($driver);
            $antes = [
                $db->fetchAll('SELECT id, nombres, apellidos, sexo, grupo_id, actualizado_en FROM personas ORDER BY id'),
                $db->fetchAll('SELECT id, fecha, descripcion, actualizado_en FROM sucesos ORDER BY id'),
            ];

            // with the confirmation on: a bad pedido is only found after the UPDATE, and must still undo it
            foreach ([false, true] as $aplicar) {
                try {
                    (new RectificacionRepository($db))->rectificar($id, $campo, (string)$extra['valor'], $extra['suceso'] ?? null, $extra['pedido'] ?? null, $aplicar);
                    $this->fail("{$driver}: {$campo} must be refused");
                } catch (\DomainException $e) {
                    $this->assertStringContainsString($mensaje, $e->getMessage(), $driver);
                }
            }

            $this->assertSame($antes[0], $db->fetchAll('SELECT id, nombres, apellidos, sexo, grupo_id, actualizado_en FROM personas ORDER BY id'), "{$driver}: personas untouched");
            $this->assertSame($antes[1], $db->fetchAll('SELECT id, fecha, descripcion, actualizado_en FROM sucesos ORDER BY id'), "{$driver}: sucesos untouched");
            $this->assertSame(0, (int)$db->fetchValue('SELECT COUNT(*) FROM rectificaciones_persona'), "{$driver}: nothing recorded");
        }
    }
}
