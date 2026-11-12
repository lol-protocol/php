-- 018: el historial de las tasas de cambio.
--
-- monedas.tasa_a_usd guarda solo la tasa de hoy: cada vez que bin/actualizar_tasas.php la
-- actualiza, la anterior se pierde. Con eso no se puede responder «a cuanto estaba el peso
-- chileno hace un mes», ni ver cuanto se movio una moneda, ni darse cuenta de que una
-- actualizacion trajo un salto raro (la 011 lo dice: «la base no guarda un historial de
-- cotizaciones»). Esta tabla guarda una fila cada vez que cambia la tasa de una moneda, o la
-- fecha de su cotizacion, o su fuente.
--
-- Como se llena: un trigger AFTER INSERT OR UPDATE sobre monedas. Igual que la tasa del dia de la
-- 011, cubre la app, bin/actualizar_tasas.php, los scripts y un UPDATE hecho a mano, sin que
-- ninguno tenga que acordarse. Un UPDATE que no cambia nada de eso (por ejemplo, el de la 016
-- sobre las columnas de reemplazo) no agrega filas.
--
-- Las columnas repiten las de monedas, con el mismo significado (ver la 009):
--   tasa_a_usd:  cuantos dolares vale una unidad de la moneda.
--   cotizada_en: la fecha de la cotizacion que informo la fuente. NULL es una tasa de ejemplo de
--                la 005: nadie la actualizo todavia. Una fila de ejemplo no se compara con una real.
--   fuente:      el servicio del que salio.
--   registrada_en: cuando se anoto aqui. Lo pone la base, y no es lo mismo que cotizada_en.
--
-- Lo que ya hay: esta migracion anota UNA fila por moneda con lo que tiene hoy. Las tasas que
-- tuvo antes no se pueden recuperar: el historial empieza hoy.
--
-- Lo que esto NO hace: las boletas, los pagos y las notas de credito siguen guardando su propia
-- tasa (011), y una fila cargada hoy con fecha de hace tres meses sigue quedando con la tasa de hoy,
-- no con la de esa fecha. El historial permitiria cotizar con la de la fecha, pero eso cambiaria las
-- cifras de los reportes y se decide aparte.
--
-- No se borra nada ni se limpia solo: son unas pocas filas por moneda y por dia, de las que solo se
-- lee la de una moneda a la vez.
--
-- IF NOT EXISTS / OR REPLACE / DROP ... IF EXISTS: la migracion corre en cada despliegue contra bases con
-- datos reales. La fila inicial de cada moneda solo se anota si la tabla esta vacia, para no repetirla.

CREATE TABLE IF NOT EXISTS historial_de_tasas (
    id            BIGSERIAL PRIMARY KEY,
    moneda_codigo CHAR(3) NOT NULL REFERENCES monedas (codigo),
    tasa_a_usd    NUMERIC(18, 8) NOT NULL CHECK (tasa_a_usd > 0),
    cotizada_en   TIMESTAMPTZ,
    fuente        TEXT,
    registrada_en TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Lo unico que se pide es el historial de una moneda, de la mas reciente a la mas antigua.
CREATE INDEX IF NOT EXISTS historial_de_tasas_por_moneda ON historial_de_tasas (moneda_codigo, id DESC);

INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente)
SELECT codigo, tasa_a_usd, tasa_actualizada_en, tasa_fuente
FROM monedas
WHERE NOT EXISTS (SELECT 1 FROM historial_de_tasas)
ORDER BY codigo;

CREATE OR REPLACE FUNCTION anotar_tasa_de_cambio() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'UPDATE'
       AND NEW.tasa_a_usd IS NOT DISTINCT FROM OLD.tasa_a_usd
       AND NEW.tasa_actualizada_en IS NOT DISTINCT FROM OLD.tasa_actualizada_en
       AND NEW.tasa_fuente IS NOT DISTINCT FROM OLD.tasa_fuente THEN
        RETURN NULL;
    END IF;

    INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente)
    VALUES (NEW.codigo, NEW.tasa_a_usd, NEW.tasa_actualizada_en, NEW.tasa_fuente);

    RETURN NULL;
END;
$$;

DROP TRIGGER IF EXISTS monedas_anotar_tasa ON monedas;
CREATE TRIGGER monedas_anotar_tasa
    AFTER INSERT OR UPDATE OF tasa_a_usd, tasa_actualizada_en, tasa_fuente ON monedas
    FOR EACH ROW EXECUTE FUNCTION anotar_tasa_de_cambio();
