<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\DigitoVerificador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DigitoVerificadorTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('IDS_DIGITO_VERIFICADOR');
    }

    protected function tearDown(): void
    {
        putenv('IDS_DIGITO_VERIFICADOR');
    }

    /** The worked example of the algorithm: 572 takes 4. */
    public function testKnownExample(): void
    {
        $this->assertSame(4, DigitoVerificador::calcular('572'));
        $this->assertSame('5724', DigitoVerificador::completar('572'));
        $this->assertTrue(DigitoVerificador::esValido('5724'));
    }

    /** @return array<string, array{string}> */
    public static function ids(): array
    {
        return [
            'persona' => ['612847310'],
            'suceso' => ['41200000'],
            'cuatro ceros' => ['0000'],
            'ceros a la izquierda' => ['000000047'],
            'un solo dígito' => ['7'],
            'de once cifras' => ['97531864209'],
        ];
    }

    #[DataProvider('ids')]
    public function testACompletedIdIsValid(string $digitos): void
    {
        $this->assertTrue(DigitoVerificador::esValido(DigitoVerificador::completar($digitos)));
    }

    /** Damm's promise: any one wrong digit, in any place, is caught. */
    public function testEverySingleDigitErrorIsCaught(): void
    {
        $id = DigitoVerificador::completar('612847310');
        for ($lugar = 0; $lugar < strlen($id); $lugar++) {
            for ($otro = 0; $otro <= 9; $otro++) {
                if ((string)$otro === $id[$lugar]) {
                    continue;
                }
                $malo = substr_replace($id, (string)$otro, $lugar, 1);
                $this->assertFalse(DigitoVerificador::esValido($malo), "{$id} -> {$malo}");
            }
        }
    }

    /** ...and so is swapping two neighbours (Luhn misses 09 <-> 90). */
    public function testEveryAdjacentSwapIsCaught(): void
    {
        foreach (['612847310', '41200000', '905123478', '81372047'] as $base) {
            $id = DigitoVerificador::completar($base);
            for ($lugar = 0; $lugar < strlen($id) - 1; $lugar++) {
                if ($id[$lugar] === $id[$lugar + 1]) {
                    continue;
                }
                $malo = substr_replace($id, $id[$lugar + 1] . $id[$lugar], $lugar, 2);
                $this->assertFalse(DigitoVerificador::esValido($malo), "{$id} -> {$malo}");
            }
        }
    }

    public function testNotJustDigitsIsNotValid(): void
    {
        foreach (['', '7', 'abc', '12a4', '5724 ', '-5724'] as $malo) {
            $this->assertFalse(DigitoVerificador::esValido($malo), var_export($malo, true));
        }
    }

    public function testCalculatingNeedsDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DigitoVerificador::calcular('12a');
    }

    public function testItIsOffUnlessTheEnvironmentTurnsItOn(): void
    {
        $this->assertFalse(DigitoVerificador::activo());

        putenv('IDS_DIGITO_VERIFICADOR=true');
        $this->assertTrue(DigitoVerificador::activo());

        putenv('IDS_DIGITO_VERIFICADOR=false');
        $this->assertFalse(DigitoVerificador::activo());
    }
}
