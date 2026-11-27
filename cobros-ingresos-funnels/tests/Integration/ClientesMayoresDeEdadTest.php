<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\ClienteMenorDeEdad;
use App\Database;
use App\MayoriaDeEdad;
use App\Repositories\ClienteRepository;
use App\Repositories\PaisRepository;
use App\RangoEdad;
use App\Validacion;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * No se admiten clientes menores de edad, con la edad de mayoria de SU pais (paises.mayoria_de_edad, migracion 010). Desde la
 * migracion 021 la base no guarda la fecha de nacimiento: la regla por pais la exige ClienteRepository::crear con la fecha
 * exacta (ClienteMenorDeEdad), y la base conserva lo que puede ver, el tramo: un cliente no entra ni pasa a «Menor de 18»
 * (trigger exigir_cliente_adulto). Aca se prueba lo uno y lo otro, y que MayoriaDeEdad, el repositorio y RangoEdad coinciden en
 * el borde, con cada edad que usa algun pais: la referencia es age() de Postgres.
 *
 * Un INSERT que la base rechaza deja abortada la transaccion del test; por eso los intentos que pueden fallar van dentro de
 * Database::transaccion(), que los anida con un SAVEPOINT y deshace solo lo suyo.
 */
final class ClientesMayoresDeEdadTest extends IntegracionTestCase
{
    private DateTimeImmutable $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoy = new DateTimeImmutable('today');
    }

    /** @return array<string, string> */
    private function datos(string $nacimiento, string $pais = 'AR'): array
    {
        return [
            'nombre' => 'Cliente de la regla de edad',
            'email' => 'edad-' . uniqid('', true) . '@example.com',
            'segmento' => 'general',
            'fecha_alta' => $this->hoy->format('Y-m-d'),
            'pais_codigo' => $pais,
            'ciudad' => 'Rosario',
            'idioma' => 'Espanol',
            'genero' => 'No especifica',
            'fecha_nacimiento' => $nacimiento,
        ];
    }

    /** Intenta dar de alta en $pais a alguien nacido en $nacimiento; devuelve false si el repositorio lo rechaza por menor. */
    private function intentarAlta(string $nacimiento, string $pais = 'AR'): bool
    {
        try {
            Database::transaccion(fn (): int => (new ClienteRepository())->crear($this->datos($nacimiento, $pais)));

            return true;
        } catch (ClienteMenorDeEdad) {
            return false;
        }
    }

    private function nacimientoHaceAnios(int $anios, int $masDias = 0): string
    {
        return $this->hoy->modify("-{$anios} years")->modify(sprintf('%+d days', $masDias))->format('Y-m-d');
    }

    /** El SQLSTATE con que la base rechaza un INSERT/UPDATE de $sql, o null si lo acepta. */
    private function sqlstateDe(string $sql, array $parametros = []): ?string
    {
        try {
            Database::transaccion(fn (): bool => Database::connection()->prepare($sql)->execute($parametros));

            return null;
        } catch (PDOException $e) {
            return (string) $e->getCode();
        }
    }

    public function testElRepositorioRechazaLaAltaDeUnMenorDeEdad(): void
    {
        self::assertFalse($this->intentarAlta($this->nacimientoHaceAnios(17)));
        self::assertFalse($this->intentarAlta($this->hoy->format('Y-m-d')), 'nacio hoy');
        self::assertFalse($this->intentarAlta($this->hoy->modify('+1 day')->format('Y-m-d')), 'nacera manana: no es una edad');
    }

    public function testElRepositorioAceptaLaAltaDeUnMayorDeEdad(): void
    {
        self::assertTrue($this->intentarAlta($this->nacimientoHaceAnios(19)));
        self::assertTrue($this->intentarAlta(MayoriaDeEdad::nacimientoMasReciente($this->hoy)), 'cumple 18 hoy');
    }

    public function testElMensajeDelRechazoEsElDeLaApp(): void
    {
        try {
            (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(16)));
            self::fail('el repositorio tenia que rechazar al menor');
        } catch (ClienteMenorDeEdad $e) {
            self::assertSame(MayoriaDeEdad::mensaje(18, 'Argentina'), $e->getMessage());
        }
    }

    /** El punto de la migracion 021: la fecha exacta se usa y se descarta; en la base queda solo el tramo. */
    public function testLaFechaDeNacimientoNoSeGuardaYQuedaElTramo(): void
    {
        $db = Database::connection();
        $columnas = $db->query(
            "SELECT table_name FROM information_schema.columns WHERE column_name = 'fecha_nacimiento' AND table_schema = current_schema()"
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([], $columnas, 'ninguna tabla guarda la fecha de nacimiento');

        $nacimiento = $this->nacimientoHaceAnios(30);
        $id = (new ClienteRepository())->crear($this->datos($nacimiento));
        $cliente = (new ClienteRepository())->porId($id);

        self::assertNotNull($cliente);
        self::assertSame(RangoEdad::deFecha($nacimiento, $this->hoy), $cliente['rango_de_edad']);
        self::assertSame('25-34', $cliente['rango_de_edad']);
        self::assertArrayNotHasKey('fecha_nacimiento', $cliente);
        self::assertStringNotContainsString($nacimiento, (string) json_encode($cliente), 'la fecha no aparece en ningun campo');
    }

    public function testLaBaseRechazaUnClienteConTramoDeMenorOInexistente(): void
    {
        $sql = "INSERT INTO clientes (nombre, email, fecha_alta, pais_codigo, ciudad, idioma, genero, rango_de_edad)
                VALUES ('Directo', :email, CURRENT_DATE, 'AR', 'Rosario', 'Espanol', 'No especifica', :rango)";

        foreach (['Menor de 18', 'Fecha inválida'] as $rango) {
            $sqlstate = $this->sqlstateDe($sql, [':email' => uniqid('d', true) . '@example.com', ':rango' => $rango]);
            self::assertSame('23514', $sqlstate, "«{$rango}»: el trigger de adultos");
        }
        self::assertSame('23514', $this->sqlstateDe($sql, [':email' => uniqid('d', true) . '@example.com', ':rango' => '30-40']), 'un tramo que no existe: el CHECK');
        self::assertNull($this->sqlstateDe($sql, [':email' => uniqid('d', true) . '@example.com', ':rango' => '65+']));
    }

    public function testElMensajeDeLaBaseEsElDeLaApp(): void
    {
        try {
            Database::connection()->exec(
                "INSERT INTO clientes (nombre, email, fecha_alta, pais_codigo, ciudad, idioma, genero, rango_de_edad)
                 VALUES ('Directo', 'directo-" . uniqid() . "@example.com', CURRENT_DATE, 'AR', 'Rosario', 'Espanol', 'No especifica', 'Menor de 18')"
            );
            self::fail('la base tenia que rechazar al menor');
        } catch (PDOException $e) {
            self::assertStringContainsString('mayores de edad', $e->getMessage());
            self::assertSame(
                MayoriaDeEdad::mensajeGeneral(),
                Validacion::mensajeDeConflicto($e, 'cliente'),
                'si una alta se salta la validacion del controller, el usuario igual ve el motivo'
            );
        }
    }

    public function testCambiarElTramoDeUnClienteAlDeUnMenorSeRechaza(): void
    {
        $id = (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(30)));

        $sqlstate = $this->sqlstateDe("UPDATE clientes SET rango_de_edad = 'Menor de 18' WHERE id = :id", [':id' => $id]);

        self::assertSame('23514', $sqlstate);
        self::assertSame('25-34', (new ClienteRepository())->porId($id)['rango_de_edad'] ?? null, 'el tramo quedo como estaba');
    }

    /**
     * Un cliente anterior a la regla que hoy esta en «Menor de 18» (la base de produccion puede tenerlos) no se puede crear de
     * nuevo, pero tampoco queda congelado: se le puede corregir la ciudad o el email mientras nadie le cambie el tramo. Se simula
     * quitando el trigger de altas solo dentro de la transaccion del test.
     */
    public function testUnClienteAnteriorALaReglaSigueSiendoEditable(): void
    {
        $db = Database::connection();
        $db->exec('ALTER TABLE clientes DISABLE TRIGGER clientes_adulto_alta');
        $db->exec(
            "INSERT INTO clientes (nombre, email, fecha_alta, pais_codigo, ciudad, idioma, genero, rango_de_edad)
             VALUES ('Anterior', 'anterior-" . uniqid() . "@example.com', CURRENT_DATE, 'AR', 'Rosario', 'Espanol', 'No especifica', 'Menor de 18')"
        );
        $id = (int) $db->lastInsertId('clientes_id_seq');
        $db->exec('ALTER TABLE clientes ENABLE TRIGGER clientes_adulto_alta');

        $db->prepare("UPDATE clientes SET ciudad = 'Cordoba' WHERE id = :id")->execute([':id' => $id]);
        $db->prepare("UPDATE clientes SET rango_de_edad = 'Menor de 18' WHERE id = :id")->execute([':id' => $id]);

        $ciudad = $db->prepare('SELECT ciudad FROM clientes WHERE id = :id');
        $ciudad->execute([':id' => $id]);
        self::assertSame('Cordoba', $ciudad->fetchColumn(), 'se pudo editar otra columna, y reponer el mismo tramo');

        self::assertSame('23514', $this->sqlstateDe("UPDATE clientes SET rango_de_edad = 'Fecha inválida' WHERE id = :id", [':id' => $id]));
        self::assertNull($this->sqlstateDe("UPDATE clientes SET rango_de_edad = '18-24' WHERE id = :id", [':id' => $id]), 'pasar a adulto si se puede');
    }

    /**
     * El borde, dia por dia durante un mes y medio a cada lado, con cada edad de mayoria que use algun pais del catalogo (18, y
     * las que sube la migracion 010): la referencia es age() de Postgres. MayoriaDeEdad y el repositorio tienen que decir lo mismo
     * de cada fecha; con 18, ademas el tramo que calcula RangoEdad ("Menor de 18").
     */
    public function testLaAppElRepositorioYElTramoDeEdadCoincidenEnElBorde(): void
    {
        $db = Database::connection();
        $porEdad = $db->query('SELECT mayoria_de_edad, MIN(codigo) FROM paises GROUP BY mayoria_de_edad ORDER BY 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertContains(18, array_keys($porEdad));
        self::assertGreaterThan(1, count($porEdad), 'el catalogo tiene que traer paises con otra edad (migracion 010)');

        $referencia = $db->prepare("SELECT date_part('year', age(CURRENT_DATE, :n::date)) >= :edad");

        foreach ($porEdad as $edad => $pais) {
            $edad = (int) $edad;
            for ($dias = -45; $dias <= 45; $dias++) {
                $nacimiento = $this->nacimientoHaceAnios($edad, $dias);

                $referencia->execute([':n' => $nacimiento, ':edad' => $edad]);
                $esMayor = (bool) $referencia->fetchColumn();

                self::assertSame($esMayor, MayoriaDeEdad::cumplida($nacimiento, $this->hoy, $edad), "MayoriaDeEdad con {$nacimiento} y {$edad} anios ({$pais})");
                self::assertSame($esMayor, $this->intentarAlta($nacimiento, (string) $pais), "el repositorio con {$nacimiento} en {$pais} ({$edad} anios)");
                if ($edad === MayoriaDeEdad::POR_DEFECTO) {
                    self::assertSame($esMayor, RangoEdad::deFecha($nacimiento, $this->hoy) !== RangoEdad::MENOR, "RangoEdad con {$nacimiento}");
                }
            }
        }
    }

    public function testLaMismaFechaDeNacimientoEsDeUnMayorEnUnPaisYDeUnMenorEnOtro(): void
    {
        $nacimiento = $this->nacimientoHaceAnios(19, -30);   // 19 anios y un mes, mas o menos

        self::assertTrue($this->intentarAlta($nacimiento, 'AR'), '18 en Argentina');
        self::assertFalse($this->intentarAlta($nacimiento, 'TH'), '20 en Tailandia');
        self::assertFalse($this->intentarAlta($nacimiento, 'SG'), '21 en Singapur');
        self::assertTrue($this->intentarAlta($nacimiento, 'CA'), '19 en Canada: ya los cumplio');
    }

    public function testElMensajeDelRechazoNombraLaEdadYElPais(): void
    {
        try {
            (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(19), 'TH'));
            self::fail('el repositorio tenia que rechazar al de 19 en Tailandia');
        } catch (ClienteMenorDeEdad $e) {
            self::assertSame(MayoriaDeEdad::mensaje(20, 'Tailandia'), $e->getMessage());
        }
    }

    /** La edad sale de la tabla, no esta escrita en el codigo: cambiarla en paises cambia lo que se exige, sin migracion. */
    public function testElRepositorioLeeLaEdadDeLaTabla(): void
    {
        $db = Database::connection();
        $nacimiento = $this->nacimientoHaceAnios(19);
        self::assertTrue($this->intentarAlta($nacimiento, 'AR'));

        $db->exec("UPDATE paises SET mayoria_de_edad = 21 WHERE codigo = 'AR'");
        self::assertFalse($this->intentarAlta($nacimiento, 'AR'), 'con 21 en Argentina, el de 19 ya no entra');
        self::assertTrue($this->intentarAlta($this->nacimientoHaceAnios(21), 'AR'));

        $db->exec("UPDATE paises SET mayoria_de_edad = 16 WHERE codigo = 'AR'");

        // Con 16 el repositorio ya no lo rechaza por menor... pero el tramo seria «Menor de 18» y la base no admite clientes ahi (migracion 021).
        try {
            $this->intentarAlta($this->nacimientoHaceAnios(16), 'AR');
            self::fail('la base tenia que rechazar el tramo «Menor de 18»');
        } catch (PDOException $e) {
            self::assertSame('23514', (string) $e->getCode());
        }
        self::assertTrue($this->intentarAlta($this->nacimientoHaceAnios(18), 'AR'));
        self::assertFalse($this->intentarAlta($this->nacimientoHaceAnios(15), 'AR'));
    }

    public function testLaEdadDeUnPaisTieneQueEstarEntreDieciseisYVeinticinco(): void
    {
        $db = Database::connection();
        $cambiar = $db->prepare('UPDATE paises SET mayoria_de_edad = :edad WHERE codigo = :pais');

        foreach ([15, 26, 0, 99, -1] as $edad) {
            try {
                Database::transaccion(fn (): bool => $cambiar->execute([':edad' => $edad, ':pais' => 'AR']));
                self::fail("la base tenia que rechazar {$edad}");
            } catch (PDOException $e) {
                self::assertSame('23514', (string) $e->getCode(), (string) $edad);
            }
        }
        foreach ([16, 25] as $edad) {
            self::assertTrue($cambiar->execute([':edad' => $edad, ':pais' => 'AR']), (string) $edad);
        }

        // NOT NULL: un pais sin edad no existe, y el trigger no tiene que adivinarla.
        try {
            Database::transaccion(fn (): bool => $db->exec("UPDATE paises SET mayoria_de_edad = NULL WHERE codigo = 'AR'") !== false);
            self::fail('la base tenia que rechazar NULL');
        } catch (PDOException $e) {
            self::assertSame('23502', (string) $e->getCode(), 'not_null_violation');
        }
    }

    /** Un pais nuevo, sin decir nada, queda con la edad general. */
    public function testUnPaisNuevoQuedaConLaEdadGeneral(): void
    {
        $db = Database::connection();
        $db->exec("INSERT INTO paises (codigo, nombre, moneda_codigo) VALUES ('ZZ', 'Pais de prueba', 'USD')");

        self::assertSame(MayoriaDeEdad::POR_DEFECTO, (int) $db->query("SELECT mayoria_de_edad FROM paises WHERE codigo = 'ZZ'")->fetchColumn());
    }

    /** Las excepciones que carga la migracion 010; el resto del catalogo queda en la edad general. */
    public function testElCatalogoTraeLasEdadesDeLaMigracion(): void
    {
        $edades = Database::connection()->query('SELECT codigo, mayoria_de_edad FROM paises')->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach (['KR' => 19, 'DZ' => 19, 'CA' => 19, 'TH' => 20, 'SG' => 21, 'EG' => 21, 'AE' => 21, 'KW' => 21, 'BH' => 21, 'HN' => 21, 'AR' => 18, 'MX' => 18, 'ES' => 18] as $pais => $esperada) {
            self::assertSame($esperada, (int) $edades[$pais], $pais);
        }
    }
}
