-- Personas cuyos datos se suprimieron (bin/persona.php suprimir). Guarda solo lo
-- necesario para probar que se hizo y para volver a hacerlo si algún día se restaura una
-- copia de seguridad vieja (bin/persona.php reaplicar): el id que desapareció, cuándo, el
-- pedido de retiro que lo originó si lo hubo, y cuántas filas de cada tipo se quitaron.
-- NO guarda el nombre ni nada con lo que se pueda reconocer a la persona.
CREATE TABLE supresiones_persona (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 1000000000 AND 9999999999),
    suprimida_en TIMESTAMP NOT NULL,
    pedido_id BIGINT REFERENCES pedidos_retiro (id),
    resumen TEXT NOT NULL
);
