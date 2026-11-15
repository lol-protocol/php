<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Migrador;

/**
 * El catalogo de paises y monedas tiene que llegar con las migraciones, no solo
 * con el seed. El seed es de desarrollo (borra toda la base): una base de
 * produccion creada con `php bin/migrar.php` quedaba con paises y monedas
 * vacios, y como clientes.pais_codigo es NOT NULL y apunta a paises, no se podia
 * dar de alta ni un cliente. La base de los tests ya viene seedeada y no
 * distingue de donde salio el catalogo, asi que aca se corren todas las
 * migraciones en un esquema vacio -como un despliegue nuevo- y se mira lo que
 * dejan. Es DDL dentro de la transaccion del test: se deshace sola al terminar.
 */
final class CatalogoDePaisesYMonedasTest extends IntegracionTestCase
{
    private const MIGRACION = __DIR__ . '/../../database/migraciones/005_catalogo_de_paises_y_monedas.sql';
    private const MIGRACION_014 = __DIR__ . '/../../database/migraciones/014_correcciones_del_catalogo_de_monedas.sql';

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

    private function monedaDelPais(string $pais): string
    {
        $stmt = Database::connection()->prepare('SELECT moneda_codigo FROM paises WHERE codigo = :codigo');
        $stmt->execute([':codigo' => $pais]);

        return (string) $stmt->fetchColumn();
    }

    /** Zimbabue y Bulgaria cambiaron de moneda; las viejas se quedan para las boletas que ya las usan. */
    public function testZimbabueUsaElZigYBulgariaElEuro(): void
    {
        $this->migrarUnaBaseVacia();

        self::assertSame('ZWG', $this->monedaDelPais('ZW'));
        self::assertSame('EUR', $this->monedaDelPais('BG'));
        self::assertNotSame('', $this->tasa('ZWL'), 'ZWL sigue en el catalogo: las boletas viejas la usan');
        self::assertNotSame('', $this->tasa('BGN'), 'BGN sigue en el catalogo: las boletas viejas la usan');
    }

