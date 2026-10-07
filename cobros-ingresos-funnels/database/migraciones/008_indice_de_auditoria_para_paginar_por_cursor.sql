-- 008: el indice que usa la paginacion por cursor de Auditoria.
--
-- Auditoria pagina con "las filas que vienen despues de esta" (creado_en, id) en
-- vez de LIMIT/OFFSET y COUNT(*), que leian la tabla de punta a punta en las
-- paginas del fondo y para el total (la tabla solo crece: cada alta, edicion y
-- anulacion agrega una fila). La consulta es
--     WHERE (creado_en, id) < (cursor) ORDER BY creado_en DESC, id DESC LIMIT 26
-- y este indice, con las dos columnas en el mismo orden que el ORDER BY, la
-- resuelve leyendo 26 entradas del indice sin importar cuantas filas haya ni a que
-- profundidad. El id va porque varias filas comparten creado_en (todo lo que se
-- escribe en una transaccion tiene el mismo now()) y, sin desempate, el cursor
-- se saltaria o repetiria filas.
--
-- Reemplaza a idx_auditoria_creado_en (solo creado_en): este lo cubre, y dejar los
-- dos es pagar dos indices en cada alta, edicion y anulacion por lo mismo.
--
-- IF NOT EXISTS / IF EXISTS: la migracion corre en cada despliegue contra bases con
-- datos reales y no puede fallar por un indice creado o borrado a mano. Como en la
-- 006, sin CONCURRENTLY: el Migrador corre cada migracion dentro de una transaccion.

CREATE INDEX IF NOT EXISTS idx_auditoria_creado_en_id ON auditoria(creado_en DESC, id DESC);
DROP INDEX IF EXISTS idx_auditoria_creado_en;
