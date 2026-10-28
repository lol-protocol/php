<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EscritorCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EscritorCsvTest extends TestCase
{
    /**
     * Lo que escribe el escritor para esas filas, como texto.
     *
     * @param list<string|int|float|null> ...$filas
     */
    private static function escribir(array ...$filas): string
    {
        $salida = fopen('php://memory', 'w+');
        self::assertNotFalse($salida);
        $csv = new EscritorCsv($salida);
        foreach ($filas as $fila) {
            $csv->fila($fila);
        }
        rewind($salida);
        $texto = (string) stream_get_contents($salida);
        fclose($salida);

        return $texto;
    }

    #[DataProvider('iniciosDeFormula')]
    public function testUnTextoQueEmpiezaComoFormulaLlevaUnApostrofeDelante(string $texto): void
    {
        self::assertSame("'" . $texto, EscritorCsv::celda($texto));
    }

    /** @return array<string, array{string}> */
    public static function iniciosDeFormula(): array
    {
        return [
            'igual' => ['=HYPERLINK("http://evil.example","clic")'],
            'mas' => ['+SUM(1+1)'],
            'menos' => ['-2+3'],
            'arroba' => ['@SUM(A1)'],
            'tabulador' => ["\t=1+1"],
            'retorno de carro' => ["\r=1+1"],
        ];
    }

    public function testUnTextoNormalNoSeToca(): void
    {
        foreach (['Rodrigo Rojas', 'a=b', 'x+y', 'correo@example.com', '2026-10-28', '', ' =no empieza'] as $texto) {
            self::assertSame($texto, EscritorCsv::celda($texto), "'{$texto}'");
        }
    }

    public function testLosNumerosNoSeNeutralizanAunqueSeanNegativos(): void
    {
        self::assertSame('-5.00', EscritorCsv::celda(-5.0), 'un saldo negativo sigue siendo un numero');
        self::assertSame('-12', EscritorCsv::celda(-12), 'un entero negativo tambien');
        self::assertSame('1234.50', EscritorCsv::celda(1234.5));
        self::assertSame('0.01', EscritorCsv::celda(0.005));
        self::assertSame('7', EscritorCsv::celda(7));
    }

    public function testUnDecimalQueRedondeaACeroNoSaleConSigno(): void
    {
        self::assertSame('0.00', EscritorCsv::celda(-0.001));
        self::assertSame('0.00', EscritorCsv::celda(-0.0));
        self::assertSame('0.00', EscritorCsv::celda(0.0));
    }

    public function testNullEsUnaCeldaVacia(): void
    {
        self::assertSame('', EscritorCsv::celda(null));
        self::assertSame("a,,c\r\n", self::escribir(['a', null, 'c']));
    }

    public function testCadaFilaTerminaEnCrlfComoPideElRfc4180(): void
    {
        self::assertSame("a,b\r\nc,d\r\n", self::escribir(['a', 'b'], ['c', 'd']));
    }

    public function testEntrecomillaSoloLoQueHaceFalta(): void
    {
        self::assertSame("simple,\"con coma, aqui\",\"con \"\"comillas\"\"\"\r\n", self::escribir(['simple', 'con coma, aqui', 'con "comillas"']));
    }

    public function testUnSaltoDeLineaDentroDeUnaCeldaNoRompeLaFila(): void
    {
        $flujo = fopen('php://memory', 'w+');
        self::assertNotFalse($flujo);
        fwrite($flujo, self::escribir(['linea uno' . "\n" . 'linea dos', 'x']));
        rewind($flujo);

        self::assertSame(["linea uno\nlinea dos", 'x'], fgetcsv($flujo, null, ',', '"', ''), 'la celda con salto de linea vuelve entera');
        self::assertFalse(fgetcsv($flujo, null, ',', '"', ''), 'y no hay una segunda fila');
    }

    public function testLaInyeccionDeFormulasSeNeutralizaEnLaFilaCompleta(): void
    {
        $texto = self::escribir(['=cmd|"/c calc"!A0', 'ok', -3.5, '@algo']);

        self::assertSame("\"'=cmd|\"\"/c calc\"\"!A0\",ok,-3.50,'@algo\r\n", $texto);
    }

    public function testLaMarcaDeOrdenSonLosTresBytesDeUtf8(): void
    {
        $salida = fopen('php://memory', 'w+');
        self::assertNotFalse($salida);

        (new EscritorCsv($salida))->marcaDeOrden();

        rewind($salida);
        self::assertSame("\xEF\xBB\xBF", stream_get_contents($salida));
    }

    public function testLasTildesYLasEnesPasanTalCual(): void
    {
        self::assertSame("Peña,Pérez,Ñandú\r\n", self::escribir(['Peña', 'Pérez', 'Ñandú']));
    }
}
