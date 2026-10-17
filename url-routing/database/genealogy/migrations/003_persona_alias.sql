-- Ids que dejaron de existir porque su persona se fusionó con otra (un
-- duplicado). La URL vieja (/{id}/ y sus acciones) redirige con un 301 a la
-- persona que quedó, en vez de dar 404 a los enlaces y marcadores que ya
-- circulan.
--
-- Quien fusione dos personas inserta aquí el id que desaparece, y además
-- apunta a la que queda los alias que señalaban a la que desaparece
-- (UPDATE persona_alias SET persona_id = <queda> WHERE persona_id = <desaparece>):
-- la clave foránea no deja borrar una persona a la que todavía apunta un alias.
CREATE TABLE persona_alias (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 1000000000 AND 9999999999),
    persona_id BIGINT NOT NULL REFERENCES personas (id)
);

CREATE INDEX persona_alias_persona_idx ON persona_alias (persona_id);
