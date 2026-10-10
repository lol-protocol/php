<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a request may see about people who may still be alive.
 *
 * A persona is presumed alive when it has no 'defuncion' event and its birth
 * is less than ANIOS_VIVA years old; personas.viva overrides that either way
 * (TRUE = alive, FALSE = dead, NULL = deduce). A persona with no birth date and
 * no death date is NOT presumed alive: nothing says so, and hiding every
 * undated ancestor would hollow out the catalog (mark the rare living one with
 * personas.viva instead).
 *
 * Only the owner (see AccessPolicy) sees everything. For anyone else:
 *  - personas() hides a living persona's name, sex, surname group and who
 *    contributed it (the name reads NOMBRE_OCULTO), but keeps its id and its
 *    parents' ids so family trees keep their shape;
 *  - a living persona is also left out of everything that is derived from
 *    names or events and could be searched, sorted or counted: the search,
 *    surname groups, birthplace lists and organization members;
 *  - sucesos() leaves out any event that has a living participant, and
 *    registros() any record that documents one, because their dates, places and
 *    descriptions would otherwise give the person away.
 *
 * The methods return SQL table expressions that REPLACE the tables, so a query
 * cannot forget to hide something: it only ever sees the hidden version. For
 * the owner they are the tables themselves. Everything interpolated here comes
 * from this class (a date it formats, constants), never from a request.
 */
final class Privacidad
{
    /** A persona born less than this many years ago, with no death recorded, is presumed alive. */
    public const ANIOS_VIVA = 110;

    /** What a living persona's name reads as. */
    public const NOMBRE_OCULTO = 'Persona viva';

    private function __construct(
        private readonly bool $ocultaVivas,
        private readonly string $umbral,
    ) {
    }

    /** What a visitor sees. $hoy exists so tests don't depend on today's date. */
    public static function publica(?\DateTimeImmutable $hoy = null): self
    {
        $hoy ??= new \DateTimeImmutable('today');

        return new self(true, $hoy->modify('-' . self::ANIOS_VIVA . ' years')->format('Y-m-d'));
    }

    /** What the owner sees: everything. */
    public static function propietario(): self
    {
        return new self(false, '');
    }

    public static function paraSolicitud(): self
    {
        return AccessPolicy::esPropietario() ? self::propietario() : self::publica();
    }

    public function ocultaVivas(): bool
    {
        return $this->ocultaVivas;
    }

    /** First day that counts as "born recently enough to be alive": births after it are presumed alive. */
    public function umbral(): string
    {
        return $this->umbral;
    }

    /**
     * SQL boolean: the persona aliased $q is presumed alive. Every inner alias
     * derives from $q, so it can sit anywhere in a query without clashing.
     */
    public function sqlViva(string $q): string
    {
        self::alias($q);

        return "COALESCE({$q}.viva, (("
            . "NOT EXISTS (SELECT 1 FROM suceso_participantes {$q}_dp JOIN sucesos {$q}_ds ON {$q}_ds.id = {$q}_dp.suceso_id"
            . " WHERE {$q}_dp.persona_id = {$q}.id AND {$q}_ds.tipo = 'defuncion')"
            . " AND (SELECT MIN({$q}_ns.fecha) FROM suceso_participantes {$q}_np JOIN sucesos {$q}_ns ON {$q}_ns.id = {$q}_np.suceso_id"
            . " WHERE {$q}_np.persona_id = {$q}.id AND {$q}_ns.tipo = 'nacimiento') > '{$this->umbral}'"
            . ') IS TRUE))';
    }

    /**
     * personas, as this request may see them: same columns plus `oculta`
     * (true when the persona is alive and hidden). Hidden rows keep id, padre_id
     * and madre_id so trees keep their shape; their name, sex, surname group and
     * contributor are replaced.
     */
    public function personas(): string
    {
        if (!$this->ocultaVivas) {
            return '(SELECT q.*, FALSE AS oculta FROM personas q)';
        }

        $oculta = $this->sqlViva('q');

        return "(SELECT b.id,
                CASE WHEN b.oculta THEN '" . self::NOMBRE_OCULTO . "' ELSE b.nombres END AS nombres,
                CASE WHEN b.oculta THEN '' ELSE b.apellidos END AS apellidos,
                CASE WHEN b.oculta THEN NULL ELSE b.sexo END AS sexo,
                b.padre_id, b.madre_id,
                CASE WHEN b.oculta THEN NULL ELSE b.grupo_id END AS grupo_id,
                CASE WHEN b.oculta THEN NULL ELSE b.aportado_por END AS aportado_por,
                CASE WHEN b.oculta THEN NULL ELSE b.creado_en END AS creado_en,
                b.oculta
           FROM (SELECT q.*, {$oculta} AS oculta FROM personas q) b)";
    }

    /** sucesos, without any event that has a living participant. */
    public function sucesos(): string
    {
        if (!$this->ocultaVivas) {
            return 'sucesos';
        }

        return '(SELECT e.* FROM sucesos e WHERE NOT EXISTS ('
            . 'SELECT 1 FROM suceso_participantes ep JOIN personas eq ON eq.id = ep.persona_id'
            . ' WHERE ep.suceso_id = e.id AND ' . $this->sqlViva('eq') . '))';
    }

    /** registros, without any record that documents an event with a living participant. */
    public function registros(): string
    {
        if (!$this->ocultaVivas) {
            return 'registros';
        }

        return '(SELECT g.* FROM registros g WHERE NOT EXISTS ('
            . 'SELECT 1 FROM registro_sucesos gs JOIN suceso_participantes gp ON gp.suceso_id = gs.suceso_id'
            . ' JOIN personas gq ON gq.id = gp.persona_id'
            . ' WHERE gs.registro_id = g.id AND ' . $this->sqlViva('gq') . '))';
    }

    /**
     * $expresion (a scalar subquery about the persona aliased $alias, built on
     * personas()) when it may be seen, NULL when the persona is hidden.
     */
    public function siVisible(string $expresion, string $alias = 'p'): string
    {
        self::alias($alias);

        return $this->ocultaVivas
            ? "CASE WHEN {$alias}.oculta THEN NULL ELSE ({$expresion}) END"
            : "({$expresion})";
    }

    private static function alias(string $alias): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $alias) !== 1) {
            throw new \InvalidArgumentException("Alias SQL inválido: {$alias}");
        }
    }
}
