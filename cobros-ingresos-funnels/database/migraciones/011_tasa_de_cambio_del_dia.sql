-- 011: cada boleta, cada pago y cada nota de credito guarda la tasa de cambio de su dia.
--
-- Hasta la 010 todos los totales en USD convertian con monedas.tasa_a_usd, que es la
-- de hoy: cuando la tasa cambiaba (ahora que database/actualizar_tasas.php la
-- actualiza todos los dias), cambiaban tambien los ingresos y los cobros de meses
-- anteriores, y un mes ya cerrado dejaba de dar la misma cifra. Un ingreso de
-- 1.000.000 de pesos no vale lo mismo en USD cuando se facturo que ahora.
--
-- Ahora la tasa queda grabada en la fila cuando se crea, y ya no se mueve. Los
-- reportes de ingresos y cobros (Dashboard, Cobros, Pagos, Cohortes, segmentacion y
-- LTV) suman monto * tasa_a_usd de cada fila. Lo unico que sigue usando la tasa de
-- hoy es la cartera pendiente (lo que todavia se debe), que es plata por cobrar
-- hoy y se valua a la tasa de hoy; es el mismo criterio de una cuenta por cobrar en
-- moneda extranjera.
--
-- Como se llena: un trigger BEFORE INSERT toma la tasa de monedas cuando la fila
-- viene sin ella. Eso cubre la app, el seed, los scripts y cualquier INSERT a mano,
-- sin que ninguno tenga que acordarse; una tasa que venga explicita se respeta (una
-- importacion de datos historicos con su propia cotizacion). No se toca al editar:
-- la moneda de una boleta o de un pago no se puede cambiar, y corregir un monto o
-- una fecha no vuelve a cotizar nada. La nota de credito la pone NotaCreditoRepository
-- como el promedio de las tasas de los pagos que devuelve, para que cobro y
-- devolucion se cancelen en USD; sin pagos, la del dia.
--
-- Lo que esto NO resuelve: una fila cargada hoy con fecha de hace tres meses queda con
-- la tasa de hoy, no con la de esa fecha, porque la base no guarda un historial de
-- cotizaciones. Es cierto tambien para todas las filas que ya existen: esta migracion
-- les graba la tasa que hoy tiene su moneda, asi que no cambia ninguna cifra. Y las
-- tasas siguen siendo de ejemplo hasta la primera corrida de actualizar_tasas.php: esa
-- primera corrida, por moneda, vuelve a expresar las filas que quedaron con la tasa de
-- ejemplo (las que tienen exactamente esa tasa) con la real, y desde entonces ya no
-- se mueven.
--
-- NUMERIC(24, 12): las tasas de monedas (18, 8) caben de sobra; los doce decimales son
-- para el promedio ponderado de las notas de credito.
--
-- Si una moneda tuviera tasa 0 o negativa, la migracion se detiene en el CHECK: esa
-- tasa ya hacia mal todos los totales y hay que corregirla antes (UPDATE monedas SET
-- tasa_a_usd = ... WHERE codigo = ...). Nada queda a medias: cada migracion corre en una
-- transaccion.

ALTER TABLE boletas       ADD COLUMN tasa_a_usd NUMERIC(24, 12);
ALTER TABLE pagos         ADD COLUMN tasa_a_usd NUMERIC(24, 12);
ALTER TABLE notas_credito ADD COLUMN tasa_a_usd NUMERIC(24, 12);

UPDATE boletas       x SET tasa_a_usd = m.tasa_a_usd FROM monedas m WHERE m.codigo = x.moneda_codigo;
UPDATE pagos         x SET tasa_a_usd = m.tasa_a_usd FROM monedas m WHERE m.codigo = x.moneda_codigo;
UPDATE notas_credito x SET tasa_a_usd = m.tasa_a_usd FROM monedas m WHERE m.codigo = x.moneda_codigo;

ALTER TABLE boletas       ALTER COLUMN tasa_a_usd SET NOT NULL;
ALTER TABLE pagos         ALTER COLUMN tasa_a_usd SET NOT NULL;
ALTER TABLE notas_credito ALTER COLUMN tasa_a_usd SET NOT NULL;

ALTER TABLE boletas       DROP CONSTRAINT IF EXISTS boletas_tasa_a_usd_positiva;
ALTER TABLE pagos         DROP CONSTRAINT IF EXISTS pagos_tasa_a_usd_positiva;
ALTER TABLE notas_credito DROP CONSTRAINT IF EXISTS notas_credito_tasa_a_usd_positiva;
ALTER TABLE boletas       ADD CONSTRAINT boletas_tasa_a_usd_positiva       CHECK (tasa_a_usd > 0);
ALTER TABLE pagos         ADD CONSTRAINT pagos_tasa_a_usd_positiva         CHECK (tasa_a_usd > 0);
ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_tasa_a_usd_positiva CHECK (tasa_a_usd > 0);

CREATE OR REPLACE FUNCTION sellar_tasa_del_dia() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.tasa_a_usd IS NULL THEN
        SELECT tasa_a_usd INTO NEW.tasa_a_usd FROM monedas WHERE codigo = NEW.moneda_codigo;
        IF NOT FOUND THEN
            -- Lo mismo que diria la clave foranea de moneda_codigo, que llegaria despues de este trigger.
            RAISE EXCEPTION 'La moneda % no existe: no hay una tasa de cambio para grabar', NEW.moneda_codigo
                USING ERRCODE = 'foreign_key_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS boletas_sellar_tasa ON boletas;
CREATE TRIGGER boletas_sellar_tasa
    BEFORE INSERT ON boletas
    FOR EACH ROW EXECUTE FUNCTION sellar_tasa_del_dia();

DROP TRIGGER IF EXISTS pagos_sellar_tasa ON pagos;
CREATE TRIGGER pagos_sellar_tasa
    BEFORE INSERT ON pagos
    FOR EACH ROW EXECUTE FUNCTION sellar_tasa_del_dia();

DROP TRIGGER IF EXISTS notas_credito_sellar_tasa ON notas_credito;
CREATE TRIGGER notas_credito_sellar_tasa
    BEFORE INSERT ON notas_credito
    FOR EACH ROW EXECUTE FUNCTION sellar_tasa_del_dia();
