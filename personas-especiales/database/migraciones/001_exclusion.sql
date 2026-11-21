-- Registro de exclusiones de personas (PostgreSQL). Fase 1.
-- Lo que guarda: quién se excluye, quién lo pidió, con qué prueba y en qué estado.
-- No guarda categorías de la persona (religión, origen, pertenencia a un grupo).

CREATE TABLE exclusion (
    id          TEXT PRIMARY KEY CHECK (length(btrim(id)) BETWEEN 1 AND 64),
    persona_id  TEXT NOT NULL CHECK (length(btrim(persona_id)) BETWEEN 1 AND 64),
    solicitante TEXT NOT NULL
                CHECK (solicitante IN ('persona', 'familiar_fallecido', 'apoderado')),
    -- Lista cerrada, sin texto libre: un campo abierto invita a guardar lo que no debe (ver Motivo.php).
    motivo      TEXT NOT NULL
                CHECK (motivo IN ('privacidad_personal', 'seguridad', 'asociacion_con_familiar', 'otro')),
    prueba      TEXT NULL CHECK (prueba IS NULL OR length(btrim(prueba)) BETWEEN 1 AND 1000),
    estado      TEXT NOT NULL
                CHECK (estado IN ('pendiente', 'activa', 'rechazada', 'revocada')),
    creada_en   TIMESTAMPTZ NOT NULL,
    cambiada_en TIMESTAMPTZ NOT NULL,
    CHECK (cambiada_en >= creada_en),
    -- Una exclusión activa siempre tiene la prueba con la que se verificó.
    CHECK (estado <> 'activa' OR prueba IS NOT NULL)
);

-- Búsqueda de las exclusiones que hoy aplican a una persona (Fase 2).
CREATE INDEX exclusion_persona_activa
    ON exclusion (persona_id)
    WHERE estado = 'activa';
