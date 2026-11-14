<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * Delivering to a persona, with a key, what the site holds about her (bin/persona.php entrega, EntregaController at
 * /e/{token}/). It exists for what a summary written by hand does not give her: what the site hides from a visitor.
 * The owner creates it only AFTER checking who asks (docs/SOLICITUDES.md), because the page shows a living persona's
 * hidden data to whoever has the link and the key.
 *
 * The link and the key travel by two different means. The link holds a random token of which only the SHA-256 hash is
 * stored (a leaked table cannot rebuild a link); the key is stored with password_hash(). Both are shown once, when
 * they are created. A link stops existing (it is a 404, as one made up) when it expires, after MAX_VISTAS good openings
 * or after MAX_INTENTOS wrong keys: whoever guesses cannot do it more than MAX_INTENTOS times per link, even when the
 * guesses arrive at the same moment, because an attempt is reserved with one guarded UPDATE BEFORE the key is checked.
 */
final class EntregaRepository extends Repository
{
    /** How many days a delivery lives by default. */
    public const VIGENCIA_DIAS = 7;

    /** How many times it can be opened with the right key. */
    public const MAX_VISTAS = 3;

    /** How many wrong keys it takes before the link stops working. */
    public const MAX_INTENTOS = 5;

    /** The characters of a key: no 0/O, 1/I/L, so it can be read aloud or over the phone without doubt. 12 of 31 are about 59 bits. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @var int how many characters a key has */
    private const LARGO_CLAVE = 12;

    /** How many times crear() asks for a number when someone else took the one it read. */
    private const INTENTOS_DE_NUMERO = 5;

    /**
     * Creates a delivery for a persona and returns what the owner needs to send: the token of the link and the key,
     * which are not stored and cannot be recovered afterwards.
     *
     * @return array{id: int, token: string, clave: string, vence_en: string}
     * @throws \DomainException when there is no such persona
     */
    public function crear(int $personaId, int $dias = self::VIGENCIA_DIAS, ?int $ahora = null): array
    {
        if ($dias < 1 || $dias > 90) {
            throw new \DomainException('La vigencia es de 1 a 90 días.');
        }
        $ahora ??= time();
        $token = bin2hex(random_bytes(32));
        $clave = self::claveNueva();
        $hash = password_hash(self::normalizar($clave), PASSWORD_DEFAULT);
        $vence = date('Y-m-d H:i:s', $ahora + $dias * 86400);

        $numero = $this->db->transaccionConReintento(function () use ($personaId, $token, $hash, $ahora, $vence): int {
            if ($this->db->fetchValue('SELECT 1 FROM personas WHERE id = ?', [$personaId]) === null) {
                throw new \DomainException("No existe la persona {$personaId}.");
            }
            $numero = $this->db->siguienteNumero('entregas_persona', 'id');
            $this->db->execute(
                'INSERT INTO entregas_persona (id, persona_id, token_hash, clave_hash, creada_en, vence_en) VALUES (?, ?, ?, ?, ?, ?)',
                [$numero, $personaId, hash('sha256', $token), $hash, date('Y-m-d H:i:s', $ahora), $vence]
            );

            return $numero;
        }, self::INTENTOS_DE_NUMERO);

        return ['id' => $numero, 'token' => $token, 'clave' => $clave, 'vence_en' => $vence];
    }

