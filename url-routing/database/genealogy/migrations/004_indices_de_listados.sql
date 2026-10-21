-- Indexes for the listing and search pages (/b/{digitos}/...), which show 50 rows at a time in a
-- fixed order: personas by apellidos, nombres; sucesos and registros by fecha.
--
-- With an index in exactly that order the database reads rows in order and stops at the first 50
-- that pass the visibility filter (see Privacidad), so what a page costs no longer grows with the
-- size of the catalog. Without it, each page sorted the whole table first. The older
-- personas_apellidos_idx is a prefix of the new personas index and could be dropped, but it stays
-- so this migration only adds.
CREATE INDEX personas_orden_idx ON personas (apellidos, nombres, id);
CREATE INDEX sucesos_fecha_idx ON sucesos (fecha, id);
CREATE INDEX registros_fecha_idx ON registros (fecha, id);
