-- Código público de cada orden: lo que va en su URL (/{codigo}/), en vez de
-- su id. El id de una orden es correlativo, así que conocer uno dejaría
-- adivinar los de las demás; el código es aleatorio y de ancho fijo (16
-- dígitos, el largo que el router asocia a "orden").
--
-- Quien cree órdenes debe generarlo con un generador criptográfico, p. ej.
-- random_int(1000000000000000, 9999999999999999), y reintentar si choca con
-- el índice único. Una orden sin código (anterior a esta migración) no tiene URL.
ALTER TABLE ordenes ADD COLUMN codigo BIGINT CHECK (codigo BETWEEN 1000000000000000 AND 9999999999999999);
CREATE UNIQUE INDEX ordenes_codigo_idx ON ordenes (codigo);
