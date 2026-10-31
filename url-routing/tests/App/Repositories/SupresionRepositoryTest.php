<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\SupresionRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/** Answering a person who asks what is held about them, and erasing it, on every available engine. */
class SupresionRepositoryTest extends TestCase
{
    private const ELLA = 6128479601;      // the persona who is erased
    private const HIJO = 6128479602;      // her child
    private const CONYUGE = 6128479603;   // her spouse
    private const OTRA = 6128479604;      // someone else, with her own pedido de retiro

    private const NACIMIENTO = 412979601;     // only hers
    private const MATRIMONIO = 412979602;     // shared with her spouse
    private const REGISTRO = 97960101;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    /**
     * Ella, with a child, a spouse and everything that can hang from a persona: an event of her own (with a record
     * about it), one shared with the spouse, a link, a colección, an organización, an id that redirects to her and
     * two pedidos de retiro (one about her, one about someone else).
     */
    private function mundo(string $driver): Database
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $db->execute("INSERT INTO usuarios (id, email, nombre) VALUES (1, 'u@example.com', 'U')");
        $db->execute("INSERT INTO lugares (ruta, padre_ruta, codigo, nombre, nivel) VALUES ('mx', NULL, 'mx', 'México', 1)");
        $db->execute("INSERT INTO personas (id, nombres, apellidos, sexo) VALUES (?, 'Rosa Elena', 'Quintana Soto', 'F')", [self::ELLA]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos, padre_id, madre_id) VALUES (?, 'Hijo', 'Prueba', NULL, ?)", [self::HIJO, self::ELLA]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Cónyuge', 'Prueba')", [self::CONYUGE]);
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Otra', 'Persona')", [self::OTRA]);

        $db->execute("INSERT INTO sucesos (id, tipo, fecha, lugar_ruta, descripcion) VALUES (?, 'nacimiento', '1850-03-04', 'mx', 'Nació en casa')", [self::NACIMIENTO]);
        $db->execute("INSERT INTO sucesos (id, tipo, fecha, lugar_ruta, descripcion) VALUES (?, 'matrimonio', '1870-06-01', 'mx', NULL)", [self::MATRIMONIO]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::NACIMIENTO, self::ELLA]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::MATRIMONIO, self::ELLA]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::MATRIMONIO, self::CONYUGE]);

        $db->execute("INSERT INTO registros (id, titulo, tipo, fecha, fuente) VALUES (?, 'Acta de bautismo', 'acta', '1850-03-10', 'Parroquia')", [self::REGISTRO]);
        $db->execute('INSERT INTO registro_sucesos (registro_id, suceso_id) VALUES (?, ?)', [self::REGISTRO, self::NACIMIENTO]);

        $db->execute("INSERT INTO vinculos (persona_a, persona_b, tipo) VALUES (?, ?, 'conyuge')", [self::ELLA, self::CONYUGE]);
        $db->execute("INSERT INTO colecciones (id, nombre, usuario_id, publica) VALUES (1000999, 'Familia', 1, TRUE)");
        $db->execute('INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES (1000999, ?)', [self::ELLA]);
        $db->execute("INSERT INTO organizaciones (id, nombre, tipo) VALUES (10999, 'Parroquia', 'parroquia')");
        $db->execute("INSERT INTO organizacion_miembros (organizacion_id, persona_id, rol) VALUES (10999, ?, 'feligresa')", [self::ELLA]);
        $db->execute('INSERT INTO persona_alias (id, persona_id) VALUES (6128479699, ?)', [self::ELLA]);

        $ahora = date('Y-m-d H:i:s');
        $db->execute("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (1, 'persona', ?, 'me_nombra', 'Soy yo', 'ella@example.com', 'oculto', ?)", [self::ELLA, $ahora]);
        $db->execute("INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en) VALUES (2, 'persona', ?, 'otro', 'Otra', 'otra@example.com', 'pendiente', ?)", [self::OTRA, $ahora]);

        return $db;
    }

    private static function cuantas(Database $db, string $sql, array $params = []): int
    {
        return (int)$db->fetchValue($sql, $params);
    }

    // --- acceso ----------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testAccessListsWhatIsHeldAndNothingOfHerRelativesBeyondCounts(string $driver): void
    {
        $acceso = (new SupresionRepository($this->mundo($driver)))->acceso(self::ELLA);

        $this->assertSame('Rosa Elena', $acceso['persona']['nombres']);
        $this->assertArrayNotHasKey('padre_id', $acceso['persona'], 'a relative is a count, not an id');
        $this->assertSame(['padre' => false, 'madre' => false, 'hijos' => 1, 'otros_vinculos' => 1], $acceso['familiares']);
        $this->assertEqualsCanonicalizing([self::NACIMIENTO, self::MATRIMONIO], array_map('intval', array_column($acceso['sucesos'], 'id')));
        $this->assertSame([self::REGISTRO], array_map('intval', array_column($acceso['registros'], 'id')));
        $this->assertSame([['id' => 1000999, 'nombre' => 'Familia', 'publica' => true]], $acceso['colecciones']);
        $this->assertSame('feligresa', $acceso['organizaciones'][0]['rol']);
        $this->assertSame([6128479699], $acceso['ids_que_redirigen_a_ella']);

        $json = json_encode($acceso, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Cónyuge', $json, 'the spouse\'s name is the spouse\'s data');
        $this->assertStringNotContainsString('Hijo', $json);
        $this->assertStringNotContainsString('ella@example.com', $json, 'who asked for a retiro is the requester\'s data');
        $this->assertStringNotContainsString('Soy yo', $json);
        $this->assertSame([1], array_map('intval', array_column($acceso['pedidos_de_retiro'], 'id')), 'but that there is one is hers to know');
    }

    #[DataProvider('drivers')]
    public function testAccessToAMissingPersonaIsNull(string $driver): void
    {
        $this->assertNull((new SupresionRepository($this->mundo($driver)))->acceso(6128479777));
    }

    // --- suprimir ---------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testErasingRemovesHerEverywhereAndKeepsWhatIsNotHers(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new SupresionRepository($db);
        $previsto = $repo->resumen(self::ELLA);

        $hecho = $repo->suprimir(self::ELLA, 1);

        $this->assertSame($previsto, $hecho, 'what the dry run said is what was done');
        $this->assertSame(
            ['sucesos_que_se_borran' => 1, 'sucesos_compartidos_que_se_conservan' => 1, 'enlaces_a_registros_que_se_quitan' => 1,
                'vinculos' => 1, 'hijos_que_pierden_el_enlace' => 1, 'organizacion_miembros' => 1, 'coleccion_personas' => 1, 'persona_alias' => 1],
            $hecho
        );
        $this->assertNull($db->fetchOne('SELECT id FROM personas WHERE id = ?', [self::ELLA]), 'her row is gone');
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM suceso_participantes WHERE persona_id = ?', [self::ELLA]));
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM sucesos WHERE id = ?', [self::NACIMIENTO]), 'the event only she was in is gone');
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM registro_sucesos WHERE suceso_id = ?', [self::NACIMIENTO]));
        $this->assertSame(1, self::cuantas($db, 'SELECT COUNT(*) FROM registros WHERE id = ?', [self::REGISTRO]), 'the record stays: it is a document, a person reads its text');
        $this->assertSame(1, self::cuantas($db, 'SELECT COUNT(*) FROM sucesos WHERE id = ?', [self::MATRIMONIO]), 'the shared event stays');
        $this->assertSame(
            [self::CONYUGE],
            array_map('intval', array_column($db->fetchAll('SELECT persona_id FROM suceso_participantes WHERE suceso_id = ?', [self::MATRIMONIO]), 'persona_id')),
            'with her taken off'
        );
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM vinculos'));
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM coleccion_personas'));
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM organizacion_miembros'));
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM persona_alias'));

        $hijo = $db->fetchOne('SELECT padre_id, madre_id FROM personas WHERE id = ?', [self::HIJO]);
        $this->assertNotNull($hijo, 'her child keeps their row');
        $this->assertNull($hijo['madre_id'], 'and loses the link to her');
        $this->assertNotNull($db->fetchOne('SELECT id FROM personas WHERE id = ?', [self::CONYUGE]));
        $this->assertNotNull($db->fetchOne('SELECT id FROM personas WHERE id = ?', [self::OTRA]));
    }

    #[DataProvider('drivers')]
    public function testTheRecordOfAnErasureHasNoNameOnIt(string $driver): void
    {
        $db = $this->mundo($driver);
        (new SupresionRepository($db))->suprimir(self::ELLA, 1);

        $fila = $db->fetchOne('SELECT * FROM supresiones_persona');

        $this->assertSame(self::ELLA, (int)$fila['id']);
        $this->assertSame(1, (int)$fila['pedido_id']);
        $this->assertNotEmpty($fila['suprimida_en']);
        $this->assertStringNotContainsString('Rosa', json_encode($fila, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Quintana', json_encode($fila, JSON_THROW_ON_ERROR));
        $this->assertSame(1, json_decode((string)$fila['resumen'], true, flags: JSON_THROW_ON_ERROR)['sucesos_que_se_borran']);
    }

    #[DataProvider('drivers')]
    public function testErasingAPersonaThatDoesNotExistFails(string $driver): void
    {
        $this->expectException(\DomainException::class);

        (new SupresionRepository($this->mundo($driver)))->suprimir(6128479777);
    }

    /** A pedido about someone else, or one that does not exist, is refused before anything is touched. */
    #[DataProvider('drivers')]
    public function testAWrongPedidoStopsEverythingBeforeTheFirstDelete(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new SupresionRepository($db);

        foreach ([2, 99] as $pedido) {
            try {
                $repo->suprimir(self::ELLA, $pedido);
                $this->fail("pedido {$pedido} should have been refused");
            } catch (\DomainException) {
                $this->assertNotNull($db->fetchOne('SELECT id FROM personas WHERE id = ?', [self::ELLA]), "pedido {$pedido}: she is still there");
                $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM supresiones_persona'), "pedido {$pedido}: nothing recorded");
                $this->assertSame(3, self::cuantas($db, 'SELECT COUNT(*) FROM suceso_participantes'), "pedido {$pedido}: nothing deleted");
            }
        }
    }

    #[DataProvider('drivers')]
    public function testErasingWorksWithoutAPedido(string $driver): void
    {
        $db = $this->mundo($driver);

        (new SupresionRepository($db))->suprimir(self::ELLA);

        $this->assertNull($db->fetchValue('SELECT pedido_id FROM supresiones_persona WHERE id = ?', [self::ELLA]));
    }

    // --- menciones --------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testMentionsFindTheNameInFreeTextIgnoringCaseAndAccents(string $driver): void
    {
        $db = $this->mundo($driver);
        $db->execute("UPDATE sucesos SET descripcion = 'Padrino: rosa ELENA quintana soto' WHERE id = ?", [self::MATRIMONIO]);
        $db->execute("UPDATE registros SET titulo = 'Bautizo de Rosa Quintana' WHERE id = ?", [self::REGISTRO]);
        $db->execute("UPDATE colecciones SET descripcion = 'Los Quintana de Oaxaca' WHERE id = 1000999");
        $repo = new SupresionRepository($db);

        $menciones = $repo->menciones('Rosa Elena', 'Quintana Soto');

        $this->assertSame([self::MATRIMONIO], $menciones['sucesos.descripcion']['ids'], 'the whole name, in other case');
        $this->assertSame([self::REGISTRO], $menciones['registros.titulo']['ids'], 'first given name and first surname');
        $this->assertArrayNotHasKey('colecciones.descripcion', $menciones, 'a surname alone is not a mention of her');
        $this->assertArrayNotHasKey('sucesos.id', $menciones);
    }

    #[DataProvider('drivers')]
    public function testMentionsOfAShortNameLookForNothingInsteadOfEverything(string $driver): void
    {
        $this->assertSame([], (new SupresionRepository($this->mundo($driver)))->menciones('', ''));
    }

    // --- reaplicar --------------------------------------------------------------------

    #[DataProvider('drivers')]
    public function testErasingAgainAfterRestoringABackup(string $driver): void
    {
        $db = $this->mundo($driver);
        $repo = new SupresionRepository($db);
        $this->assertSame([], $repo->volvieron(), 'nobody has been erased yet');
        $repo->suprimir(self::ELLA);
        $this->assertSame([], $repo->volvieron(), 'and she is not back');

        // The backup brings her back, with an event and a membership.
        $db->execute("INSERT INTO personas (id, nombres, apellidos) VALUES (?, 'Rosa Elena', 'Quintana Soto')", [self::ELLA]);
        $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [self::MATRIMONIO, self::ELLA]);
        $db->execute('INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES (1000999, ?)', [self::ELLA]);
        $this->assertSame([self::ELLA], $repo->volvieron());

        $this->assertSame([self::ELLA], $repo->reaplicar());

        $this->assertNull($db->fetchOne('SELECT id FROM personas WHERE id = ?', [self::ELLA]));
        $this->assertSame(0, self::cuantas($db, 'SELECT COUNT(*) FROM coleccion_personas'));
        $this->assertSame([], $repo->volvieron());
        $this->assertSame(1, self::cuantas($db, 'SELECT COUNT(*) FROM supresiones_persona'), 'one record, not two');
        $this->assertSame([], $repo->reaplicar(), 'nothing left to do');
    }
}
