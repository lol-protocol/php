<?php

declare(strict_types=1);

/**
 * Registro de cada guardado de una nota: quién, cuándo, sobre qué acción y qué decidió el
 * filtro. No guarda el texto (la nota ya está en notas_acciones, y repetirla duplicaría los
 * datos de un usuario): guarda su largo y su huella SHA-256, que alcanzan para comprobar si
 * un texto dado es el que se escribió.
 */
final class AlmacenRegistroNotas
{
    /** Lo que decidió el filtro (approve | review | reject) y cómo queda en el registro. */
    public const DECISIONES = ['approve' => 'aprobada', 'review' => 'revisar', 'reject' => 'rechazada'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param string $decision aprobada | revisar | rechazada | borrada
     * @param list<string> $motivos
     */
    public function anotar(string $accionId, string $administrador, string $decision, array $motivos, string $texto): void
    {
        $texto = trim($texto);
        $stmt = $this->pdo->prepare(
            'INSERT INTO registro_notas (accion_id, administrador, decision, motivos, largo, huella)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$accionId, $administrador, $decision, implode(', ', $motivos), mb_strlen($texto), hash('sha256', $texto)]);
    }

    /** @return list<array<string,mixed>> los registros de una acción, el más nuevo primero */
    public function deAccion(string $accionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT administrador, decision, motivos, largo, huella, creado_en
             FROM registro_notas WHERE accion_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$accionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
