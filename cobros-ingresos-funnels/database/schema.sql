-- Esquema del sistema de cobros, ingresos, pagos y funnel de conversion
-- Motor: PostgreSQL.

DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS intentos_login;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS boletas;
DROP TABLE IF EXISTS usuarios_funnel;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios_sistema;
DROP TABLE IF EXISTS paises;
DROP TABLE IF EXISTS monedas;

-- Monedas (ISO 4217). tasa_a_usd = cuantos USD vale 1 unidad de esa moneda,
-- para poder consolidar montos multi-moneda en los reportes agregados.
-- Son tasas estaticas de referencia cargadas por el seed, no un feed en vivo.
CREATE TABLE monedas (
    codigo CHAR(3) PRIMARY KEY,
    nombre TEXT NOT NULL,
    simbolo TEXT NOT NULL,
    tasa_a_usd NUMERIC(18, 8) NOT NULL
);

-- Paises (ISO 3166-1), cada uno con su moneda oficial principal.
CREATE TABLE paises (
    codigo CHAR(2) PRIMARY KEY,
    nombre TEXT NOT NULL,
    moneda_codigo CHAR(3) NOT NULL REFERENCES monedas(codigo)
);

-- Usuarios que pueden entrar al panel (login).
CREATE TABLE usuarios_sistema (
    id SERIAL PRIMARY KEY,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    segmento TEXT NOT NULL DEFAULT 'general',
    fecha_alta DATE NOT NULL,
    pais_codigo CHAR(2) NOT NULL REFERENCES paises(codigo),
    ciudad TEXT NOT NULL,
    idioma TEXT NOT NULL,
    genero TEXT NOT NULL,
    fecha_nacimiento DATE NOT NULL
);

-- Cada fila es un usuario que entro al funnel de adquisicion.
-- Las fechas de cada etapa quedan NULL hasta que el usuario la alcanza;
-- si fecha_conversion no es NULL, el usuario paso a ser cliente (cliente_id).
-- El perfil (pais/ciudad/idioma/genero/nacimiento) se captura una sola vez
-- por persona y viaja con ella si se convierte en cliente.
CREATE TABLE usuarios_funnel (
    id SERIAL PRIMARY KEY,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    canal TEXT NOT NULL,
    pais_codigo CHAR(2) NOT NULL REFERENCES paises(codigo),
    ciudad TEXT NOT NULL,
    idioma TEXT NOT NULL,
    genero TEXT NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    fecha_visita DATE NOT NULL,
    fecha_registro DATE,
    fecha_lead DATE,
    fecha_conversion DATE,
    cliente_id INTEGER REFERENCES clientes(id)
);

-- Ingresos devengados (boletas de venta al usuario final, no facturas
-- fiscales). El estado de cobro se calcula dinamicamente a partir de los
-- pagos aplicados, no se guarda aqui. monto esta expresado en moneda_codigo
-- (normalmente la moneda del pais del cliente).
CREATE TABLE boletas (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    concepto TEXT NOT NULL,
    monto NUMERIC(14, 2) NOT NULL,
    moneda_codigo CHAR(3) NOT NULL REFERENCES monedas(codigo),
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    anulada BOOLEAN NOT NULL DEFAULT FALSE
);

-- Cobros reales (caja): solo pagos que el usuario nos hace a nosotros.
-- boleta_id es opcional: permite registrar anticipos o pagos sueltos no
-- ligados a una boleta puntual.
CREATE TABLE pagos (
    id SERIAL PRIMARY KEY,
    boleta_id INTEGER REFERENCES boletas(id),
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    monto NUMERIC(14, 2) NOT NULL,
    moneda_codigo CHAR(3) NOT NULL REFERENCES monedas(codigo),
    fecha_pago DATE NOT NULL,
    metodo TEXT NOT NULL DEFAULT 'transferencia',
    anulada BOOLEAN NOT NULL DEFAULT FALSE
);

-- Quien hizo que, para trazabilidad de altas/ediciones/anulaciones.
CREATE TABLE auditoria (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios_sistema(id),
    accion TEXT NOT NULL,
    entidad TEXT NOT NULL,
    entidad_id INTEGER NOT NULL,
    detalle TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);

-- Fuerza bruta en el login: cuenta intentos fallidos por email y bloquea
-- temporalmente despues de varios seguidos.
CREATE TABLE intentos_login (
    email TEXT PRIMARY KEY,
    intentos INTEGER NOT NULL DEFAULT 0,
    bloqueado_hasta TIMESTAMP,
    ultimo_intento TIMESTAMP NOT NULL DEFAULT now()
);

CREATE INDEX idx_clientes_pais ON clientes(pais_codigo);
CREATE INDEX idx_boletas_cliente ON boletas(cliente_id);
CREATE INDEX idx_boletas_fecha_emision ON boletas(fecha_emision);
CREATE INDEX idx_pagos_boleta ON pagos(boleta_id);
CREATE INDEX idx_pagos_cliente ON pagos(cliente_id);
CREATE INDEX idx_pagos_fecha ON pagos(fecha_pago);
CREATE INDEX idx_funnel_fechas ON usuarios_funnel(fecha_visita, fecha_registro, fecha_lead, fecha_conversion);
CREATE INDEX idx_funnel_pais ON usuarios_funnel(pais_codigo);
CREATE INDEX idx_auditoria_creado_en ON auditoria(creado_en DESC);
CREATE INDEX idx_auditoria_entidad ON auditoria(entidad, entidad_id);
