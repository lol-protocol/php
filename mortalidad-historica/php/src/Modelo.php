<?php

declare(strict_types=1);

namespace MortalidadHistorica;

use InvalidArgumentException;
use RuntimeException;

/**
 * Port a PHP de modelo.py: el modelo log-cuadrático de mortalidad de Wilmoth et al. (2012), que arma una tabla de vida
 * completa desde la mortalidad de 0 a 5 años y la esperanza de vida al nacer, con los coeficientes de
 * datos/modelo_logcuadratico.csv (CC BY-SA 3.0 IGO, de DemoTools). Es un modelo, no una medición: ver modelo.py.
 *
 * Hace los mismos cálculos que Python, paso por paso; lo comprueban pruebas/ejecutar.php con datos/vectores.json.
 */
final class Modelo
{
    /** Grupos de edad del modelo: 0, 1-4, 5-9 … 105-109, 110+. */
    public const EDADES = [0, 1, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100, 105, 110];

    public const EDAD_MAXIMA = 110;

    public const SEXOS = ['T', 'F', 'M'];

    private const AMPLITUD = [1, 4, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5];

    /** @var array<string, array{list<float>, list<float>, list<float>, list<float>}> */
    private array $coeficientes = [];

    public function __construct(private readonly string $carpetaDeDatos)
    {
    }

    /**
     * Los coeficientes a, b, c y v de un sexo (T, F o M).
     *
     * @return array{list<float>, list<float>, list<float>, list<float>}
     */
    private function coeficientes(string $sexo): array
    {
        if (!in_array($sexo, self::SEXOS, true)) {
            throw new InvalidArgumentException("sexo «{$sexo}»: debe ser uno de " . implode(', ', self::SEXOS));
        }
        if (isset($this->coeficientes[$sexo])) {
            return $this->coeficientes[$sexo];
        }
        $ruta = "{$this->carpetaDeDatos}/modelo_logcuadratico.csv";
        $f = @fopen($ruta, 'rb');
        if ($f === false) {
            throw new RuntimeException("No se pudo abrir $ruta");
        }
        $cabecera = fgetcsv($f, 0, ',', '"', '');
        if ($cabecera === false || in_array(null, $cabecera, true)) {
            throw new RuntimeException("$ruta no tiene una cabecera válida");
        }
        $ax = $bx = $cx = $vx = [];
        $edades = [];
        while (($c = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($c === [null]) {
                continue;
            }
            $fila = array_combine($cabecera, array_map(fn (?string $v): string => (string) $v, $c));
            if ($fila['sexo'] === $sexo) {
                $edades[] = (int) $fila['edad'];
                $ax[] = (float) $fila['ax'];
                $bx[] = (float) $fila['bx'];
                $cx[] = (float) $fila['cx'];
                $vx[] = (float) $fila['vx'];
            }
        }
        fclose($f);
        if ($edades !== self::EDADES) {
            throw new RuntimeException("modelo_logcuadratico.csv no tiene los 24 grupos de edad para el sexo $sexo");
        }

        return $this->coeficientes[$sexo] = [$ax, $bx, $cx, $vx];
    }

    /**
     * Tabla de vida del modelo para una mortalidad 5q0 (0 < q5 < 1) y un k: [l, e], la proporción de sobrevivientes y la
     * esperanza restante en cada grupo de edad (l(0) = 1).
     *
     * @return array{list<float>, list<float>}
     */
    public function tabla(float $q5, float $k, string $sexo = 'T'): array
    {
        if (!($q5 > 0 && $q5 < 1)) {
            throw new InvalidArgumentException("5q0 fuera de rango: $q5");
        }
        [$ax, $bx, $cx, $vx] = $this->coeficientes($sexo);
        $h = log($q5);
        $m = [];
        foreach ($ax as $i => $a) {
            $m[] = exp($a + $bx[$i] * $h + $cx[$i] * $h * $h + $vx[$i] * $k);
        }
        $n = count(self::EDADES);
        $a0 = $m[0] >= 0.107 ? 0.35 : 0.0425 + 2.875 * $m[0];
        $q0 = $m[0] / (1 + (1 - $a0) * $m[0]);
        $q = [$q0, 1 - (1 - $q5) / (1 - $q0)];
        for ($i = 2; $i < $n - 1; $i++) {
            $q[] = min(1.0, 5 * $m[$i] / (1 + 2.5 * $m[$i]));
        }
        $q[] = 1.0;
        $a = [$a0, 1.5];
        for ($i = 2; $i < $n - 1; $i++) {
            $a[] = 2.5;
        }
        $a[] = 1 / $m[$n - 1];
        $l = [1.0];
        for ($i = 0; $i < $n - 1; $i++) {
            $l[] = $l[$i] * (1 - $q[$i]);
        }
        $personasAnio = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $personasAnio[] = self::AMPLITUD[$i] * $l[$i + 1] + $a[$i] * $l[$i] * $q[$i];
        }
        $personasAnio[] = $l[$n - 1] / $m[$n - 1];
        $e = array_fill(0, $n, 0.0);
        $acumulado = 0.0;
        for ($i = $n - 1; $i >= 0; $i--) {
            $acumulado += $personasAnio[$i];
            $e[$i] = $l[$i] > 0 ? $acumulado / $l[$i] : 0.0;
        }

        return [$l, $e];
    }

