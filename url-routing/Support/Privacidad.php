<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a request may see about people who may still be alive, or who belong
 * only to private collections.
 *
 * A persona is presumed alive when she has no 'defuncion' event of her own and
 * her birth is less than ANIOS_VIVA years old; personas.viva overrides that
 * either way (TRUE = alive, FALSE = dead, NULL = deduce). Her own event is one
 * where she is the ROL_PRINCIPAL: registering someone's death, or being a
 * parent or a godparent at a birth, is being there, not dying or being born.
 * A persona with no birth date and no death date is NOT presumed alive: nothing
 * says so, and hiding every undated ancestor would hollow out the catalog (mark
 * the rare living one with personas.viva instead).
 *
 * A persona is private when she is in at least one colección and in none that
 * is public (colecciones.publica). A private tree hides its collection, and
 * this hides the people only it holds: otherwise anyone with a persona's id
 * could read what a private tree was meant to keep. A persona who is in no
 * colección at all is not private: nothing says she belongs to anyone.
 *
 * Only the owner (see AccessPolicy) sees everything. For anyone else:
 *  - personas() hides a hidden persona's name, sex, surname group and who
 *    contributed it (the name reads NOMBRE_OCULTO if she may be alive,
 *    NOMBRE_PRIVADO if she is only private), but keeps its id and its
 *    parents' ids so family trees keep their shape;
 *  - a hidden persona is also left out of everything that is derived from
 *    names or events and could be searched, sorted or counted: the search,
 *    surname groups, birthplace lists and organization members;
 *  - sucesos() leaves out any event that has a hidden participant, and
 *    registros() any record that documents one, because their dates, places and
 *    descriptions would otherwise give the person away. What a persona who is
 *    visible shows of such an event is only its year (sqlEventoDe()): her own
 *    birth or death is still hers to state, but not the day or the place, which
 *    would point at the living person who was there. Lists of births by place
 *    leave it out altogether, since the place is what they list.
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

    /** What the name of a persona who is only in private collections reads as. */
    public const NOMBRE_PRIVADO = 'Persona privada';

    /** The role of the persona an event is about: the one who is born or who dies, not a parent, a witness or a declarant. */
    public const ROL_PRINCIPAL = 'principal';

    private function __construct(
        private readonly bool $ocultaVivas,
        private readonly string $umbral,
        private readonly bool $porFila = false,
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

    /**
     * The same view, with the "does this persona have such a row" checks of sqlViva() and sqlPrivada()
     * written as one lookup per persona, `(SELECT 1 ... LIMIT 1) IS NOT NULL`, instead of EXISTS.
     * PostgreSQL turns an EXISTS into a hash of the whole table behind it, built every time the query
     * runs: that pays off when thousands of personas are checked and costs the size of the table when
     * only a page of them is, which is what a search for the first 50 does. SQLite runs both forms the
     * same way. Both answer exactly the same; the choice is only the cost, so only the queries that
     * check few personas should ask for this one (see PersonaRepository::buscar()).
     */
    public function porFila(): self
    {
        return new self($this->ocultaVivas, $this->umbral, true);
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
     *
     * Only her own death counts as one (she is its ROL_PRINCIPAL): a living
     * declarant at someone else's death used to read as dead, and so as safe to
     * show. Her birth is still the earliest of any birth she is in, her own or
     * a child's, on purpose: a parent with no birth of her own recorded is taken
     * to be no younger than her child, so she stays hidden while the child is.
     */
    public function sqlViva(string $q): string
    {
        self::alias($q);

        return "COALESCE({$q}.viva, (("
            . $this->existePersona($q, 'dp', 'suceso_participantes',
                join: " JOIN sucesos {$q}_ds ON {$q}_ds.id = {$q}_dp.suceso_id",
                extra: "{$q}_dp.rol = '" . self::ROL_PRINCIPAL . "' AND {$q}_ds.tipo = 'defuncion'",
                negado: true,
            )
            . " AND (SELECT MIN({$q}_ns.fecha) FROM suceso_participantes {$q}_np JOIN sucesos {$q}_ns ON {$q}_ns.id = {$q}_np.suceso_id"
            . " WHERE {$q}_np.persona_id = {$q}.id AND {$q}_ns.tipo = 'nacimiento') > '{$this->umbral}'"
            . ') IS TRUE))';
    }

    /**
     * SQL boolean: the persona aliased $q is in some colección and in none that
     * is public. Same alias rule as sqlViva().
     */
    public function sqlPrivada(string $q): string
    {
        self::alias($q);

        return '(' . $this->existePersona($q, 'mp', 'coleccion_personas')
            . ' AND ' . $this->existePersona($q, 'pp', 'coleccion_personas',
                join: " JOIN colecciones {$q}_pc ON {$q}_pc.id = {$q}_pp.coleccion_id",
                extra: "{$q}_pc.publica",
                negado: true,
            ) . ')';
    }

    /**
     * SQL boolean: the persona aliased $q is hidden from a visitor, whether she may be alive, is only
     * private, or was withdrawn after a pedido de retiro (see sqlRetirada()).
     */
    public function sqlOculta(string $q): string
    {
        return '(' . $this->sqlViva($q) . ' OR ' . $this->sqlPrivada($q) . ' OR ' . self::sqlRetirada($q) . ')';
    }

    /** SQL boolean: the persona aliased $q has a pedido de retiro that hides or withdraws her. */
    public static function sqlRetirada(string $q): string
    {
        self::alias($q);

        return \App\Repositories\Genealogy\RetiroRepository::sqlRetirado('persona', "{$q}.id");
    }

    /**
     * SQL boolean: the persona aliased $q may be seen by this request, which is exactly `NOT oculta` of
     * personas(). For the owner nobody is hidden and it is a plain TRUE. It is meant to be asked of the
     * real table (personas q), to pick which rows a query returns before personas() masks them: a query
     * that asks it of the real table and then reads personas() for those rows cannot show more than
     * personas() allows, and it computes `oculta` for the rows it looks at instead of for the whole table.
     */
    public function sqlVisible(string $q): string
    {
        return $this->ocultaVivas ? 'NOT ' . $this->sqlOculta($q) : 'TRUE';
    }

    /**
     * personas, as this request may see them: same columns plus `oculta`
     * (true when the persona is hidden: she may be alive, or is only in private
     * collections). Hidden rows keep id, padre_id and madre_id so trees keep
     * their shape; their name, sex, surname group and contributor are replaced.
     */
    public function personas(): string
    {
        if (!$this->ocultaVivas) {
            return '(SELECT q.*, FALSE AS oculta FROM personas q)';
        }

        $viva = $this->sqlViva('q');
        $privada = $this->sqlPrivada('q');
        $retirada = self::sqlRetirada('q');

        return "(SELECT b.id,
                CASE WHEN b.oculta THEN CASE WHEN b.viva_presunta THEN '" . self::NOMBRE_OCULTO . "' ELSE '" . self::NOMBRE_PRIVADO . "' END
                     ELSE b.nombres END AS nombres,
                CASE WHEN b.oculta THEN '' ELSE b.apellidos END AS apellidos,
                CASE WHEN b.oculta THEN NULL ELSE b.sexo END AS sexo,
                b.padre_id, b.madre_id,
                CASE WHEN b.oculta THEN NULL ELSE b.grupo_id END AS grupo_id,
                CASE WHEN b.oculta THEN NULL ELSE b.aportado_por END AS aportado_por,
                CASE WHEN b.oculta THEN NULL ELSE b.creado_en END AS creado_en,
                b.oculta
           FROM (SELECT x.*, (x.viva_presunta OR x.privada OR x.retirada) AS oculta
                   FROM (SELECT q.*, {$viva} AS viva_presunta, {$privada} AS privada, {$retirada} AS retirada FROM personas q) x) b)";
    }

    /** sucesos, without any event that has a hidden participant. */
    public function sucesos(): string
    {
        if (!$this->ocultaVivas) {
            return 'sucesos';
        }

        return '(SELECT e.* FROM sucesos e WHERE ' . $this->sqlSucesoVisible('e') . ' AND NOT '
            . \App\Repositories\Genealogy\RetiroRepository::sqlRetirado('suceso', 'e.id') . ')';
    }

    /**
     * SQL boolean: the event aliased $q has no hidden participant, so all of it
     * may be seen. sucesos() is exactly the events for which this holds.
     */
    public function sqlSucesoVisible(string $q): string
    {
        self::alias($q);

        if (!$this->ocultaVivas) {
            return 'TRUE';
        }

        return "NOT EXISTS (SELECT 1 FROM suceso_participantes {$q}_vp JOIN personas {$q}_vq ON {$q}_vq.id = {$q}_vp.persona_id"
            . " WHERE {$q}_vp.suceso_id = {$q}.id AND " . $this->sqlOculta("{$q}_vq") . ')';
    }

    /** registros, without any record that documents an event with a hidden participant. */
    public function registros(): string
    {
        if (!$this->ocultaVivas) {
            return 'registros';
        }

        return '(SELECT g.* FROM registros g WHERE ' . $this->sqlRegistroVisible('g') . ' AND NOT '
            . \App\Repositories\Genealogy\RetiroRepository::sqlRetirado('registro', 'g.id') . ')';
    }

    /**
     * SQL boolean: the record aliased $q documents no event with a hidden participant (registros() is
     * those that also have no pedido de retiro of their own).
     */
    public function sqlRegistroVisible(string $q): string
    {
        self::alias($q);

        if (!$this->ocultaVivas) {
            return 'TRUE';
        }

        return 'NOT EXISTS ('
            . "SELECT 1 FROM registro_sucesos {$q}_rs JOIN suceso_participantes {$q}_rp ON {$q}_rp.suceso_id = {$q}_rs.suceso_id"
            . " JOIN personas {$q}_rq ON {$q}_rq.id = {$q}_rp.persona_id"
            . " WHERE {$q}_rs.registro_id = {$q}.id AND " . $this->sqlOculta("{$q}_rq") . ')';
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

    /**
     * Scalar subquery: $campo of the earliest $tipo event ('nacimiento',
     * 'defuncion') of the persona aliased $alias. $campo is 'fecha',
     * 'lugar_ruta' or 'lugar_nombre' (the place's name).
     *
     * Only an event she is the ROL_PRINCIPAL of counts, so a persona's summary
     * shows her own birth and death and never one she merely took part in. If
     * the event has a hidden participant (see sucesos()) the date comes back as
     * its year alone and the place as NULL. Wrap it in siVisible(): a persona
     * who is herself hidden gets NULL.
     */
    public function sqlEventoDe(string $alias, string $tipo, string $campo): string
    {
        self::alias($alias);
        self::palabra($tipo, 'tipo de suceso');
        [$valor, $unir] = match ($campo) {
            'fecha' => [$this->fechaVisible('s'), ''],
            'lugar_ruta' => [$this->soloSiVisible('s', 's.lugar_ruta'), ''],
            'lugar_nombre' => [$this->soloSiVisible('s', 'l.nombre'), ' LEFT JOIN lugares l ON l.ruta = s.lugar_ruta'],
            default => throw new \InvalidArgumentException("Campo de suceso inválido: {$campo}"),
        };

        return "SELECT {$valor} FROM sucesos s JOIN suceso_participantes sp ON sp.suceso_id = s.id{$unir}"
            . " WHERE sp.persona_id = {$alias}.id AND sp.rol = '" . self::ROL_PRINCIPAL . "' AND s.tipo = '{$tipo}'"
            . ' ORDER BY s.fecha LIMIT 1';
    }

    /**
     * The date of the event aliased $q: whole when the event may be seen, only
     * its year (as text) when a hidden participant hides it. Cast to text on
     * both sides so PostgreSQL, which will not mix a date and a text in one
     * CASE, and SQLite agree.
     */
    private function fechaVisible(string $q): string
    {
        if (!$this->ocultaVivas) {
            return "{$q}.fecha";
        }

        return 'CASE WHEN ' . $this->sqlSucesoVisible($q) . " THEN CAST({$q}.fecha AS TEXT)"
            . " ELSE SUBSTR(CAST({$q}.fecha AS TEXT), 1, 4) END";
    }

    /** $expresion when the event aliased $q may be seen, NULL when a hidden participant hides it. */
    private function soloSiVisible(string $q, string $expresion): string
    {
        return $this->ocultaVivas
            ? 'CASE WHEN ' . $this->sqlSucesoVisible($q) . " THEN {$expresion} END"
            : $expresion;
    }

    /**
     * `[NOT ]EXISTS (SELECT 1 FROM $tabla {$q}_$sufijo[$join] WHERE {$q}_$sufijo.persona_id = {$q}.id[ AND $extra])`
     * — the shape sqlViva()'s own-death check and both of sqlPrivada()'s checks repeat (or, in
     * porFila() mode, the same test as a one-row lookup). $join and $extra are written as given,
     * already built from constants here, never from a request.
     */
    private function existePersona(string $q, string $sufijo, string $tabla, string $join = '', string $extra = '', bool $negado = false): string
    {
        $alias = "{$q}_{$sufijo}";
        $condicion = "{$alias}.persona_id = {$q}.id" . ($extra === '' ? '' : " AND {$extra}");

        if ($this->porFila) {
            return "((SELECT 1 FROM {$tabla} {$alias}{$join} WHERE {$condicion} LIMIT 1) IS " . ($negado ? '' : 'NOT ') . 'NULL)';
        }

        return ($negado ? 'NOT ' : '') . "EXISTS (SELECT 1 FROM {$tabla} {$alias}{$join} WHERE {$condicion})";
    }

    private static function alias(string $alias): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $alias) !== 1) {
            throw new \InvalidArgumentException("Alias SQL inválido: {$alias}");
        }
    }

    /** A value that gets interpolated into SQL must be a plain word: it comes from a caller, never from a request. */
    private static function palabra(string $valor, string $que): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $valor) !== 1) {
            throw new \InvalidArgumentException("Valor SQL inválido para {$que}: {$valor}");
        }
    }
}
