<?php

declare(strict_types=1);

namespace App\Repositories;

use PDOStatement;

/**
 * Las consultas de reporting que reciben un periodo (desde y hasta, en Y-m-d) y lo pasan a la
 * sentencia como :desde y :hasta. Eran prepare() + execute([':desde' => ..., ':hasta' => ...]) +
 * fetch*() escritos a mano en catorce consultas; aca queda una vez, con el fetch que cada una
 * necesita. La clase que use este trait debe tener $this->db (PDO), igual que Anulable.
 *
 * Una consulta con mas parametros que el periodo (el filtro de cliente de los listados, por
 * ejemplo) no entra aca: arma los suyos.
 */
trait ConsultasEnRango
{
    /** Todas las filas de $sql. @return array<int, array<string, mixed>> */
    private function filasEnRango(string $sql, string $desde, string $hasta): array
    {
        return $this->consultarEnRango($sql, $desde, $hasta)->fetchAll();
    }

    /** La primera fila de $sql (una consulta de agregados da siempre una). @return array<string, mixed> */
    private function filaEnRango(string $sql, string $desde, string $hasta): array
    {
        $fila = $this->consultarEnRango($sql, $desde, $hasta)->fetch();

        return $fila === false ? [] : $fila;
    }

    /** La primera columna de la primera fila de $sql, o null si no hay filas. */
    private function valorEnRango(string $sql, string $desde, string $hasta): mixed
    {
        $valor = $this->consultarEnRango($sql, $desde, $hasta)->fetchColumn();

        return $valor === false ? null : $valor;
    }

    private function consultarEnRango(string $sql, string $desde, string $hasta): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        return $stmt;
    }
}
