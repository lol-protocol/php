<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\MayoriaDeEdad;
use App\Repositories\ClienteRepository;
use App\Repositories\RangoEdad;
use App\Validacion;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * No se admiten clientes menores de edad, con la edad de mayoria de SU pais
 * (paises.mayoria_de_edad, migracion 010). La app lo valida en el alta
 * (ClientesController); la base lo exige con un trigger (migraciones 007 y 010)
 * para cualquier otro camino. Aca se prueba el trigger, y que MayoriaDeEdad, el
 * trigger y -para los 18- el primer tramo de adultos de RangoEdad coinciden en el
 * borde, con cada edad que usa algun pais: la referencia es age() de Postgres,
 * que es lo que usa la segmentacion.
 *
 * Un INSERT que la base rechaza deja abortada la transaccion del test; por eso
 * los intentos que pueden fallar van dentro de Database::transaccion(), que los
 * anida con un SAVEPOINT y deshace solo lo suyo.
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

    /** Intenta dar de alta en $pais a alguien nacido en $nacimiento; devuelve el SQLSTATE del rechazo, o null si entro. */
    private function intentarAlta(string $nacimiento, string $pais = 'AR'): ?string
    {
        try {
            Database::transaccion(fn (): int => (new ClienteRepository())->crear($this->datos($nacimiento, $pais)));

            return null;
        } catch (PDOException $e) {
            return (string) $e->getCode();
        }
    }

    private function nacimientoHaceAnios(int $anios, int $masDias = 0): string
    {
        return $this->hoy->modify("-{$anios} years")->modify(sprintf('%+d days', $masDias))->format('Y-m-d');
    }

    public function testLaBaseRechazaLaAltaDeUnMenorDeEdad(): void
    {
        self::assertSame('23514', $this->intentarAlta($this->nacimientoHaceAnios(17)), 'check_violation');
        self::assertSame('23514', $this->intentarAlta($this->hoy->format('Y-m-d')), 'nacio hoy');
    }

    public function testLaBaseAceptaLaAltaDeUnMayorDeEdad(): void
    {
        self::assertNull($this->intentarAlta($this->nacimientoHaceAnios(19)));
        self::assertNull($this->intentarAlta(MayoriaDeEdad::nacimientoMasReciente($this->hoy)), 'cumple 18 hoy');
    }

    public function testElMensajeDeLaBaseEsElDeLaApp(): void
    {
        try {
            (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(16)));
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

    public function testCambiarLaFechaDeNacimientoDeUnClienteAUnaDeMenorSeRechaza(): void
    {
        $id = (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(30)));
        $db = Database::connection();
        $cambiar = $db->prepare('UPDATE clientes SET fecha_nacimiento = :nacimiento WHERE id = :id');

        try {
            Database::transaccion(fn (): bool => $cambiar->execute([':nacimiento' => $this->nacimientoHaceAnios(16), ':id' => $id]));
            self::fail('la base tenia que rechazar el cambio');
        } catch (PDOException $e) {
            self::assertSame('23514', (string) $e->getCode());
        }

        $nacimiento = $db->prepare('SELECT fecha_nacimiento FROM clientes WHERE id = :id');
        $nacimiento->execute([':id' => $id]);
        self::assertSame($this->nacimientoHaceAnios(30), $nacimiento->fetchColumn(), 'la fecha quedo como estaba');
    }

    /**
     * Un cliente anterior a la regla que hoy tiene menos de 18 (la base de
     * produccion puede tenerlos) no se puede crear de nuevo -ese es el trigger de
     * altas, que aca se apaga solo dentro de la transaccion del test para
     * simularlo-, pero tampoco queda congelado: se le puede corregir la ciudad
     * o el email mientras nadie le cambie la fecha de nacimiento.
     */
    public function testUnClienteAnteriorALaReglaSigueSiendoEditable(): void
    {
        $db = Database::connection();
        $db->exec('ALTER TABLE clientes DISABLE TRIGGER clientes_mayor_de_edad_alta');
        $nacimiento = $this->nacimientoHaceAnios(15);
        $id = (new ClienteRepository())->crear($this->datos($nacimiento));
        $db->exec('ALTER TABLE clientes ENABLE TRIGGER clientes_mayor_de_edad_alta');

        $db->prepare("UPDATE clientes SET ciudad = 'Cordoba' WHERE id = :id")->execute([':id' => $id]);
        $db->prepare('UPDATE clientes SET fecha_nacimiento = :n WHERE id = :id')->execute([':n' => $nacimiento, ':id' => $id]);

        $ciudad = $db->prepare('SELECT ciudad FROM clientes WHERE id = :id');
        $ciudad->execute([':id' => $id]);
        self::assertSame('Cordoba', $ciudad->fetchColumn(), 'se pudo editar otra columna, y reponer la misma fecha');

        try {
            Database::transaccion(fn (): bool => $db->prepare('UPDATE clientes SET fecha_nacimiento = :n WHERE id = :id')
                ->execute([':n' => $this->nacimientoHaceAnios(14), ':id' => $id]));
            self::fail('cambiarle la fecha a otra de menor sigue estando prohibido');
        } catch (PDOException $e) {
            self::assertSame('23514', (string) $e->getCode());
        }
    }

    /**
     * El borde, dia por dia durante un mes y medio a cada lado, con cada edad de
     * mayoria que use algun pais del catalogo (18, y las que sube la migracion
     * 010): la referencia es age() de Postgres. La app (MayoriaDeEdad) y el trigger
     * tienen que decir lo mismo de cada fecha; con 18, ademas RangoEdad
     * ("Menor de 18").
     */
    public function testLaAppLaBaseYElTramoDeEdadCoincidenEnElBorde(): void
    {
        $db = Database::connection();
        $porEdad = $db->query('SELECT mayoria_de_edad, MIN(codigo) FROM paises GROUP BY mayoria_de_edad ORDER BY 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertContains(18, array_keys($porEdad));
        self::assertGreaterThan(1, count($porEdad), 'el catalogo tiene que traer paises con otra edad (migracion 010)');

        $referencia = $db->prepare("SELECT date_part('year', age(CURRENT_DATE, :n::date)) >= :edad");
        $tramo = $db->prepare('SELECT ' . RangoEdad::expresionSql('t.n') . ' FROM (SELECT :n::date AS n) t');

        foreach ($porEdad as $edad => $pais) {
            $edad = (int) $edad;
            for ($dias = -45; $dias <= 45; $dias++) {
                $nacimiento = $this->nacimientoHaceAnios($edad, $dias);

                $referencia->execute([':n' => $nacimiento, ':edad' => $edad]);
                $esMayor = (bool) $referencia->fetchColumn();

                self::assertSame($esMayor, MayoriaDeEdad::cumplida($nacimiento, $this->hoy, $edad), "MayoriaDeEdad con {$nacimiento} y {$edad} anios ({$pais})");
                self::assertSame($esMayor, $this->intentarAlta($nacimiento, (string) $pais) === null, "el trigger con {$nacimiento} en {$pais} ({$edad} anios)");
                if ($edad === MayoriaDeEdad::POR_DEFECTO) {
                    $tramo->execute([':n' => $nacimiento]);
                    self::assertSame($esMayor, $tramo->fetchColumn() !== 'Menor de 18', "RangoEdad con {$nacimiento}");
                }
            }
        }
    }

    public function testLaMismaFechaDeNacimientoEsDeUnMayorEnUnPaisYDeUnMenorEnOtro(): void
    {
        $nacimiento = $this->nacimientoHaceAnios(19, -30);   // 19 anios y un mes, mas o menos

        self::assertNull($this->intentarAlta($nacimiento, 'AR'), '18 en Argentina');
        self::assertSame('23514', $this->intentarAlta($nacimiento, 'TH'), '20 en Tailandia');
        self::assertSame('23514', $this->intentarAlta($nacimiento, 'SG'), '21 en Singapur');
        self::assertNull($this->intentarAlta($nacimiento, 'CA'), '19 en Canada: ya los cumplio');
    }

    public function testElMensajeDeLaBaseNombraLaEdadYElPais(): void
    {
        try {
            (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(19), 'TH'));
            self::fail('la base tenia que rechazar al de 19 en Tailandia');
        } catch (PDOException $e) {
            self::assertStringContainsString('20 anios cumplidos en TH', $e->getMessage());
        }
    }

    /** La edad sale de la tabla, no esta escrita en el trigger: cambiarla en paises cambia lo que se exige, sin migracion. */
    public function testElTriggerLeeLaEdadDeLaTabla(): void
    {
        $db = Database::connection();
        $nacimiento = $this->nacimientoHaceAnios(19);
        self::assertNull($this->intentarAlta($nacimiento, 'AR'));

        $db->exec("UPDATE paises SET mayoria_de_edad = 21 WHERE codigo = 'AR'");
        self::assertSame('23514', $this->intentarAlta($nacimiento, 'AR'), 'con 21 en Argentina, el de 19 ya no entra');
        self::assertNull($this->intentarAlta($this->nacimientoHaceAnios(21), 'AR'));

        $db->exec("UPDATE paises SET mayoria_de_edad = 16 WHERE codigo = 'AR'");
        self::assertNull($this->intentarAlta($this->nacimientoHaceAnios(16), 'AR'), 'con 16, el de 16 cumplidos entra');
        self::assertSame('23514', $this->intentarAlta($this->nacimientoHaceAnios(15), 'AR'));
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

    public function testMudarAUnClienteAUnPaisConMasEdadSeRechazaSiTodaviaNoLaTiene(): void
    {
        $db = Database::connection();
        $id = (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(19), 'AR'));
        $mudar = $db->prepare('UPDATE clientes SET pais_codigo = :pais WHERE id = :id');

        try {
            Database::transaccion(fn (): bool => $mudar->execute([':pais' => 'TH', ':id' => $id]));
            self::fail('a los 19 no se lo puede pasar a Tailandia (20)');
        } catch (PDOException $e) {
            self::assertSame('23514', (string) $e->getCode());
            self::assertStringContainsString('mayores de edad', $e->getMessage());
        }

        // A un pais que le pide lo mismo o menos, si.
        self::assertTrue($mudar->execute([':pais' => 'CA', ':id' => $id]));
        self::assertTrue($mudar->execute([':pais' => 'MX', ':id' => $id]));

        $pais = $db->prepare('SELECT pais_codigo FROM clientes WHERE id = :id');
        $pais->execute([':id' => $id]);
        self::assertSame('MX', $pais->fetchColumn());
    }

    /** Un cliente anterior a la regla sigue siendo editable en lo demas, y tambien puede conservar su pais. */
    public function testUnClienteAnteriorALaReglaPuedeRepetirSuPais(): void
    {
        $db = Database::connection();
        $db->exec('ALTER TABLE clientes DISABLE TRIGGER clientes_mayor_de_edad_alta');
        $id = (new ClienteRepository())->crear($this->datos($this->nacimientoHaceAnios(15), 'AR'));
        $db->exec('ALTER TABLE clientes ENABLE TRIGGER clientes_mayor_de_edad_alta');

        $db->prepare("UPDATE clientes SET pais_codigo = 'AR', ciudad = 'Salta' WHERE id = :id")->execute([':id' => $id]);

        $ciudad = $db->prepare('SELECT ciudad FROM clientes WHERE id = :id');
        $ciudad->execute([':id' => $id]);
        self::assertSame('Salta', $ciudad->fetchColumn());
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
