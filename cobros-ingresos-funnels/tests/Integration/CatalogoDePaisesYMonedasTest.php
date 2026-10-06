<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Migrador;

/**
 * El catalogo de paises y monedas tiene que llegar con las migraciones, no solo
 * con el seed. El seed es de desarrollo (borra toda la base): una base de
 * produccion creada con `php database/migrar.php` quedaba con paises y monedas
 * vacios, y como clientes.pais_codigo es NOT NULL y apunta a paises, no se podia
 * dar de alta ni un cliente. La base de los tests ya viene seedeada y no
 * distingue de donde salio el catalogo, asi que aca se corren todas las
 * migraciones en un esquema vacio -como un despliegue nuevo- y se mira lo que
 * dejan. Es DDL dentro de la transaccion del test: se deshace sola al terminar.
 */
final class CatalogoDePaisesYMonedasTest extends IntegracionTestCase
{
    private const MIGRACION = __DIR__ . '/../../database/migraciones/005_catalogo_de_paises_y_monedas.sql';

    /** Deja la conexion apuntando a un esquema vacio con todas las migraciones del repo aplicadas. */
    private function migrarUnaBaseVacia(): void
    {
        $esquema = 'despliegue_nuevo_' . uniqid();
        $db = Database::connection();
        $db->exec("CREATE SCHEMA {$esquema}");
        $db->exec("SET LOCAL search_path TO {$esquema}");

        (new Migrador())->aplicar();
    }

    private function contar(string $tabla): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
    }

    private function tasa(string $moneda): string
    {
        $stmt = Database::connection()->prepare('SELECT tasa_a_usd FROM monedas WHERE codigo = :codigo');
        $stmt->execute([':codigo' => $moneda]);

        return (string) $stmt->fetchColumn();
    }

    public function testUnaBaseSoloMigradaYaTieneElCatalogo(): void
    {
        $this->migrarUnaBaseVacia();

        self::assertGreaterThan(0, $this->contar('paises'), 'sin paises no se puede dar de alta ningun cliente');
        self::assertGreaterThan(0, $this->contar('monedas'));
        self::assertSame('1.00000000', $this->tasa('USD'), 'los reportes consolidan en USD: monto * tasa_a_usd');
    }

    /** El sintoma real de la base vacia: el alta de un cliente chocaba con la clave foranea a paises. */
    public function testEnUnaBaseSoloMigradaSePuedeDarDeAltaUnCliente(): void
    {
        $this->migrarUnaBaseVacia();

        $filas = Database::connection()->exec(
            "INSERT INTO clientes (nombre, email, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
             VALUES ('Alta de prueba', 'alta.de.prueba@example.test', CURRENT_DATE, 'AR', 'Buenos Aires', 'Espanol', 'Femenino', '1990-01-01')"
        );

        self::assertSame(1, $filas);
    }

    /**
     * Una base que ya tiene el catalogo -la de desarrollo, o una de produccion
     * cuyas tasas se reemplazaron por las reales- no puede perder esos cambios
     * cuando la migracion se aplique sobre ella. Y lo que le falte, se completa.
     */
    public function testAplicarLaMigracionSobreUnCatalogoExistenteNoPisaLasTasasYCompletaLoQueFalte(): void
    {
        $this->migrarUnaBaseVacia();
        $paises = $this->contar('paises');
        $db = Database::connection();
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.00123456 WHERE codigo = 'ARS'");
        $db->exec("DELETE FROM paises WHERE codigo = 'UY'");

        $db->exec((string) file_get_contents(self::MIGRACION));

        self::assertSame('0.00123456', $this->tasa('ARS'), 'la tasa que alguien actualizo a mano se queda como esta');
        self::assertSame($paises, $this->contar('paises'), 'el pais que faltaba se vuelve a cargar');
    }
}