    /** El k para el que la tabla del modelo tiene esperanza de vida al nacer `$e0`, o null si no existe en (-10, 10). */
    public function resolverK(float $q5, float $e0, string $sexo = 'T'): ?float
    {
        $f = fn (float $k): float => $this->tabla($q5, $k, $sexo)[1][0] - $e0;
        $bajo = -10.0;
        $alto = 10.0;
        if (!($f($bajo) > 0 && 0 > $f($alto))) {
            return null;
        }
        for ($i = 0; $i < 50; $i++) {
            $medio = ($bajo + $alto) / 2;
            if ($f($medio) > 0) {
                $bajo = $medio;
            } else {
                $alto = $medio;
            }
        }

        return ($bajo + $alto) / 2;
    }

    /**
     * [l, e] del modelo para esa mortalidad de 0 a 5 años (por mil) y esa esperanza al nacer, o null si no las puede cuadrar.
     *
     * @return array{list<float>, list<float>}|null
     */
    public function perfil(float $q5PorMil, float $e0, string $sexo = 'T'): ?array
    {
        $q5 = $q5PorMil / 1000.0;
        if (!($q5 > 0 && $q5 < 1)) {
            return null;
        }
        $k = $this->resolverK($q5, $e0, $sexo);

        return $k === null ? null : $this->tabla($q5, $k, $sexo);
    }

    /**
     * Proporción de los recién nacidos que llega con vida a `$edad` (0 a 110), por interpolación lineal entre los grupos
     * de edad del modelo.
     *
     * @param list<float> $l
     */
    public static function supervivenciaEn(array $l, float $edad): float
    {
        if (!($edad >= 0 && $edad <= self::EDAD_MAXIMA)) {
            throw new InvalidArgumentException('edad fuera de 0 a ' . self::EDAD_MAXIMA . ": $edad");
        }
        for ($i = 0; $i < count(self::EDADES) - 1; $i++) {
            if ($edad <= self::EDADES[$i + 1]) {
                $fraccion = ($edad - self::EDADES[$i]) / (self::EDADES[$i + 1] - self::EDADES[$i]);

                return $l[$i] + $fraccion * ($l[$i + 1] - $l[$i]);
            }
        }

        return $l[count($l) - 1];
    }

    /**
     * La edad a la que la proporción de sobrevivientes baja a `$objetivo` (0 < objetivo <= 1); 110 si el modelo no llega
     * tan abajo.
     *
     * @param list<float> $l
     */
    public static function edadDeSupervivencia(array $l, float $objetivo): float
    {
        if (!($objetivo > 0 && $objetivo <= 1)) {
            throw new InvalidArgumentException("proporción fuera de (0, 1]: $objetivo");
        }
        for ($i = 0; $i < count(self::EDADES) - 1; $i++) {
            if ($l[$i + 1] <= $objetivo && $objetivo <= $l[$i]) {
                if ($l[$i] === $l[$i + 1]) {
                    return (float) self::EDADES[$i];
                }

                return self::EDADES[$i] + ($l[$i] - $objetivo) / ($l[$i] - $l[$i + 1]) * (self::EDADES[$i + 1] - self::EDADES[$i]);
            }
        }

        return (float) self::EDAD_MAXIMA;
    }
}
