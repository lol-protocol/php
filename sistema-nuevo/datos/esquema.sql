-- Esquema PostgreSQL: catálogos (de los que todo lo demás depende). Lo carga
-- datos/generador/cargar-postgres.php al final de generar-datos-semilla.php,
-- así que esto SÍ es lo que corre en vivo (no es solo referencia). Entidades
-- "núcleo" (usuarios, administradores, acciones) en esquema-nucleo.sql, que se
-- corre después de este archivo. Datos de ejemplo sueltos en
-- esquema-datos-ejemplo.sql.

DROP TABLE IF EXISTS acciones CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS administradores CASCADE;
DROP TABLE IF EXISTS tipos_accion CASCADE;
DROP TABLE IF EXISTS grupo_pais CASCADE;
DROP TABLE IF EXISTS grupos_paises CASCADE;
DROP TABLE IF EXISTS paises CASCADE;
DROP TABLE IF EXISTS monedas CASCADE;
DROP TABLE IF EXISTS configuracion_alertas CASCADE;
DROP TABLE IF EXISTS filtros_guardados CASCADE;
DROP TABLE IF EXISTS intentos_login CASCADE;

CREATE TABLE monedas (
    codigo     CHAR(3) PRIMARY KEY,   -- 'USD', 'EUR', 'ARS'...
    tasa_a_usd DECIMAL(14,6) NOT NULL -- unidades de esta moneda por 1 USD (fija/ilustrativa)
);

CREATE TABLE paises (
    codigo           CHAR(2) PRIMARY KEY, -- ISO 3166-1 alpha-2
    nombre           VARCHAR(100) NOT NULL,
    moneda_codigo    CHAR(3) NOT NULL REFERENCES monedas(codigo),
    offset_utc_horas DECIMAL(4,2) NOT NULL DEFAULT 0 -- huso horario aproximado, sin DST
);

CREATE TABLE grupos_paises (
    clave    VARCHAR(20) PRIMARY KEY, -- 'otan', 'brics', 'latam'...
    etiqueta VARCHAR(80) NOT NULL
);

CREATE TABLE grupo_pais (
    grupo_clave VARCHAR(20) NOT NULL REFERENCES grupos_paises(clave),
    pais_codigo CHAR(2) NOT NULL REFERENCES paises(codigo),
    PRIMARY KEY (grupo_clave, pais_codigo)
);

CREATE TABLE tipos_accion (
    clave             VARCHAR(30) PRIMARY KEY, -- 'login', 'payment', 'api_call'...
    etiqueta          VARCHAR(80) NOT NULL,
    ruta_base         VARCHAR(160) NOT NULL,   -- archivo/ruta del backend
    duracion_base_ms  INTEGER NOT NULL CHECK (duracion_base_ms >= 0),
    tiene_monto       BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE configuracion_alertas (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
);

CREATE TABLE filtros_guardados (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    scope VARCHAR(50) NOT NULL,      -- 'all_countries', 'grupo:otan', 'pais:US', etc.
    age_min INTEGER,
    age_max INTEGER,
    gender VARCHAR(10),              -- 'all', 'm', 'f', 'o'
    tipo_accion VARCHAR(30)          -- 'all', 'login', 'payment', etc.
);

-- Protección contra fuerza bruta en /api/login: un contador por IP, no por
-- usuario (hay un solo admin, así que "por usuario" no aportaría nada).
CREATE TABLE intentos_login (
    ip               VARCHAR(45) PRIMARY KEY,
    intentos         INTEGER NOT NULL DEFAULT 0,
    ultimo_intento   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta  TIMESTAMP NULL
);

INSERT INTO configuracion_alertas VALUES
  ('alerta_ip_pais', 'true'),
  ('alerta_cambio_pais', 'true'),
  ('alerta_logins_fallidos', 'true'),
  ('umbral_sensibilidad', '50');  -- 0-100, por defecto 50%
