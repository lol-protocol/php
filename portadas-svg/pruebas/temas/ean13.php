<?php

declare(strict_types=1);

/** Pruebas del código de barras EAN-13 del ISBN. Las funciones auxiliares están en ../ayudas.php. */

use PortadasSvg\Ean13;
use PortadasSvg\Generador;

/** Lee los dígitos de unos módulos con las tablas inversas (L, G y R), para comprobar la codificación sin repetirla. */
function decodificarEan(string $modulos): string
{
    $l = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
    $g = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
    $r = array_map(static fn (string $codigo): string => strtr($codigo, '01', '10'), $l);
    $paridades = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];
    $digitos = '';
    $paridad = '';
    for ($i = 0; $i < 6; $i++) {
        $siete = substr($modulos, 3 + 7 * $i, 7);
        if (($d = array_search($siete, $l, true)) !== false) {
            $paridad .= 'L';
        } elseif (($d = array_search($siete, $g, true)) !== false) {
            $paridad .= 'G';
        } else {
            throw new RuntimeException("módulos {$siete} no son un dígito");
        }
        $digitos .= $d;
    }
    for ($i = 0; $i < 6; $i++) {
        $d = array_search(substr($modulos, 50 + 7 * $i, 7), $r, true);
        if ($d === false) {
            throw new RuntimeException('módulos de la derecha que no son un dígito');
        }
        $digitos .= $d;
    }
    $primero = array_search($paridad, $paridades, true);
    if ($primero === false) {
        throw new RuntimeException("paridad {$paridad} desconocida");
    }

    return $primero . $digitos;
}

return [
    'El ISBN se pasa a EAN-13, con su dígito de control, y uno que no vale da null' => function (): void {
        igual('9780306406157', Ean13::desdeIsbn('978-0-306-40615-7'));
        igual('9780306406157', Ean13::desdeIsbn('ISBN 0-306-40615-2'));
        igual('9780306406157', Ean13::desdeIsbn('0306406152'));
        igual(null, Ean13::desdeIsbn('978-0-306-40615-8'));
        igual(null, Ean13::desdeIsbn(''));
        igual('7', Ean13::control('978030640615'));
        igual('1', Ean13::control('400638133393'));
    },

    'Los módulos del EAN-13 tienen las guardas y se leen de vuelta' => function (): void {
        foreach (['9780306406157', '4006381333931', '0012345678905', '9789992158104'] as $ean) {
            $modulos = Ean13::modulos($ean);
            igual(Ean13::MODULOS, strlen($modulos));
            igual('101', substr($modulos, 0, 3));
            igual('01010', substr($modulos, 45, 5));
            igual('101', substr($modulos, 92, 3));
            igual($ean, decodificarEan($modulos));
        }
        lanza(InvalidArgumentException::class, static fn () => Ean13::modulos('9780306406158'));
        lanza(InvalidArgumentException::class, static fn () => Ean13::modulos('97803064061'));
    },

    'Las franjas cubren exactamente los módulos negros, y las guardas se reconocen' => function (): void {
        $modulos = Ean13::modulos('9780306406157');
        $negros = 0;
        foreach (Ean13::franjas($modulos) as [$desde, $cuantos]) {
            igual(str_repeat('1', $cuantos), substr($modulos, $desde, $cuantos));
            $negros += $cuantos;
        }
        igual(substr_count($modulos, '1'), $negros);
        verdadero(Ean13::esGuarda(0) && Ean13::esGuarda(46) && Ean13::esGuarda(94) && !Ean13::esGuarda(10), 'guardas');
    },

    'La contratapa dibuja el código de barras con un ISBN válido y deja el hueco en blanco si no' => function (): void {
        conCarpeta(static function (string $dir): void {
            Generador::escribir(libroDeEjemplo(['isbn' => '978-0-306-40615-7']), "$dir/bien");
            Generador::escribir(libroDeEjemplo(['isbn' => '978-0-306-40615-8']), "$dir/malo");
            Generador::escribir(libroDeEjemplo(), "$dir/sin");
            $leer = static fn (string $ruta): string => (string) file_get_contents($ruta);
            $bien = $leer("$dir/bien/contratapa.svg");
            verdadero(str_contains($bien, 'fill="#000000"'), 'las barras van en negro');
            verdadero(str_contains($bien, '780306') && str_contains($bien, '406157'), 'los dígitos van bajo las barras');
            foreach (['malo', 'sin'] as $caso) {
                verdadero(!str_contains($leer("$dir/$caso/contratapa.svg"), 'fill="#000000"'), "$caso: el hueco queda en blanco");
            }
        });
    },
];
