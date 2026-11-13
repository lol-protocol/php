-- Entregas con clave de lo que el sitio guarda de una persona (bin/persona.php entrega, /e/{token}/): el propietario,
-- DESPUÉS de comprobar quién pide (docs/SOLICITUDES.md), crea un enlace y una clave, y los manda por dos medios distintos.
-- La página muestra lo que `acceso` imprime, en una página y no en un JSON, incluido lo que el sitio oculta a un visitante.
--
-- Del enlace solo se guarda el hash SHA-256 de su token (32 bytes al azar): quien lea esta tabla no puede armar el enlace.
-- La clave se guarda con password_hash(). Ninguna de las dos se vuelve a mostrar después de crearla.
-- intentos cuenta las claves malas seguidas (se reserva ANTES de comprobar la clave, para que varias pruebas a la vez no
-- esquiven el tope); vistas, las veces que se abrió con la clave buena. Pasado el plazo, o el tope de intentos o de vistas,
-- el enlace deja de existir (responde 404, igual que uno inventado).
-- persona_id no es llave foránea a propósito: si la persona se suprime, la entrega no debe estorbar el borrado (y ya no
-- muestra nada: no hay persona).
CREATE TABLE entregas_persona (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    persona_id BIGINT NOT NULL CHECK (persona_id BETWEEN 1000000000 AND 9999999999),
    token_hash TEXT NOT NULL UNIQUE,
    clave_hash TEXT NOT NULL,
    creada_en TIMESTAMP NOT NULL,
    vence_en TIMESTAMP NOT NULL,
    intentos INTEGER NOT NULL DEFAULT 0 CHECK (intentos >= 0),
    vistas INTEGER NOT NULL DEFAULT 0 CHECK (vistas >= 0),
    ultima_vista_en TIMESTAMP
);

CREATE INDEX entregas_persona_persona_idx ON entregas_persona (persona_id);