    /** Liechtenstein era el unico miembro de la ONU que faltaba; los territorios traen las monedas que no tenian pais. */
    public function testLasMonedasQueFaltabanTienenAlgunPaisQueLasUse(): void
    {
        $this->migrarUnaBaseVacia();

        self::assertSame('CHF', $this->monedaDelPais('LI'));
        $sinPais = Database::connection()->query(
            'SELECT codigo FROM monedas m WHERE NOT EXISTS (SELECT 1 FROM paises p WHERE p.moneda_codigo = m.codigo)
              AND codigo NOT IN (\'ZWL\', \'BGN\') ORDER BY codigo'
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([], $sinPais, 'toda moneda del catalogo (salvo las dos retiradas) la usa algun pais');
        foreach (['AWG' => 'AW', 'BMD' => 'BM', 'FKP' => 'FK', 'GIP' => 'GI', 'KYD' => 'KY', 'SHP' => 'SH', 'XCG' => 'CW', 'XPF' => 'PF'] as $moneda => $pais) {
            self::assertSame($moneda, $this->monedaDelPais($pais));
        }
    }

    /**
     * Lo que se puede comprobar del catalogo sin la lista oficial (ISO 4217 y 3166-1 no se pueden bajar desde
     * donde corren las pruebas): la forma de cada codigo, que nada se repita y que las monedas retiradas
     * apunten a la que las reemplaza. La comparacion con la lista vigente sigue siendo manual (ver el README).
     */
    public function testElCatalogoTieneLaFormaDeIsoYNadaRepetido(): void
    {
        $this->migrarUnaBaseVacia();
        $db = Database::connection();

        $paises = $db->query('SELECT codigo, nombre, moneda_codigo FROM paises ORDER BY codigo')->fetchAll(\PDO::FETCH_ASSOC);
        $monedas = $db->query('SELECT codigo, nombre, decimales, reemplazada_por FROM monedas ORDER BY codigo')->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($paises as $pais) {
            self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $pais['codigo'], 'pais ' . $pais['nombre']);
            self::assertMatchesRegularExpression('/^[A-Z]{3}$/', $pais['moneda_codigo'], 'moneda de ' . $pais['nombre']);
        }
        foreach ($monedas as $moneda) {
            self::assertMatchesRegularExpression('/^[A-Z]{3}$/', $moneda['codigo'], 'moneda ' . $moneda['nombre']);
            self::assertContains((int) $moneda['decimales'], [0, 2, 3], $moneda['codigo'] . ': ISO 4217 solo conoce 0, 2 y 3 decimales para estas monedas');
        }
        $nombresDePais = array_map('mb_strtolower', array_column($paises, 'nombre'));
        self::assertSame(array_values(array_unique($nombresDePais)), $nombresDePais, 'un pais no esta dos veces con otro codigo');
        $nombresDeMoneda = array_map('mb_strtolower', array_column($monedas, 'nombre'));
        self::assertSame(array_values(array_unique($nombresDeMoneda)), $nombresDeMoneda, 'una moneda no esta dos veces con otro codigo');

        $reemplazadas = array_column(array_filter($monedas, static fn (array $m): bool => $m['reemplazada_por'] !== null), 'reemplazada_por', 'codigo');
        self::assertSame(['BGN' => 'EUR', 'ZWL' => 'ZWG'], $reemplazadas, 'las monedas retiradas apuntan a su reemplazo');
        foreach ($reemplazadas as $retirada => $reemplazo) {
            self::assertNotContains($retirada, array_column($paises, 'moneda_codigo'), "ningun pais usa ya {$retirada}");
        }
    }

    /** Las monedas ligadas por ley a otra salen del par fijo, no de un numero suelto. */
    public function testLasTasasDeLasMonedasLigadasSalenDelParFijo(): void
    {
        $this->migrarUnaBaseVacia();

        self::assertSame($this->tasa('GBP'), $this->tasa('FKP'));
        self::assertSame($this->tasa('GBP'), $this->tasa('GIP'));
        self::assertSame($this->tasa('GBP'), $this->tasa('SHP'));
        self::assertEqualsWithDelta((float) $this->tasa('EUR') / 119.33174, (float) $this->tasa('XPF'), 1e-8);
        self::assertSame('1.00000000', $this->tasa('BMD'));
    }

    /** Los decimales de ISO 4217: 0 para el yen y los francos CFA, 3 para los dinares y el rial omani, 2 para el resto. */
    public function testLosDecimalesDeCadaMonedaSonLosDeIso(): void
    {
        $this->migrarUnaBaseVacia();

        $porDecimales = [];
        foreach (Database::connection()->query('SELECT codigo, decimales FROM monedas ORDER BY codigo')->fetchAll() as $fila) {
            $porDecimales[(int) $fila['decimales']][] = $fila['codigo'];
        }
        self::assertSame(['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], $porDecimales[0]);
        self::assertSame(['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'], $porDecimales[3]);
        self::assertContains('USD', $porDecimales[2]);
    }

    /**
     * La 014 corre sobre una base que ya tiene el catalogo: no pisa una tasa cambiada a mano ni deja
     * a Zimbabue y a Bulgaria con la moneda vieja, y correrla dos veces es lo mismo que una.
     */
    public function testLa014SobreUnaBaseQueSoloLlegoHastaLa013PasaALaMonedaNuevaYNoPisaNada(): void
    {
        $this->migrarUnaBaseVacia();
        $db = Database::connection();
        $db->exec("UPDATE paises SET moneda_codigo = 'ZWL' WHERE codigo = 'ZW'");
        $db->exec("UPDATE paises SET moneda_codigo = 'BGN' WHERE codigo = 'BG'");
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.04567 WHERE codigo = 'ZWG'");
        $db->exec("DELETE FROM paises WHERE codigo = 'LI'");
        $paises = $this->contar('paises') + 1;

        $db->exec((string) file_get_contents(self::MIGRACION_014));
        $db->exec((string) file_get_contents(self::MIGRACION_014));

        self::assertSame('ZWG', $this->monedaDelPais('ZW'));
        self::assertSame('EUR', $this->monedaDelPais('BG'));
        self::assertSame('0.04567000', $this->tasa('ZWG'), 'la tasa que alguien actualizo a mano se queda como esta');
        self::assertSame($paises, $this->contar('paises'), 'el pais que faltaba se vuelve a cargar, una sola vez');
    }

    /** Un cliente de Zimbabue ya cargado conserva su pais; su moneda de facturacion pasa a ser la nueva. */
    public function testUnClienteDeZimbabueFacturaEnLaMonedaNueva(): void
    {
        $this->migrarUnaBaseVacia();
        $db = Database::connection();
        $db->exec(
            "INSERT INTO clientes (nombre, email, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
             VALUES ('Cliente de Harare', 'harare@example.test', CURRENT_DATE, 'ZW', 'Harare', 'Ingles', 'Femenino', '1990-01-01')"
        );

        $moneda = $db->query("SELECT p.moneda_codigo FROM clientes c JOIN paises p ON p.codigo = c.pais_codigo WHERE c.email = 'harare@example.test'")->fetchColumn();

        self::assertSame('ZWG', $moneda);
    }
}
