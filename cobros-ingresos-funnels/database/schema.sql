-- Esquema del sistema de cobros, ingresos, pagos y funnel de conversion

DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS boletas;
DROP TABLE IF EXISTS usuarios_funnel;
DROP TABLE IF EXISTS clientes;

CREATE TABLE clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    segmento TEXT NOT NULL DEFAULT 'general',
    fecha_alta TEXT NOT NULL,
    pais TEXT NOT NULL,
    ciudad TEXT NOT NULL,
    idioma TEXT NOT NULL,
    genero TEXT NOT NULL,
    fecha_nacimiento TEXT NOT NULL
);

-- Cada fila es un usuario que entro al funnel de adquisicion.
-- Las fechas de cada etapa quedan NULL hasta que el usuario la alcanza;
-- si fecha_conversion no es NULL, el usuario paso a ser cliente (cliente_id).
-- El perfil (pais/ciudad/idioma/genero/nacimiento) se captura una sola vez
-- por persona y viaja con ella si se convierte en cliente.
CREATE TABLE usuarios_funnel (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    canal TEXT NOT NULL,
    pais TEXT NOT NULL,
    ciudad TEXT NOT NULL,
    idioma TEXT NOT NULL,
    genero TEXT NOT NULL,
    fecha_nacimiento TEXT NOT NULL,
    fecha_visita TEXT NOT NULL,
    fecha_registro TEXT,
    fecha_lead TEXT,
    fecha_conversion TEXT,
    cliente_id INTEGER REFERENCES clientes(id)
);

-- Ingresos devengados (boletas de venta al usuario final, no facturas
-- fiscales). El estado de cobro se calcula dinamicamente a partir de los
-- pagos aplicados, no se guarda aqui.
CREATE TABLE boletas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    concepto TEXT NOT NULL,
    monto REAL NOT NULL,
    fecha_emision TEXT NOT NULL,
    fecha_vencimiento TEXT NOT NULL
);

-- Cobros reales (caja): solo pagos que el usuario nos hace a nosotros.
-- boleta_id es opcional: permite registrar anticipos o pagos sueltos no
-- ligados a una boleta puntual.
CREATE TABLE pagos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    boleta_id INTEGER REFERENCES boletas(id),
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    monto REAL NOT NULL,
    fecha_pago TEXT NOT NULL,
    metodo TEXT NOT NULL DEFAULT 'transferencia'
);

CREATE INDEX idx_boletas_cliente ON boletas(cliente_id);
CREATE INDEX idx_boletas_fecha_emision ON boletas(fecha_emision);
CREATE INDEX idx_pagos_boleta ON pagos(boleta_id);
CREATE INDEX idx_pagos_cliente ON pagos(cliente_id);
CREATE INDEX idx_pagos_fecha ON pagos(fecha_pago);
CREATE INDEX idx_funnel_fechas ON usuarios_funnel(fecha_visita, fecha_registro, fecha_lead, fecha_conversion);
