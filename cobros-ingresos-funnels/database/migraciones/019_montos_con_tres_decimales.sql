-- 019: los montos guardan hasta tres decimales (NUMERIC(15, 3)).
--
-- La 014 dejo dicho el limite: el dinar kuwaiti, el bahreini, el omani, el tunecino, el jordano, el iraqui y el
-- libio tienen tres decimales en ISO 4217 (el fils, el baisa...), pero boletas.monto, pagos.monto y
-- notas_credito.monto eran NUMERIC(14, 2) y esas monedas se guardaban con dos. Ahora la columna tiene tres, y la
-- aplicacion usa los decimales de cada moneda (MonedaRepository::decimales(): 0, 2 o 3) para pedir, validar y mostrar.
--
-- El tope no baja: NUMERIC(15, 3) sigue dejando doce digitos enteros (Validacion::MONTO_MAXIMO es
-- 999.999.999.999,999). Lo ya guardado no cambia de valor: «1500.50» pasa a «1500.500».
--
-- boletas_con_saldo se basa en boletas.monto y PostgreSQL no deja cambiarle el tipo mientras la vista exista: se
-- borra y se vuelve a crear con la misma definicion de la 003. Su b.* se expande otra vez y ahora trae tambien las
-- columnas que la 011 le agrego a boletas (tasa_a_usd...): solo suma columnas, ninguna consulta usa una por posicion.

DROP VIEW IF EXISTS boletas_con_saldo;

ALTER TABLE boletas        ALTER COLUMN monto TYPE NUMERIC(15, 3);
ALTER TABLE pagos          ALTER COLUMN monto TYPE NUMERIC(15, 3);
ALTER TABLE notas_credito  ALTER COLUMN monto TYPE NUMERIC(15, 3);

CREATE VIEW boletas_con_saldo AS
SELECT con_pagado.*, con_pagado.monto - con_pagado.pagado AS saldo
FROM (
    SELECT b.*,
           COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado,
           (SELECT MIN(p.fecha_pago) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada) AS primer_pago
    FROM boletas b
) con_pagado;
