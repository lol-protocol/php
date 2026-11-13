<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * The solicitudes of access, correction or erasure that anyone makes about a persona at /s/{id}/ (SolicitudController),
 * and what the owner does with them (bin/solicitudes.php). Receiving one gives, changes and erases nothing: the owner
 * answers it by hand after checking who asks (docs/SOLICITUDES.md).
 *
 * A solicitud holds the e-mail and the text of whoever asked, which are their data: depurar() blanks them once a
 * closed solicitud is old enough, and leaves the number, the persona, the right asked for, the state and the dates.
 */
final class SolicitudRepository extends Repository
{
    /** What can be asked for: key => the label of the form. */
    public const DERECHOS = [
        'acceso' => 'Ver qué datos tienen de mí',
        'rectificacion' => 'Corregir un dato mío',
        'supresion' => 'Borrar mis datos',
    ];

    /** What the owner may close a solicitud as: the word of the command => the state it leaves. */
    public const CIERRES = ['atender' => 'atendida', 'rechazar' => 'rechazada'];

    /** How many months a closed solicitud keeps the requester's e-mail and text before depurar() erases them. */
    public const CONSERVAR_MESES = 12;

    /** How many closed solicitudes todas() lists (the pending ones are always all there). */
    public const CERRADAS_VISIBLES = 100;

    /** How many times crear() asks for a number when someone else took the one it read. */
    private const INTENTOS_DE_NUMERO = 5;

    /** How many solicitudes about this persona are waiting for the owner: one with many is not asked about again. */
    public function pendientesDe(int $personaId): int
    {
        return (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM solicitudes_persona WHERE persona_id = ? AND estado = 'pendiente'",
            [$personaId]
        );
    }

    /**
     * Stores a new solicitud ('pendiente') and returns its number. The number is MAX(id) + 1, so two people asking
     * at the same moment read the same one: the whole transaction is tried again (see RetiroRepository::crear()).
     */
    public function crear(int $personaId, string $derecho, string $detalle, string $contacto, ?int $ahora = null): int
    {
        if (!isset(self::DERECHOS[$derecho])) {
            throw new \InvalidArgumentException("«{$derecho}» no es un derecho que se pueda pedir.");
        }
        $cuando = date('Y-m-d H:i:s', $ahora ?? time());

        return $this->db->transaccionConReintento(function () use ($personaId, $derecho, $detalle, $contacto, $cuando): int {
            $numero = $this->db->siguienteNumero('solicitudes_persona', 'id');
            $this->db->execute(
                'INSERT INTO solicitudes_persona (id, persona_id, derecho, detalle, contacto, creado_en) VALUES (?, ?, ?, ?, ?, ?)',
                [$numero, $personaId, $derecho, $detalle, $contacto, $cuando]
            );

            return $numero;
        }, self::INTENTOS_DE_NUMERO);
    }

    /**
     * The solicitudes still waiting for the owner, oldest first, with the whole hours each has waited. Nothing about
     * who asked or what they wrote: this is what a cron job mails.
     *
     * @return list<array{id: int, persona_id: int, derecho: string, creado_en: string, horas: int}>
     */
    public function pendientes(?int $ahora = null): array
    {
        $ahora ??= time();

        return array_map(static fn(array $f): array => [
            'id' => (int)$f['id'],
            'persona_id' => (int)$f['persona_id'],
            'derecho' => (string)$f['derecho'],
            'creado_en' => (string)$f['creado_en'],
            'horas' => max(0, intdiv($ahora - (int)strtotime((string)$f['creado_en']), 3600)),
        ], $this->db->fetchAll("SELECT id, persona_id, derecho, creado_en FROM solicitudes_persona WHERE estado = 'pendiente' ORDER BY creado_en, id"));
    }

    /**
     * Every solicitud still pending, then the $cerradas most recent closed ones (the newest first), in full: for the
     * owner's panel (/0/4/), who needs the e-mail and the text to answer. The closed ones are capped because nothing
     * ever removes them but depurar(), which blanks their data and keeps the row.
     *
     * @return list<array<string, mixed>>
     */
    public function todas(int $cerradas = self::CERRADAS_VISIBLES): array
    {
        return [
            ...$this->db->fetchAll("SELECT * FROM solicitudes_persona WHERE estado = 'pendiente' ORDER BY creado_en DESC, id DESC"),
            ...$this->db->fetchAll("SELECT * FROM solicitudes_persona WHERE estado <> 'pendiente' ORDER BY creado_en DESC, id DESC LIMIT " . max(0, $cerradas)),
        ];
    }

    /**
     * One solicitud in full, for the owner who has to answer it (the e-mail and the text included).
     *
     * @return array<string, mixed>|null
     */
    public function ver(int $numero): ?array
    {
        return $this->db->fetchOne('SELECT * FROM solicitudes_persona WHERE id = ?', [$numero]);
    }

    /**
     * Closes a pending solicitud. False when there is none with that number, or it was closed already (a closed one is
     * never re-opened or re-worded: the first answer stays).
     *
     * @param string $cierre a CIERRES key
     */
    public function cerrar(int $numero, string $cierre, ?string $nota, ?int $ahora = null): bool
    {
        if (!isset(self::CIERRES[$cierre])) {
            throw new \InvalidArgumentException("«{$cierre}» no es un cierre.");
        }

        return $this->db->execute(
            "UPDATE solicitudes_persona SET estado = ?, cerrada_en = ?, nota = ? WHERE id = ? AND estado = 'pendiente'",
            [self::CIERRES[$cierre], date('Y-m-d H:i:s', $ahora ?? time()), $nota, $numero]
        ) === 1;
    }

    /**
     * Erases the e-mail, the text and the note of the solicitudes closed more than $meses ago (once each: depurado_en),
     * and keeps the rest. The guard is in the UPDATE and each row is counted, so two runs at the same time do not
     * report the same ones twice.
     *
     * @return list<int> the numbers it erased
     */
    public function depurar(int $meses, ?int $ahora = null): array
    {
        $ahora ??= time();
        $limite = date('Y-m-d H:i:s', strtotime("-{$meses} months", $ahora) ?: 0);
        $borradas = [];

        $candidatas = $this->db->fetchAll(
            "SELECT id FROM solicitudes_persona WHERE estado <> 'pendiente' AND depurado_en IS NULL AND cerrada_en < ? ORDER BY id",
            [$limite]
        );
        foreach ($candidatas as $fila) {
            $cambiadas = $this->db->execute(
                "UPDATE solicitudes_persona SET contacto = '', detalle = '', nota = NULL, depurado_en = ? WHERE id = ? AND depurado_en IS NULL",
                [date('Y-m-d H:i:s', $ahora), (int)$fila['id']]
            );
            if ($cambiadas === 1) {
                $borradas[] = (int)$fila['id'];
            }
        }

        return $borradas;
    }
}
