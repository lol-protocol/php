-- 003: una sola definicion de "cuanto se pago de cada boleta".
--
-- La subconsulta de lo pagado (la suma de los pagos no anulados) estaba
-- copiada en cuatro consultas: el detalle, el listado y la ficha de
-- BoletaRepository, y la antiguedad de cartera de IngresosRepository. Cuando
-- cambie la regla -por ejemplo, con devoluciones parciales- se cambia aca y
-- en ningun otro lado.
--
-- Son subconsultas correlacionadas y no un JOIN contra un GROUP BY de pagos a
-- proposito: asi Postgres las resuelve fila por fila con idx_pagos_boleta, y
-- traer una sola boleta no obliga a agregar la tabla de pagos entera. La
-- vista se aplana dentro de la consulta que la usa, asi que una columna que
-- no se pide (ej. primer_pago) no se calcula.
--
-- Ojo: b.* se expande al crear la vista. Si una migracion futura le agrega
-- columnas a boletas, tiene que recrear esta vista para que las incluya.
CREATE VIEW boletas_con_saldo AS
SELECT con_pagado.*, con_pagado.monto - con_pagado.pagado AS saldo
FROM (
    SELECT b.*,
           COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado,
           (SELECT MIN(p.fecha_pago) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada) AS primer_pago
    FROM boletas b
) con_pagado;
