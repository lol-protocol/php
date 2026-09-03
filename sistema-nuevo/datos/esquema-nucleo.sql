-- Esquema PostgreSQL: entidades núcleo. Se corre después de esquema.sql
-- (referencia paises/monedas/tipos_accion, que viven ahí).

CREATE TABLE usuarios (
    id          CHAR(4) PRIMARY KEY, -- 'u001'
    nombre      VARCHAR(120) NOT NULL,
    pais_codigo CHAR(2) NOT NULL REFERENCES paises(codigo),
    edad        SMALLINT NOT NULL CHECK (edad BETWEEN 0 AND 130),
    genero      CHAR(1) NOT NULL CHECK (genero IN ('M', 'F', 'O'))
);

-- Cuentas del backoffice (quién puede iniciar sesión en el panel), separado de
-- "usuarios" (que son el sujeto de la analítica, no operadores del sistema).
CREATE TABLE administradores (
    id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario    VARCHAR(60) UNIQUE NOT NULL,
    clave_hash VARCHAR(255) NOT NULL, -- password_hash() de PHP, nunca texto plano
    creado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE acciones (
    id             CHAR(6) PRIMARY KEY, -- 'a00001'
    usuario_id     CHAR(4) NOT NULL REFERENCES usuarios(id),
    tipo_clave     VARCHAR(30) NOT NULL REFERENCES tipos_accion(clave),
    marca_temporal TIMESTAMP NOT NULL, -- UTC, 'Y-m-d H:i:s'
    duracion_ms    INTEGER NOT NULL CHECK (duracion_ms >= 0),
    ruta           VARCHAR(200) NOT NULL,

    -- Origen de la sesión
    ip             VARCHAR(45) NOT NULL, -- IPv4 o IPv6
    ip_pais_codigo CHAR(2) NULL REFERENCES paises(codigo), -- puede no coincidir con usuarios.pais_codigo
    ip_hora_local  TIME NULL,
    ip_proveedor   VARCHAR(120) NULL,

    -- Solo en payment/refund
    monto_local DECIMAL(14,2) NULL,
    moneda_codigo CHAR(3) NULL REFERENCES monedas(codigo),
    monto_usd   DECIMAL(14,2) NULL,

    -- Solo en review_submit/support_ticket
    comentario TEXT NULL,

    -- Solo en api_call
    endpoint    VARCHAR(160) NULL,
    codigo_http SMALLINT NULL CHECK (codigo_http BETWEEN 100 AND 599),

    -- Solo en file_upload
    tamano_archivo_kb DECIMAL(10,1) NULL
);

CREATE INDEX idx_acciones_usuario_tiempo ON acciones (usuario_id, marca_temporal);
CREATE INDEX idx_acciones_tipo ON acciones (tipo_clave);
