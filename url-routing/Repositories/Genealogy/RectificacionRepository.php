<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;
use App\Support\Privacidad;

/**
 * The owner's tool to correct a persona's ficha (bin/persona.php rectificar) instead of an UPDATE by hand. Only a
 * short list of fields can be corrected (CAMPOS), one at a time, and every change:
 *
 *  - happens in one transaction, touches exactly one row and stamps `actualizado_en` (migration 010: the content of
 *    the page changed);
 *  - is recorded in rectificaciones_persona (which field, when, the pedido: never the values), through
 *    SupresionRepository::rectificar();
 *  - tells whether the change hides the persona from a visitor or shows her (a birth or death date decides whether she
 *    may be alive, see Privacidad), because that is what an UPDATE by hand does not say.
 *
 * Without $aplicar it does all of that inside the transaction and rolls it back, so what it reports is what would
 * happen, and nothing is left behind.
 *
 * A date or a description belongs to an event (`sucesos`), which other people may be in. It is corrected only when the
 * persona is the one the event is about (ROL_PRINCIPAL) and the only one: a wedding, which has two, is corrected by
 * hand, looking at both.
 */
final class RectificacionRepository extends Repository
{
    /** The fields that can be corrected, and what table each is in. */
    public const CAMPOS = [
        'personas.nombres' => 'personas',
        'personas.apellidos' => 'personas',
        'personas.sexo' => 'personas',
        'sucesos.fecha' => 'sucesos',
        'sucesos.descripcion' => 'sucesos',
    ];

    /** The longest name, surname or description accepted. */
    private const MAX_NOMBRE = 200;
    private const MAX_DESCRIPCION = 2000;

    /**
     * @param string $campo one of CAMPOS
     * @param string $valor the new value, as typed
     * @param int|null $suceso the event, for the fields of `sucesos` (and only for them)
     * @param int|null $pedido the pedido de retiro that asked for it, for the record
     * @param bool $aplicar false: report what would happen and change nothing
     * @return array{campo: string, aplicada: bool, antes_oculta: bool, despues_oculta: bool, correccion: int|null, aviso_grupo: string|null}
     * @throws \DomainException when the persona, the pedido or the event are wrong, the field cannot be corrected, the
     *                          value is not valid or is the one the field already has
     */
    public function rectificar(int $id, string $campo, string $valor, ?int $suceso = null, ?int $pedido = null, bool $aplicar = false): array
    {
        if (!isset(self::CAMPOS[$campo])) {
            throw new \DomainException("«{$campo}» no se corrige con este comando. Los campos son: " . implode(', ', array_keys(self::CAMPOS)) . '; lo demás, a mano (docs/SOLICITUDES.md).');
        }
        $nuevo = self::normalizar($campo, $valor);
        $marca = new \RuntimeException('simulación');
        $resultado = null;

        try {
            $this->db->transaccionConReintento(function () use ($id, $campo, $nuevo, $suceso, $pedido, $aplicar, $marca, &$resultado): void {
                $resultado = $this->cambiar($id, $campo, $nuevo, $suceso, $pedido, $aplicar);
                if (!$aplicar) {
                    throw $marca;
                }
            });
        } catch (\RuntimeException $e) {
            if ($e !== $marca) {
                throw $e;
            }
        }
        assert($resultado !== null);

        return $resultado;
    }

    /**
     * @return array{campo: string, aplicada: bool, antes_oculta: bool, despues_oculta: bool, correccion: int|null, aviso_grupo: string|null}
     */
    private function cambiar(int $id, string $campo, string $nuevo, ?int $suceso, ?int $pedido, bool $aplicar): array
    {
        $fila = $this->db->fetchOne('SELECT apellidos, grupo_id FROM personas WHERE id = ?', [$id]);
        if ($fila === null) {
            throw new \DomainException("No existe la persona {$id}.");
        }
        [$tabla, $columna] = explode('.', $campo);

        if ($tabla === 'sucesos') {
            if ($suceso === null) {
                throw new \DomainException("{$campo} es de un suceso: falta --suceso=N (ver `php bin/persona.php acceso {$id}`).");
            }
            $this->comprobarSuceso($id, $suceso);
            $donde = $suceso;
        } else {
            if ($suceso !== null) {
                throw new \DomainException("--suceso solo va con los campos de sucesos, no con {$campo}.");
            }
            $donde = $id;
        }

        $actual = $this->db->fetchValue("SELECT {$columna} FROM {$tabla} WHERE id = ?", [$donde]);
        if ((string)$actual === $nuevo) {
            throw new \DomainException("{$campo} ya tiene ese valor: no hay nada que corregir.");
        }

        $antes = $this->oculta($id);
        if ($this->db->execute("UPDATE {$tabla} SET {$columna} = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?", [$nuevo, $donde]) !== 1) {
            throw new \DomainException("El cambio no tocó exactamente una fila: no se hizo nada.");
        }
        $despues = $this->oculta($id);

        // Recording validates the pedido too (it must be about this persona), so a bad --pedido stops the change.
        $correccion = (new SupresionRepository($this->db))->rectificar($id, $campo, $pedido);

        return [
            'campo' => $campo,
            'aplicada' => $aplicar,
            'antes_oculta' => $antes,
            'despues_oculta' => $despues,
            'correccion' => $aplicar ? $correccion : null,
            'aviso_grupo' => $campo === 'personas.apellidos' ? $this->avisoDelGrupo($fila['grupo_id'], $nuevo) : null,
        ];
    }

