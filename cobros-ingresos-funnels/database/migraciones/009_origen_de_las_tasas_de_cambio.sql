-- 009: de donde viene cada tasa de cambio y desde cuando.
--
-- monedas.tasa_a_usd lo cargo la 005 con tasas de ejemplo, y todos los totales en
-- USD (Dashboard, Cobros, Pagos, Cohortes) las usan. database/actualizar_tasas.php
-- las reemplaza por las reales; estas dos columnas dicen cuales ya lo estan:
--
--   tasa_actualizada_en: cuando es la cotizacion (la fecha que informa la fuente, o
--                        el momento de la descarga si no informa ninguna). NULL es
--                        una tasa de ejemplo de la 005: nadie la actualizo todavia.
--   tasa_fuente:         el servicio del que salio (ej. open.er-api.com).
--
-- Con ellas las pantallas avisan cuando una tasa es de ejemplo o lleva mas de 7 dias
-- sin actualizarse, y dicen de que fecha son las que usan.
--
-- No cambia ninguna tasa: todas quedan como estaban, con las dos columnas en NULL.
-- IF NOT EXISTS: la migracion corre en cada despliegue contra bases con datos reales.

ALTER TABLE monedas ADD COLUMN IF NOT EXISTS tasa_actualizada_en TIMESTAMPTZ;
ALTER TABLE monedas ADD COLUMN IF NOT EXISTS tasa_fuente TEXT;
