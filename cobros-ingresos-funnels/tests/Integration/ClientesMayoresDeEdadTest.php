<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\MayoriaDeEdad;
use App\Repositories\ClienteRepository;
use App\Repositories\RangoEdad;
use App\Validacion;
use DateTimeImmutable;
use PDOException;

/**
 * No se admiten clientes menores de edad. La app lo valida en el alta
 * (ClienteController); la base lo exige con un trigger (migracion 007) para
 * cualquier otro camino. Aca se prueba el trigger, y que MayoriaDeEdad, el
 * trigger y el primer tramo de adultos de RangoEdad coinciden en el borde: la
 * referencia es age() de Postgres, que es lo que usa la segmentacion.
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
    private function datos(string $nacimiento): array
    {
        return [
            'nombre' => 'Cliente de la regla de edad',
            'email' => 'edad-' . uniqid('', true) . '@example.com',
            'segmento' => 'general',
            'fecha_alta' => $this->hoy->format('Y-m-d'),
            'pais_codigo' => 'AR',
            'ciudad' => 'Rosario',
            'idioma' => 'Espanol',
            'genero' => 'No especifica',
            'fecha_nacimiento' => $nacimiento,
        ];
    }

    /** Intenta dar de alta a alguien nacido en $nacimiento; devuelve el SQLSTATE del rechazo, o null si entro. */
    private function intentarAlta(string $nacimiento): ?string
    {
        try {
            Database::transaccion(fn (): int => (new ClienteRepository())->crear($this->datos($nacimiento)));

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
                MayoriaDeEdad::mensaje(),
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
     * El borde, dia por dia durante un mes y medio a cada lado: la referencia es
     * age() de Postgres. La app (MayoriaDeEdad), el trigger y RangoEdad
     * ("Menor de 18") tienen que decir lo mismo de cada fecha.
     */
    public function testLaAppLaBaseYElTramoDeEdadCoincidenEnElBorde(): void
    {
        $db = Database::connection();
        $referencia = $db->prepare("SELECT date_part('year', age(CURRENT_DATE, :n::date)) >= 18");
        $tramo = $db->prepare('SELECT ' . RangoEdad::expresionSql('t.n') . ' FROM (SELECT :n::date AS n) t');

        for ($dias = -45; $dias <= 45; $dias++) {
            $nacimiento = $this->nacimientoHaceAnios(18, $dias);

            $referencia->execute([':n' => $nacimiento]);
            $esMayor = (bool) $referencia->fetchColumn();
            $tramo->execute([':n' => $nacimiento]);

            self::assertSame($esMayor, MayoriaDeEdad::cumplida($nacimiento, $this->hoy), "MayoriaDeEdad con {$nacimiento}");
            self::assertSame($esMayor, $this->intentarAlta($nacimiento) === null, "el trigger con {$nacimiento}");
            self::assertSame($esMayor, $tramo->fetchColumn() !== 'Menor de 18', "RangoEdad con {$nacimiento}");
        }
    }
}
