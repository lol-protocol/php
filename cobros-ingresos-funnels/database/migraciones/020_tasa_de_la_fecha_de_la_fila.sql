-- 020: una fila nueva toma la tasa de SU fecha, no la de hoy (hacia adelante).
--
-- La 011 graba en cada boleta, pago y nota de credito la tasa de cambio del dia en que se carga, y avisa de su
-- limite: una fila cargada hoy con fecha de hace tres meses queda con la tasa de hoy. Desde la 018 hay un
-- historial de tasas (historial_de_tasas), asi que ahora se puede cotizar con la de esa fecha.
--
-- Regla (el trigger sellar_tasa_del_dia, solo cuando la fila viene SIN tasa, igual que antes):
--   * la fecha de la fila es boletas.fecha_emision, pagos.fecha_pago o notas_credito.fecha;
--   * si la fecha es de hoy o futura, la tasa de hoy (monedas.tasa_a_usd): no hay otra cotizacion que mirar;
--   * si es anterior, la ultima cotizacion del historial hecha ese dia o antes (por cotizada_en, en UTC);
--   * si el historial no tiene ninguna de esa fecha o anterior (el historial empieza con la 018, y las tasas de
--     ejemplo, con cotizada_en NULL, no cuentan), la tasa de hoy, como hasta ahora.
-- Una tasa explicita se sigue respetando (una importacion con su propia cotizacion).
--
-- Lo que NO cambia: ninguna fila que ya existe (no se reescribe nada, ningun total historico se mueve), editar una
-- fila no la vuelve a cotizar, y la cartera pendiente se sigue valuando a la tasa de hoy. La nota de credito con
-- pagos sigue tomando el promedio de las tasas de los pagos que devuelve (NotaCreditoRepository).
--
-- Cuidado con lo que esto cambia hacia adelante: dos filas de la misma moneda con fechas distintas ya pueden tener
-- tasas distintas aunque se carguen el mismo dia. Un pago se convierte con la tasa de su fecha de pago, no con la
-- de la boleta, y la diferencia entre las dos es la diferencia de cambio.
--
-- Idempotente: CREATE OR REPLACE. Los triggers de la 011 siguen apuntando a la misma funcion.

CREATE OR REPLACE FUNCTION sellar_tasa_del_dia() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_fecha DATE;
BEGIN
    IF NEW.tasa_a_usd IS NULL THEN
        -- IF y no CASE: PL/pgSQL no deja nombrar un campo que el registro de esa tabla no tiene, ni en una rama que no corre.
        IF TG_TABLE_NAME = 'boletas' THEN
            v_fecha := NEW.fecha_emision;
        ELSIF TG_TABLE_NAME = 'pagos' THEN
            v_fecha := NEW.fecha_pago;
        ELSIF TG_TABLE_NAME = 'notas_credito' THEN
            v_fecha := NEW.fecha;
        END IF;

        IF v_fecha IS NOT NULL AND v_fecha < CURRENT_DATE THEN
            SELECT h.tasa_a_usd INTO NEW.tasa_a_usd
            FROM historial_de_tasas h
            WHERE h.moneda_codigo = NEW.moneda_codigo
              AND h.cotizada_en IS NOT NULL
              AND (h.cotizada_en AT TIME ZONE 'UTC')::date <= v_fecha
            ORDER BY h.cotizada_en DESC, h.id DESC
            LIMIT 1;
        END IF;

        IF NEW.tasa_a_usd IS NULL THEN
            SELECT tasa_a_usd INTO NEW.tasa_a_usd FROM monedas WHERE codigo = NEW.moneda_codigo;
            IF NOT FOUND THEN
                -- Lo mismo que diria la clave foranea de moneda_codigo, que llegaria despues de este trigger.
                RAISE EXCEPTION 'La moneda % no existe: no hay una tasa de cambio para grabar', NEW.moneda_codigo
                    USING ERRCODE = 'foreign_key_violation';
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;
