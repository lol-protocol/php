-- Solicitudes de acceso, rectificación o supresión de los datos de una persona, hechas en /s/{id}/ (cualquiera, sin
-- cuenta). Se atienden con bin/solicitudes.php y el recorrido de docs/SOLICITUDES.md: recibir una solicitud no entrega,
-- corrige ni borra nada, lo hace una persona después de comprobar quién pide.
--
-- Es una tabla aparte de pedidos_retiro: aquella pide ocultar una página y tiene un recorrido cerrado (pendiente,
-- oculto, retirado, rechazado); esta pide algo de una persona y se cierra como atendida o rechazada.
--
-- persona_id no es llave foránea a propósito: si la persona se suprime después, la solicitud que la pidió se conserva
-- (hasta que bin/solicitudes.php --depurar borre el correo y el texto) y no debe estorbar el borrado.
-- contacto, detalle y nota son datos de quien pidió: --depurar los borra al pasar la conservación (depurado_en).
CREATE TABLE solicitudes_persona (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    persona_id BIGINT NOT NULL CHECK (persona_id BETWEEN 1000000000 AND 9999999999),
    derecho TEXT NOT NULL CHECK (derecho IN ('acceso', 'rectificacion', 'supresion')),
    detalle TEXT NOT NULL,
    contacto TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente', 'atendida', 'rechazada')),
    creado_en TIMESTAMP NOT NULL,
    cerrada_en TIMESTAMP,
    nota TEXT,
    depurado_en TIMESTAMP
);

CREATE INDEX solicitudes_persona_persona_idx ON solicitudes_persona (persona_id, estado);
