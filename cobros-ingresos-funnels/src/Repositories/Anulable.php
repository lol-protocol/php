<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * El soft-delete (UPDATE ... SET anulada = TRUE WHERE id = :id) es identico
 * en todos los repos que lo usan; solo cambia la tabla. La clase que use
 * este trait debe tener $this->db (PDO) y $this->tablaAnulable.
 */
trait Anulable
{
    /**
     * Anula la fila solo si todavia estaba activa y devuelve si fue ESTA
     * llamada la que la anulo.
     *
     * El chequeo va en la misma sentencia que el UPDATE a proposito. Hacerlo
     * antes en PHP ("leer, ver que no esta anulada, anular") deja una ventana
     * entre la lectura y la escritura: en READ COMMITTED dos procesos leen
     * anulada = FALSE y los dos siguen adelante, asi que una boleta terminaba
     * con dos notas de credito -devolviendole la plata al cliente dos veces- y
     * un pago con dos entradas de auditoria. Con el WHERE ... AND NOT anulada,
     * el segundo proceso queda bloqueado en la fila hasta que el primero
     * commitea, reevalua la condicion, no matchea nada y devuelve false, asi
     * el llamador sabe que los efectos de la anulacion ya los aplico otro.
     */
    public function anularSiEstabaActiva(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->tablaAnulable} SET anulada = TRUE WHERE id = :id AND NOT anulada RETURNING id"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }
}
