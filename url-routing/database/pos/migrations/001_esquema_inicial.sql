-- POS (Contrastocolor) schema. Portable SQL: runs unchanged on SQLite and
-- PostgreSQL. Catalog ids are the public URL ids, pinned by CHECK to the
-- digit width the router assigns each type. Money is stored in integer
-- cents: no floating-point rounding on prices or totals.

CREATE TABLE usuarios (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    email TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Categories (4 digits), optionally nested.
CREATE TABLE grupos (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 1000 AND 9999),
    nombre TEXT NOT NULL,
    padre_id BIGINT REFERENCES grupos (id)
);

CREATE TABLE productos (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 10000000 AND 99999999),
    nombre TEXT NOT NULL,
    descripcion TEXT,
    precio_centavos INTEGER NOT NULL CHECK (precio_centavos >= 0),
    moneda CHAR(3) NOT NULL DEFAULT 'MXN',
    grupo_id BIGINT REFERENCES grupos (id),
    activo BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE INDEX productos_grupo_idx ON productos (grupo_id);
CREATE INDEX productos_nombre_idx ON productos (nombre);

-- Attributes (5 digits): colors, sizes, materials. "valor" holds e.g. the
-- hex code for a color.
CREATE TABLE atributos (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 10000 AND 99999),
    tipo TEXT NOT NULL CHECK (tipo IN ('color', 'talla', 'material')),
    nombre TEXT NOT NULL,
    valor TEXT
);

CREATE TABLE producto_atributos (
    producto_id BIGINT NOT NULL REFERENCES productos (id),
    atributo_id BIGINT NOT NULL REFERENCES atributos (id),
    PRIMARY KEY (producto_id, atributo_id)
);

CREATE INDEX producto_atributos_atributo_idx ON producto_atributos (atributo_id);

-- Purchasable variants of a product. precio_centavos NULL means "same as
-- the product".
CREATE TABLE variantes (
    sku TEXT PRIMARY KEY,
    producto_id BIGINT NOT NULL REFERENCES productos (id),
    nombre TEXT NOT NULL,
    precio_centavos INTEGER CHECK (precio_centavos >= 0),
    stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0)
);

CREATE INDEX variantes_producto_idx ON variantes (producto_id);

CREATE TABLE variante_atributos (
    sku TEXT NOT NULL REFERENCES variantes (sku),
    atributo_id BIGINT NOT NULL REFERENCES atributos (id),
    PRIMARY KEY (sku, atributo_id)
);

CREATE TABLE etiquetas (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 100000 AND 999999),
    nombre TEXT NOT NULL
);

CREATE TABLE producto_etiquetas (
    producto_id BIGINT NOT NULL REFERENCES productos (id),
    etiqueta_id BIGINT NOT NULL REFERENCES etiquetas (id),
    PRIMARY KEY (producto_id, etiqueta_id)
);

CREATE INDEX producto_etiquetas_etiqueta_idx ON producto_etiquetas (etiqueta_id);

-- Collections / campaigns (7 digits), with an optional validity window.
CREATE TABLE colecciones (
    id BIGINT PRIMARY KEY CHECK (id BETWEEN 1000000 AND 9999999),
    nombre TEXT NOT NULL,
    descripcion TEXT,
    inicio DATE,
    fin DATE
);

CREATE TABLE coleccion_productos (
    coleccion_id BIGINT NOT NULL REFERENCES colecciones (id),
    producto_id BIGINT NOT NULL REFERENCES productos (id),
    orden INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (coleccion_id, producto_id)
);

-- Orders live under /order/{id}/, so their id has no fixed width.
CREATE TABLE ordenes (
    id BIGINT PRIMARY KEY CHECK (id > 0),
    usuario_id BIGINT NOT NULL REFERENCES usuarios (id),
    estado TEXT NOT NULL CHECK (estado IN ('pendiente', 'pagada', 'enviada', 'entregada', 'cancelada', 'devuelta')),
    total_centavos INTEGER NOT NULL CHECK (total_centavos >= 0),
    moneda CHAR(3) NOT NULL DEFAULT 'MXN',
    direccion_envio TEXT,
    numero_guia TEXT,
    creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX ordenes_usuario_idx ON ordenes (usuario_id);

-- Line items keep a copy of name and unit price: an order must keep showing
-- what was paid even if the product is later renamed or repriced.
CREATE TABLE orden_items (
    orden_id BIGINT NOT NULL REFERENCES ordenes (id),
    sku TEXT NOT NULL,
    producto_id BIGINT NOT NULL REFERENCES productos (id),
    nombre TEXT NOT NULL,
    cantidad INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario_centavos INTEGER NOT NULL CHECK (precio_unitario_centavos >= 0),
    PRIMARY KEY (orden_id, sku)
);

-- Shipment tracking history shown by /order/{id}/2/.
CREATE TABLE orden_eventos (
    orden_id BIGINT NOT NULL REFERENCES ordenes (id),
    ocurrido_en TIMESTAMP NOT NULL,
    estado TEXT NOT NULL,
    detalle TEXT,
    PRIMARY KEY (orden_id, ocurrido_en)
);

CREATE TABLE deseos (
    usuario_id BIGINT NOT NULL REFERENCES usuarios (id),
    producto_id BIGINT NOT NULL REFERENCES productos (id),
    agregado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, producto_id)
);

CREATE TABLE direcciones (
    usuario_id BIGINT NOT NULL REFERENCES usuarios (id),
    alias TEXT NOT NULL,
    destinatario TEXT NOT NULL,
    calle TEXT NOT NULL,
    ciudad TEXT NOT NULL,
    codigo_postal TEXT NOT NULL,
    pais CHAR(2) NOT NULL,
    principal BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (usuario_id, alias)
);