    /**
     * Whether a visitor is shown the persona as hidden (alive, private or withdrawn): the same condition every page uses.
     */
    private function oculta(int $id): bool
    {
        return (bool)$this->db->fetchValue(
            'SELECT CASE WHEN ' . $this->privacidad->sqlOculta('p') . ' THEN 1 ELSE 0 END FROM personas p WHERE p.id = ?',
            [$id]
        );
    }

    /** @throws \DomainException unless $suceso is an event this persona is the only subject of */
    private function comprobarSuceso(int $id, int $suceso): void
    {
        if ($this->db->fetchValue('SELECT 1 FROM sucesos WHERE id = ?', [$suceso]) === null) {
            throw new \DomainException("No existe el suceso {$suceso}.");
        }
        $principales = array_map('intval', array_column($this->db->fetchAll(
            'SELECT persona_id FROM suceso_participantes WHERE suceso_id = ? AND rol = ?',
            [$suceso, Privacidad::ROL_PRINCIPAL]
        ), 'persona_id'));
        if (!in_array($id, $principales, true)) {
            throw new \DomainException("La persona {$id} no es el sujeto del suceso {$suceso}.");
        }
        if (count($principales) !== 1) {
            throw new \DomainException("El suceso {$suceso} tiene más de un sujeto: también son datos de las otras personas. Se corrige a mano (docs/SOLICITUDES.md).");
        }
    }

    /**
     * The grupo is the surname the pages group her under, and it is not changed here: when the new surnames no longer
     * hold it, the owner is told to look (bin/fusionar.php grupo, or personas.grupo_id by hand).
     */
    private function avisoDelGrupo(mixed $grupoId, string $apellidos): ?string
    {
        if ($grupoId === null) {
            return null;
        }
        $grupo = (string)$this->db->fetchValue('SELECT apellido FROM grupos WHERE id = ?', [(int)$grupoId]);
        $palabras = preg_split('/\s+/u', mb_strtolower($apellidos), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $grupo !== '' && !in_array(mb_strtolower($grupo), $palabras, true)
            ? "Sigue en el grupo «{$grupo}», que ya no está en sus apellidos: revisa personas.grupo_id."
            : null;
    }

    /** @throws \DomainException when the value is not valid for the field */
    private static function normalizar(string $campo, string $valor): string
    {
        if (!mb_check_encoding($valor, 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}]/u', $valor) === 1) {
            throw new \DomainException('El valor tiene caracteres de control o no es UTF-8.');
        }
        $valor = trim($valor);

        switch ($campo) {
            case 'personas.nombres':
            case 'personas.apellidos':
                if ($valor === '' || mb_strlen($valor) > self::MAX_NOMBRE) {
                    throw new \DomainException("{$campo} lleva de 1 a " . self::MAX_NOMBRE . ' caracteres.');
                }

                return $valor;
            case 'personas.sexo':
                if (!in_array($valor, ['F', 'M', 'X'], true)) {
                    throw new \DomainException('personas.sexo es F, M o X.');
                }

                return $valor;
            case 'sucesos.fecha':
                $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
                if ($fecha === false || $fecha->format('Y-m-d') !== $valor || $valor > date('Y-m-d') || $valor < '1000-01-01') {
                    throw new \DomainException('sucesos.fecha es una fecha AAAA-MM-DD, entre el año 1000 y hoy.');
                }

                return $valor;
            default:
                if ($valor === '' || mb_strlen($valor) > self::MAX_DESCRIPCION) {
                    throw new \DomainException('sucesos.descripcion lleva de 1 a ' . self::MAX_DESCRIPCION . ' caracteres.');
                }

                return $valor;
        }
    }
}
