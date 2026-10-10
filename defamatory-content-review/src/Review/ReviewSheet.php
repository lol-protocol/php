<?php

namespace DefamatoryContentReview\Review;

/**
 * Lee una planilla de review/ ya llenada por el revisor y la traduce a
 * cambios (ver review/README.md, «Cómo se convierte la revisión en cambios»):
 *
 * - `término`: `no` lo quita; `low`/`medium`/`high` (o baja/media/alta) le
 *   cambia la severidad; `ambiguous` o `nameCollision` le ponen esa marca, y
 *   `sin ambiguous` (o `-ambiguous`) se la quita.
 * - `frecuente`: `no` (no debería censurarse) la añade a `everyday`.
 * - `excepción`: `no` (no es una palabra cotidiana) la quita de su lista.
 *
 * Lo que no se puede aplicar solo —un patrón, un comentario, una marca en una
 * lista de temas— va a `manual`; un valor desconocido, a `problems`. `sí` o la
 * celda vacía no cambian nada.
 */
final class ReviewSheet
{
    private const YES = ['sí', 'si', 'yes', 'ok'];
    private const SEVERITY = ['low' => 'low', 'medium' => 'medium', 'high' => 'high', 'baja' => 'low', 'media' => 'medium', 'alta' => 'high'];
    private const FLAGS = ['ambiguous' => 'ambiguous', 'namecollision' => 'nameCollision'];

    /** @var array<int,array{action:string,file:string,word:string,value:string,was:string,row:int}> */
    public array $changes = [];
    /** @var array<int,string> */
    public array $manual = [];
    /** @var array<int,string> */
    public array $problems = [];
    public int $pending = 0;

    /** @param iterable<int,array<int,string|null>> $rows filas del CSV, la primera con los encabezados */
    public function __construct(iterable $rows)
    {
        $header = null;
        foreach ($rows as $index => $values) {
            $values = array_map(fn($v): string => trim((string) $v), $values);
            if ($header === null) {
                $header = $values;
                continue;
            }
            if (count($values) === count($header)) {
                $this->read(array_combine($header, $values), $index + 1);
            }
        }
    }

    /** @param array<string,string> $row */
    private function read(array $row, int $line): void
    {
        $answer = mb_strtolower($row['correcto']);
        $where = "fila {$line} ({$row['seccion']} «{$row['termino']}»)";
        if ($row['comentario'] !== '') {
            $this->manual[] = "{$where}: {$row['comentario']}";
        }
        if ($answer === '' || in_array($answer, self::YES, true)) {
            $this->pending += $answer === '' ? 1 : 0;
            return;
        }
        $file = $row['origen'] === 'diccionario' ? 'languages' : 'chat-topics';
        $off = (string) preg_replace('/^(?:-|sin\s+)/u', '', $answer); // «sin ambiguous», «-ambiguous»: quitar la marca
        $change = match (true) {
            $row['seccion'] === 'término' && $answer === 'no' => ['remove', $file, ''],
            $row['seccion'] === 'término' && isset(self::SEVERITY[$answer]) => ['severity', $file, self::SEVERITY[$answer]],
            $row['seccion'] === 'término' && isset(self::FLAGS[$answer]) && $file === 'languages' => ['flag', $file, self::FLAGS[$answer]],
            $row['seccion'] === 'término' && isset(self::FLAGS[$off]) && $file === 'languages' => ['flag-off', $file, self::FLAGS[$off]],
            $row['seccion'] === 'frecuente' && $answer === 'no' => ['list-add', 'chat-topics', 'everyday'],
            $row['seccion'] === 'excepción' && $answer === 'no' => ['list-remove', 'chat-topics', $row['categoria']],
            default => null,
        };
        if ($change !== null) {
            $this->changes[] = ['action' => $change[0], 'file' => $change[1], 'word' => $row['termino'], 'value' => $change[2],
                'was' => $row['seccion'] === 'término' ? $row['severidad'] : '', 'row' => $line];
        } elseif ($row['seccion'] === 'patrón' || isset(self::FLAGS[$off])) {
            $this->manual[] = "{$where}: «{$row['correcto']}» se aplica a mano";
        } else {
            $this->problems[] = "{$where}: «{$row['correcto']}» no es una respuesta válida (sí, no, low/medium/high, ambiguous, nameCollision, sin ambiguous…)";
        }
    }
}
