<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * Merges a duplicate grupo (surname) into the one that stays, in one transaction: its personas move to the one
 * that stays, which keeps what it has and takes the origin and description it lacks; the duplicate is deleted
 * and its id becomes an alias (grupo_alias), so its URLs redirect instead of breaking. The same idea as
 * FusionRepository, for the type most likely to be created twice (Garcia and García).
 */
final class FusionGrupoRepository extends Repository
{
    /**
     * What a merge would move, without changing anything.
     *
     * @return array<string, int> what => how many rows
     */
    public function resumen(int $duplicado): array
    {
        return [
            'personas' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM personas WHERE grupo_id = ?', [$duplicado]),
            'alias' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM grupo_alias WHERE grupo_id = ?', [$duplicado]),
        ];
    }

    /** @throws \DomainException when the two can't be merged (see validar()) */
    public function fusionar(int $duplicado, int $vigente): void
    {
        $this->db->transaction(function () use ($duplicado, $vigente): void {
            $this->validar($duplicado, $vigente);

            $this->db->execute(
                'UPDATE grupos SET
                        origen = COALESCE(origen, (SELECT d.origen FROM grupos d WHERE d.id = ?)),
                        descripcion = COALESCE(descripcion, (SELECT d.descripcion FROM grupos d WHERE d.id = ?))
                  WHERE id = ?',
                [$duplicado, $duplicado, $vigente]
            );
            $this->db->execute('UPDATE personas SET grupo_id = ? WHERE grupo_id = ?', [$vigente, $duplicado]);
            $this->db->execute('UPDATE grupo_alias SET grupo_id = ? WHERE grupo_id = ?', [$vigente, $duplicado]);
            $this->db->execute('DELETE FROM grupos WHERE id = ?', [$duplicado]);
            $this->db->execute('INSERT INTO grupo_alias (id, grupo_id) VALUES (?, ?)', [$duplicado, $vigente]);
        });
    }

    /**
     * Refuses a merge that makes no sense, or that would quietly undo a pedido de retiro: a page the owner hid
     * or withdrew keeps answering 410 on its old id, so merging it away first would hide the decision.
     */
    private function validar(int $duplicado, int $vigente): void
    {
        if ($duplicado === $vigente) {
            throw new \DomainException('Un grupo no se puede fusionar consigo mismo.');
        }

        $existen = (int)$this->db->fetchValue('SELECT COUNT(*) FROM grupos WHERE id IN (?, ?)', [$duplicado, $vigente]);
        if ($existen !== 2) {
            throw new \DomainException('Los dos grupos tienen que existir.');
        }

        $retirados = (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM grupos g WHERE g.id IN (?, ?) AND ' . RetiroRepository::sqlRetirado('grupo', 'g.id'),
            [$duplicado, $vigente]
        );
        if ($retirados > 0) {
            throw new \DomainException('Uno de los dos tiene un pedido de retiro que lo oculta o lo retira: resuélvelo antes de fusionar.');
        }
    }
}
