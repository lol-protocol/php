-- 003: los eventos de una orden se numeran (orden), en vez de identificarse por la hora.
--
-- La clave de orden_eventos era (orden_id, ocurrido_en), con la hora a segundos: dos pasos de la misma orden en
-- el mismo segundo (pagada y enviada cargadas por un script, p. ej.) chocaban con la clave, y a la hora no se
-- le podía pedir el orden entre ellos. pedido_retiro_eventos (genealogía, migración 005) ya lo resolvió con una
-- columna orden que numera los eventos de cada pedido (1, 2, 3...): esto hace lo mismo con los de una orden.
--
-- La tabla se rehace porque la clave primaria cambia (SQLite no lo permite con ALTER TABLE). Los eventos que ya
-- hay se numeran por su hora, a igual hora en el orden en que estén guardados.
CREATE TABLE orden_eventos_nueva (
    orden_id BIGINT NOT NULL REFERENCES ordenes (id),
    orden INTEGER NOT NULL CHECK (orden > 0),
    ocurrido_en TIMESTAMP NOT NULL,
    estado TEXT NOT NULL,
    detalle TEXT,
    PRIMARY KEY (orden_id, orden)
);

INSERT INTO orden_eventos_nueva (orden_id, orden, ocurrido_en, estado, detalle)
    SELECT orden_id, ROW_NUMBER() OVER (PARTITION BY orden_id ORDER BY ocurrido_en), ocurrido_en, estado, detalle
      FROM orden_eventos;

DROP TABLE orden_eventos;

ALTER TABLE orden_eventos_nueva RENAME TO orden_eventos;
