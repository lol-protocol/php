-- Esquema de referencia: cómo se vería este mismo modelo de datos en una base
-- relacional, en vez de los archivos JSON/CSV que usa hoy el sistema. Dialecto
-- MySQL/MariaDB (portable a PostgreSQL con cambios menores: ENUM -> CHECK,
-- AUTO_INCREMENT -> GENERATED ALWAYS AS IDENTITY). No es lo que corre en vivo.
--
-- Orden de creación: catálogos primero (de los que todo lo demás depende),
-- "acciones" al final (referencia a casi todo). Datos de ejemplo en
-- esquema-datos-ejemplo.sql.

CREATE TABLE monedas (
    codigo       CHAR(3) PRIMARY KEY,       -- 'USD', 'EUR', 'ARS'...
    tasa_a_usd   DECIMAL(14,6) NOT NULL      -- unidades de esta moneda por 1 USD (fija/ilustrativa)
);

CREATE TABLE paises (
    codigo             CHAR(2) PRIMARY KEY,  -- ISO 3166-1 alpha-2
    nombre             VARCHAR(100) NOT NULL,
    moneda_codigo      CHAR(3) NOT NULL REFERENCES monedas(codigo),
    offset_utc_horas   DECIMAL(4,2) NOT NULL DEFAULT 0  -- huso horario aproximado, sin DST
);

CREATE TABLE grupos_paises (
    clave    VARCHAR(20) PRIMARY KEY,   -- 'otan', 'brics', 'latam'...
    etiqueta VARCHAR(80) NOT NULL
);

CREATE TABLE grupo_pais (
    grupo_clave  VARCHAR(20) NOT NULL REFERENCES grupos_paises(clave),
    pais_codigo  CHAR(2) NOT NULL REFERENCES paises(codigo),
    PRIMARY KEY (grupo_clave, pais_codigo)
);

CREATE TABLE tipos_accion (
    clave             VARCHAR(30) PRIMARY KEY,  -- 'login', 'payment', 'api_call'...
    etiqueta          VARCHAR(80) NOT NULL,
    ruta_base         VARCHAR(160) NOT NULL,    -- archivo/ruta del backend
    duracion_base_ms  INT UNSIGNED NOT NULL,
    tiene_monto       BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE usuarios (
    id       CHAR(4) PRIMARY KEY,   -- 'u001'
    nombre   VARCHAR(120) NOT NULL,
    pais_codigo  CHAR(2) NOT NULL REFERENCES paises(codigo),
    edad     TINYINT UNSIGNED NOT NULL,
    genero   ENUM('M', 'F', 'O') NOT NULL
);

-- Cuentas del backoffice (quién puede iniciar sesión en el panel), separado de
-- "usuarios" (que son el sujeto de la analítica, no operadores del sistema).
CREATE TABLE administradores (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario     VARCHAR(60) UNIQUE NOT NULL,
    clave_hash  VARCHAR(255) NOT NULL,   -- password_hash() de PHP, nunca texto plano
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE acciones (
    id                CHAR(6) PRIMARY KEY,   -- 'a00001'
    usuario_id        CHAR(4) NOT NULL REFERENCES usuarios(id),
    tipo_clave        VARCHAR(30) NOT NULL REFERENCES tipos_accion(clave),
    marca_temporal    DATETIME NOT NULL,     -- UTC, 'Y-m-d H:i:s'
    duracion_ms       INT UNSIGNED NOT NULL,
    ruta              VARCHAR(200) NOT NULL,

    -- Origen de la sesión
    ip                VARCHAR(45) NOT NULL,  -- IPv4 o IPv6
    ip_pais_codigo    CHAR(2) NULL REFERENCES paises(codigo),  -- puede no coincidir con usuarios.pais_codigo
    ip_hora_local     TIME NULL,
    ip_proveedor      VARCHAR(120) NULL,

    -- Solo en payment/refund
    monto_local       DECIMAL(14,2) NULL,
    moneda_codigo     CHAR(3) NULL REFERENCES monedas(codigo),
    monto_usd         DECIMAL(14,2) NULL,

    -- Solo en review_submit/support_ticket
    comentario        TEXT NULL,

    -- Solo en api_call
    endpoint          VARCHAR(160) NULL,
    codigo_http       SMALLINT UNSIGNED NULL,

    -- Solo en file_upload
    tamano_archivo_kb DECIMAL(10,1) NULL,

    INDEX idx_acciones_usuario_tiempo (usuario_id, marca_temporal),
    INDEX idx_acciones_tipo (tipo_clave)
);
