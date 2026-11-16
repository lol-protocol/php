<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Índice TF-IDF con similitud coseno sobre los pasajes de un libro.
 *
 * idf(t) = ln((N + 1) / (df(t) + 1)) + 1, y cada pasaje y cada consulta se normalizan a
 * longitud 1. Los empates se resuelven por el número de pasaje (el primero gana).
 * La versión en Java (java/src/chatlibros/Indice.java) suma en el mismo orden, para que
 * los puntajes coincidan con `pruebas/vectores.tsv`.
 */
final class Indice
{
    /** @var array<string,float> */
    private array $idf = [];

    /** @var list<array<string,float>> */
    private array $vectores = [];

    /**
     * @param list<list<string>> $pasajes términos normalizados de cada pasaje
     */
    public function __construct(array $pasajes)
    {
        $n = count($pasajes);
        $df = [];
        foreach ($pasajes as $terminos) {
            foreach (array_unique($terminos) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        foreach ($df as $t => $d) {
            $this->idf[(string) $t] = log(($n + 1) / ($d + 1)) + 1.0;
        }
        foreach ($pasajes as $terminos) {
            $this->vectores[] = $this->vector($terminos);
        }
    }

    /**
     * Pasajes más parecidos a la consulta, del más parecido al menos parecido.
     *
     * @param list<string> $terminos términos ya normalizados (Normaliza::terminos)
     * @return list<array{pasaje:int, puntaje:float}>
     */
    public function buscar(array $terminos, int $k = 3): array
    {
        $consulta = $this->vector($terminos);
        $resultados = [];
        foreach ($this->vectores as $i => $vector) {
            $puntaje = 0.0;
            foreach ($consulta as $t => $peso) {
                if (isset($vector[(string) $t])) {
                    $puntaje += $peso * $vector[(string) $t];
                }
            }
            $resultados[] = ['pasaje' => $i, 'puntaje' => $puntaje];
        }
        usort(
            $resultados,
            static fn (array $a, array $b): int => ($b['puntaje'] <=> $a['puntaje'])
                ?: ($a['pasaje'] <=> $b['pasaje']),
        );
        return array_slice($resultados, 0, max(0, $k));
    }

    /**
     * @param list<string> $terminos
     * @return array<string,float> peso normalizado por término, en orden de aparición
     */
    private function vector(array $terminos): array
    {
        $v = [];
        foreach ($terminos as $t) {
            if (!isset($this->idf[$t])) {
                continue;
            }
            $v[$t] = ($v[$t] ?? 0.0) + 1.0;
        }
        $norma = 0.0;
        foreach ($v as $t => $frecuencia) {
            $v[$t] = $frecuencia * $this->idf[(string) $t];
            $norma += $v[$t] * $v[$t];
        }
        $norma = sqrt($norma);
        if ($norma > 0.0) {
            foreach ($v as $t => $peso) {
                $v[$t] = $peso / $norma;
            }
        }
        return $v;
    }
}