    /** Whether a link is alive: it exists, has not expired and has neither its views nor its attempts used up. */
    public function vigente(string $token, ?int $ahora = null): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM entregas_persona WHERE token_hash = ? AND vence_en > ? AND vistas < ? AND intentos < ?',
            [hash('sha256', $token), date('Y-m-d H:i:s', $ahora ?? time()), self::MAX_VISTAS, self::MAX_INTENTOS]
        ) !== null;
    }

    /**
     * Opens a delivery with its key. Returns the persona it is about when the key is right, 'clave' when it is wrong
     * (the attempt is counted), and null when the link does not exist or no longer works (expired, used up, locked):
     * the caller answers 404 to that, the same as to a link made up.
     *
     * @return int|'clave'|null
     */
    public function abrir(string $token, string $clave, ?int $ahora = null): int|string|null
    {
        $hash = hash('sha256', $token);
        $cuando = date('Y-m-d H:i:s', $ahora ?? time());

        // One guarded UPDATE reserves the attempt: it only changes a row that is still alive, so a burst of guesses at the
        // same moment gets at most MAX_INTENTOS of them through, however many arrive.
        $reservada = $this->db->execute(
            'UPDATE entregas_persona SET intentos = intentos + 1 WHERE token_hash = ? AND vence_en > ? AND vistas < ? AND intentos < ?',
            [$hash, $cuando, self::MAX_VISTAS, self::MAX_INTENTOS]
        );
        if ($reservada !== 1) {
            return null;
        }

        $fila = $this->db->fetchOne('SELECT id, persona_id, clave_hash FROM entregas_persona WHERE token_hash = ?', [$hash]);
        if ($fila === null || !password_verify(self::normalizar($clave), (string)$fila['clave_hash'])) {
            return 'clave';
        }

        // The right key: the attempt it used goes back, and the opening is counted (still guarded: the views are limited).
        $abierta = $this->db->execute(
            'UPDATE entregas_persona SET intentos = 0, vistas = vistas + 1, ultima_vista_en = ? WHERE id = ? AND vistas < ?',
            [$cuando, (int)$fila['id'], self::MAX_VISTAS]
        );

        return $abierta === 1 ? (int)$fila['persona_id'] : null;
    }

    /**
     * Ends every delivery of a persona that still works, at once (the owner sent the link to the wrong person, or the
     * key was told too widely).
     *
     * @return int how many were ended
     */
    public function revocar(int $personaId, ?int $ahora = null): int
    {
        $cuando = date('Y-m-d H:i:s', $ahora ?? time());

        return $this->db->execute('UPDATE entregas_persona SET vence_en = ? WHERE persona_id = ? AND vence_en > ?', [$cuando, $personaId, $cuando]);
    }

    /**
     * Deletes the deliveries that expired more than $meses ago. What a row holds is only hashes, the persona's id and the
     * dates, so nothing personal goes: it is kept for a while as the record that an access was delivered, and then it has
     * no use. A delivery that still works is never touched, however old. (A persona's erasure deletes hers too: see
     * SupresionRepository.) The count is of rows the DELETE removed.
     *
     * @return int how many were deleted
     */
    public function depurar(int $meses, ?int $ahora = null): int
    {
        $ahora ??= time();
        $limite = date('Y-m-d H:i:s', strtotime("-{$meses} months", $ahora) ?: 0);

        return $this->db->execute('DELETE FROM entregas_persona WHERE vence_en < ?', [$limite]);
    }

    /**
     * What a persona is shown: the same as bin/persona.php acceso, in the order and the words of a page, and without
     * the internal ids that mean nothing to her (who contributed the persona, her surname group). Relatives are counts,
     * as everywhere: their names are their own data.
     *
     * @return array<string, mixed>|null null when the persona no longer exists (she was erased)
     */
    public function contenido(int $personaId): ?array
    {
        $acceso = (new SupresionRepository($this->db))->acceso($personaId);
        if ($acceso === null) {
            return null;
        }
        $p = $acceso['persona'];

        return [
            'ficha' => array_filter([
                'Nombres' => $p['nombres'],
                'Apellidos' => $p['apellidos'],
                'Sexo' => $p['sexo'],
                'Viva' => $p['viva'] === null ? 'no se ha marcado: se deduce de sus fechas' : ((bool)$p['viva'] ? 'sí' : 'no'),
                'Se agregó el' => $p['creado_en'],
                'Cambió por última vez el' => $p['actualizado_en'],
            ], static fn(mixed $v): bool => $v !== null && $v !== ''),
            'familiares' => $acceso['familiares'],
            'sucesos' => $acceso['sucesos'],
            'registros' => $acceso['registros'],
            'colecciones' => $acceso['colecciones'],
            'organizaciones' => $acceso['organizaciones'],
            'correcciones' => $acceso['correcciones'],
            'pedidos_de_retiro' => $acceso['pedidos_de_retiro'],
        ];
    }

    /** A new key, «XXXX-XXXX-XXXX», from random_int() (never rand()). */
    private static function claveNueva(): string
    {
        $clave = '';
        for ($i = 0; $i < self::LARGO_CLAVE; $i++) {
            $clave .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return implode('-', str_split($clave, 4));
    }

    /** What is compared: uppercase, without the dashes and spaces a person adds when typing or reading it out. */
    private static function normalizar(string $clave): string
    {
        return strtoupper(preg_replace('/[\s-]+/u', '', $clave) ?? '');
    }
}
