-- Correcciones hechas a la ficha de una persona (bin/persona.php rectificacion). Guarda solo
-- lo necesario para probar que se hizo: cuál persona, cuándo, qué campo ('tabla.columna') y el
-- pedido de retiro que lo originó si lo hubo. NO guarda el valor viejo ni el nuevo: la nota no
-- debe conservar justo el dato que se quiso corregir.
-- persona_id no es llave foránea a propósito: si la persona se suprime después, estas filas se
-- conservan (como supresiones_persona) y no deben estorbar el borrado.
CREATE TABLE rectificaciones_persona (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    persona_id BIGINT NOT NULL CHECK (persona_id BETWEEN 1000000000 AND 9999999999),
    corregida_en TIMESTAMP NOT NULL,
    que TEXT NOT NULL,
    pedido_id BIGINT REFERENCES pedidos_retiro (id)
);
CREATE INDEX rectificaciones_persona_persona ON rectificaciones_persona (persona_id);
