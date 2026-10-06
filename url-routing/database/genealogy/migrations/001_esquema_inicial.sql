-- Genealogy site schema. Portable SQL: runs unchanged on SQLite and PostgreSQL.
--
-- Primary keys are the public URL ids, so each table's CHECK pins its id to
-- the digit width the router assigns that type (persona = 10 digits, etc.):
-- an id that doesn't fit would produce a URL that resolves to another type.

CREATE TABLE usuarios (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    email TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Places are addressed by their code path ("mx", "mx/jal", "mx/jal/gdl"),
-- which is exactly the URL, so the path itself is the key.
CREATE TABLE lugares (
    ruta TEXT PRIMARY KEY,
    padre_ruta TEXT REFERENCES lugares (ruta),
    codigo TEXT NOT NULL,
    nombre TEXT NOT NULL,
    nivel SMALLINT NOT NULL CHECK (nivel BETWEEN 1 AND 3)
);

CREATE INDEX lugares_padre_idx ON lugares (padre_ruta);

CREATE TABLE grupos (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 100000 AND 999999),
    apellido TEXT NOT NULL,
    origen TEXT,
    descripcion TEXT
);

CREATE INDEX grupos_apellido_idx ON grupos (apellido);

CREATE TABLE personas (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 1000000000 AND 9999999999),
    nombres TEXT NOT NULL,
    apellidos TEXT NOT NULL,
    sexo CHAR(1) CHECK (sexo IN ('F', 'M', 'X')),
    padre_id BIGINT REFERENCES personas (id),
    madre_id BIGINT REFERENCES personas (id),
    grupo_id BIGINT REFERENCES grupos (id),
    aportado_por BIGINT REFERENCES usuarios (id),
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX personas_padre_idx ON personas (padre_id);
CREATE INDEX personas_madre_idx ON personas (madre_id);
CREATE INDEX personas_grupo_idx ON personas (grupo_id);
CREATE INDEX personas_apellidos_idx ON personas (apellidos);

-- Non-parental links (spouses, godparents...). Parent/child lives on
-- personas.padre_id / madre_id so ancestry is a single recursive query.
CREATE TABLE vinculos (
    persona_a BIGINT NOT NULL REFERENCES personas (id),
    persona_b BIGINT NOT NULL REFERENCES personas (id),
    tipo TEXT NOT NULL CHECK (tipo IN ('conyuge', 'padrino', 'tutor', 'otro')),
    PRIMARY KEY (persona_a, persona_b, tipo),
    CHECK (persona_a <> persona_b)
);

CREATE INDEX vinculos_b_idx ON vinculos (persona_b);

-- Events are the single source of truth for dates: birth and death shown on
-- a persona come from its 'nacimiento' / 'defuncion' events, never from
-- duplicated columns that could disagree.
CREATE TABLE sucesos (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 100000000 AND 999999999),
    tipo TEXT NOT NULL,
    fecha DATE,
    lugar_ruta TEXT REFERENCES lugares (ruta),
    descripcion TEXT
);

CREATE INDEX sucesos_lugar_idx ON sucesos (lugar_ruta);

CREATE TABLE suceso_participantes (
    suceso_id BIGINT NOT NULL REFERENCES sucesos (id),
    persona_id BIGINT NOT NULL REFERENCES personas (id),
    rol TEXT NOT NULL,
    PRIMARY KEY (suceso_id, persona_id, rol)
);

CREATE INDEX suceso_participantes_persona_idx ON suceso_participantes (persona_id);

CREATE TABLE organizaciones (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999),
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL,
    lugar_ruta TEXT REFERENCES lugares (ruta)
);

CREATE TABLE organizacion_miembros (
    organizacion_id BIGINT NOT NULL REFERENCES organizaciones (id),
    persona_id BIGINT NOT NULL REFERENCES personas (id),
    rol TEXT NOT NULL,
    desde DATE,
    hasta DATE,
    PRIMARY KEY (organizacion_id, persona_id, rol)
);

CREATE INDEX organizacion_miembros_persona_idx ON organizacion_miembros (persona_id);

-- Documentary records (certificates, censuses...). "fuente" is the citation
-- of where the original is held — shown by the registro's action 1.
CREATE TABLE registros (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 10000000 AND 99999999),
    titulo TEXT NOT NULL,
    tipo TEXT NOT NULL,
    fecha DATE,
    lugar_ruta TEXT REFERENCES lugares (ruta),
    organizacion_id BIGINT REFERENCES organizaciones (id),
    fuente TEXT,
    url TEXT,
    aportado_por BIGINT REFERENCES usuarios (id)
);

CREATE INDEX registros_organizacion_idx ON registros (organizacion_id);

CREATE TABLE registro_sucesos (
    registro_id BIGINT NOT NULL REFERENCES registros (id),
    suceso_id BIGINT NOT NULL REFERENCES sucesos (id),
    PRIMARY KEY (registro_id, suceso_id)
);

CREATE INDEX registro_sucesos_suceso_idx ON registro_sucesos (suceso_id);

-- A colección is a user's family tree: a curated set of personas.
CREATE TABLE colecciones (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 1000000 AND 9999999),
    nombre TEXT NOT NULL,
    descripcion TEXT,
    usuario_id BIGINT NOT NULL REFERENCES usuarios (id),
    publica BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX colecciones_usuario_idx ON colecciones (usuario_id);

CREATE TABLE coleccion_personas (
    coleccion_id BIGINT NOT NULL REFERENCES colecciones (id),
    persona_id BIGINT NOT NULL REFERENCES personas (id),
    PRIMARY KEY (coleccion_id, persona_id)
);

CREATE INDEX coleccion_personas_persona_idx ON coleccion_personas (persona_id);
