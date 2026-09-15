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
    public function anular(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->tablaAnulable} SET anulada = TRUE WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
