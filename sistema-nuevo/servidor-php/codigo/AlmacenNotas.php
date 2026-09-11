<?php

declare(strict_types=1);

class AlmacenNotas
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function guardar(string $accionId, string $texto): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            $this->eliminar($accionId);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO notas_acciones (accion_id, texto) VALUES (?, ?)
             ON CONFLICT (accion_id) DO UPDATE SET texto = EXCLUDED.texto'
        );
        $stmt->execute([$accionId, $texto]);
    }

    public function eliminar(string $accionId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notas_acciones WHERE accion_id = ?');
        $stmt->execute([$accionId]);
    }
}
