-- Esquema del sistema de cobros, ingresos, pagos y funnel de conversion

DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS facturas;
DROP TABLE IF EXISTS usuarios_funnel;
DROP TABLE IF EXISTS clientes;

CREATE TABLE clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    segmento TEXT NOT NULL DEFAULT 'general',
    fecha_alta TEXT NOT NULL
);

-- Cada fila es un usuario que entro al funnel de adquisicion.
-- Las fechas de cada etapa quedan NULL hasta que el usuario la alcanza;
-- si fecha_conversion no es NULL, el usuario paso a ser cliente (cliente_id).
CREATE TABLE usuarios_funnel (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    canal TEXT NOT NULL,
    fecha_visita TEXT NOT NULL,
    fecha_registro TEXT,
    fecha_lead TEXT,
    fecha_conversion TEXT,
    cliente_id INTEGER REFERENCES clientes(id)
);

-- Ingresos devengados (facturacion). El estado de cobro se calcula
-- dinamicamente a partir de los pagos aplicados, no se guarda aqui.
CREATE TABLE facturas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    concepto TEXT NOT NULL,
    monto REAL NOT NULL,
    fecha_emision TEXT NOT NULL,
    fecha_vencimiento TEXT NOT NULL
);

-- Cobros reales (caja). factura_id es opcional: permite registrar
-- anticipos o pagos sueltos no ligados a una factura puntual.
CREATE TABLE pagos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    factura_id INTEGER REFERENCES facturas(id),
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    monto REAL NOT NULL,
    fecha_pago TEXT NOT NULL,
    metodo TEXT NOT NULL DEFAULT 'transferencia'
);

CREATE INDEX idx_facturas_cliente ON facturas(cliente_id);
CREATE INDEX idx_facturas_fecha_emision ON facturas(fecha_emision);
CREATE INDEX idx_pagos_factura ON pagos(factura_id);
CREATE INDEX idx_pagos_cliente ON pagos(cliente_id);
CREATE INDEX idx_pagos_fecha ON pagos(fecha_pago);
CREATE INDEX idx_funnel_fechas ON usuarios_funnel(fecha_visita, fecha_registro, fecha_lead, fecha_conversion);
