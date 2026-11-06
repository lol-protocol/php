-- Ids de grupo (apellido) que dejaron de existir porque dos grupos eran el mismo (Garcia y García): la URL
-- vieja (/{id}/ y sus acciones) redirige con un 301 al grupo que quedó, en vez de dar 404 a los enlaces que
-- ya circulan. Es lo mismo que persona_alias (003) para las personas.
--
-- Quien fusione dos grupos (bin/fusionar.php grupo) inserta aquí el id que desaparece, y apunta al que queda los
-- alias que señalaban al que desaparece: la clave foránea no deja borrar un grupo al que todavía apunta un alias.
CREATE TABLE grupo_alias (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 100000 AND 999999),
    grupo_id BIGINT NOT NULL REFERENCES grupos (id)
);

CREATE INDEX grupo_alias_grupo_idx ON grupo_alias (grupo_id);
