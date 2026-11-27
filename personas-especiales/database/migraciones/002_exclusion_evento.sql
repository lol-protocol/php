-- Auditoría: quién hizo qué con cada solicitud, y cuándo. Solo se agrega: no se edita ni se borra.
-- No guarda razones ni pruebas (eso ya está en exclusion); solo el hecho, el actor y la hora.
-- «actor» es un identificador opaco de quien actuó (revisor, o quien pidió); no un nombre.

CREATE TABLE exclusion_evento (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    exclusion_id TEXT NOT NULL REFERENCES exclusion (id),
    tipo         TEXT NOT NULL
                 CHECK (tipo IN ('solicitada', 'verificada', 'rechazada', 'revocada')),
    actor        TEXT NOT NULL CHECK (length(btrim(actor)) BETWEEN 1 AND 64),
    ocurrido_en  TIMESTAMPTZ NOT NULL
);

CREATE INDEX exclusion_evento_exclusion ON exclusion_evento (exclusion_id, id);

CREATE FUNCTION exclusion_evento_solo_agrega() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'exclusion_evento solo admite INSERT (%)', TG_OP;
END
$$;

CREATE TRIGGER exclusion_evento_inmutable
    BEFORE UPDATE OR DELETE ON exclusion_evento
    FOR EACH ROW EXECUTE FUNCTION exclusion_evento_solo_agrega();

CREATE TRIGGER exclusion_evento_sin_truncar
    BEFORE TRUNCATE ON exclusion_evento
    FOR EACH STATEMENT EXECUTE FUNCTION exclusion_evento_solo_agrega();
