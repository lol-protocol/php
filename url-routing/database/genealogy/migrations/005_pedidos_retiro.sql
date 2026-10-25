-- Pedidos de retiro. Cualquiera, con cuenta o sin ella, puede pedir que se
-- retire una página (/r/{id}/), y el propietario los revisa en /0/3/. Una
-- página con un pedido en estado 'oculto' o 'retirado' responde 410 a quien no
-- es el propietario (ver Support/Retiros.php). Recibir un pedido no oculta nada
-- por sí solo: lo decide una persona, o cualquiera podría bajar cualquier página.
CREATE TABLE pedidos_retiro (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    tipo TEXT NOT NULL CHECK (tipo IN ('persona', 'suceso', 'registro', 'coleccion', 'grupo', 'organizacion')),
    recurso_id BIGINT NOT NULL CHECK (recurso_id > 0),
    motivo TEXT NOT NULL CHECK (motivo IN ('me_nombra', 'familiar', 'terminos', 'otro')),
    detalle TEXT NOT NULL,
    contacto TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente', 'oculto', 'retirado', 'rechazado')),
    creado_en TIMESTAMP NOT NULL
);

CREATE INDEX pedidos_retiro_recurso_idx ON pedidos_retiro (tipo, recurso_id);

-- Todo lo que pasó con cada pedido, en orden (recibido, oculto, retirado,
-- rechazado) con la nota del propietario. Sólo se agregan filas, nunca se
-- cambian: es lo que hay para responder a un reclamo.
-- orden numera los eventos de cada pedido (1, 2, 3...): dos en el mismo
-- segundo no se pueden ordenar por la hora.
CREATE TABLE pedido_retiro_eventos (
    pedido_id BIGINT NOT NULL REFERENCES pedidos_retiro (id),
    orden INTEGER NOT NULL CHECK (orden > 0),
    ocurrido_en TIMESTAMP NOT NULL,
    estado TEXT NOT NULL CHECK (estado IN ('recibido', 'oculto', 'retirado', 'rechazado')),
    nota TEXT,
    PRIMARY KEY (pedido_id, orden)
);
