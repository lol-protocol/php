-- 012: cada nota de credito anota cuando se le devolvio la plata al cliente.
--
-- Hasta la 011 una nota de credito era solo el aviso de "esto hay que devolverle al
-- cliente": se emitia al anular una boleta cobrada y quedaba ahi para siempre, sin
-- forma de decir que la devolucion ya se hizo. No habia como saber cuanto se debia
-- todavia.
--
-- Ahora la nota tiene fecha_devolucion: NULL mientras la plata sigue pendiente de
-- devolver, y el dia en que se devolvio cuando se registra desde la app (pantalla
-- Notas de credito). No se guarda un estado aparte: "pendiente" es fecha_devolucion
-- IS NULL, asi no hay dos datos que puedan contradecirse.
--
-- Lo que NO cambia: los reportes de cobros siguen restando la nota el dia en que se
-- emitio (notas_credito.fecha), no el dia en que se devolvio. La nota anula el
-- ingreso desde que la boleta se anula; que la plata salga de la caja despues es
-- otra cosa, y es justo lo que esta columna deja ver. Cambiar el dia en que se
-- netea movia cifras ya cerradas, y nadie lo pidio.
--
-- Las notas que ya existian quedan pendientes (NULL), porque la base no sabe si se
-- devolvieron. Si alguna ya se devolvio, se marca desde la pantalla.
--
-- CHECK: una devolucion no puede ser anterior a la nota. Que tampoco sea futura lo
-- valida la app; la base no puede compararla con "hoy" sin que el CHECK deje de ser
-- inmutable (y un restore lo reevaluaria).
--
-- El indice parcial acelera lo que mira la pantalla y el dashboard: las pendientes,
-- que son pocas frente al historial.

ALTER TABLE notas_credito ADD COLUMN fecha_devolucion DATE;

ALTER TABLE notas_credito DROP CONSTRAINT IF EXISTS notas_credito_devolucion_no_antes_de_la_nota;
ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_devolucion_no_antes_de_la_nota
    CHECK (fecha_devolucion IS NULL OR fecha_devolucion >= fecha);

CREATE INDEX IF NOT EXISTS notas_credito_pendientes ON notas_credito (fecha, id) WHERE fecha_devolucion IS NULL;
