-- Datos de ejemplo para esquema.sql: alcanza para ver una fila de cada tipo de
-- acción (login "normal", pago con monto, reseña con comentario, llamada a
-- API con endpoint/código HTTP, subida de archivo) y una IP que no coincide
-- con el país declarado del usuario (fila a000005).

INSERT INTO monedas (codigo, tasa_a_usd) VALUES
    ('USD', 1.0), ('EUR', 0.92), ('ARS', 1400.0), ('ZAR', 18.7), ('CHF', 0.88);

INSERT INTO paises (codigo, nombre, moneda_codigo, offset_utc_horas) VALUES
    ('AR', 'Argentina', 'ARS', -3),
    ('DE', 'Alemania', 'EUR', 1),
    ('ZA', 'Sudáfrica', 'ZAR', 2),
    ('CH', 'Suiza', 'CHF', 1);

INSERT INTO grupos_paises (clave, etiqueta) VALUES
    ('latam', 'LATAM'), ('euro', 'Zona Euro');

INSERT INTO grupo_pais (grupo_clave, pais_codigo) VALUES
    ('latam', 'AR'), ('euro', 'DE');

INSERT INTO tipos_accion (clave, etiqueta, ruta_base, duracion_base_ms, tiene_monto) VALUES
    ('login', 'Inicio de sesión', '/app/auth/iniciar-sesion.php', 1500, FALSE),
    ('payment', 'Pago', '/app/checkout/pago.php', 12000, TRUE),
    ('review_submit', 'Reseña enviada', '/app/resenas/enviar.php', 15000, FALSE),
    ('api_call', 'Llamada a la API', '/app/api/index.php', 300, FALSE),
    ('file_upload', 'Subida de archivo', '/app/soporte/adjuntos.php', 4000, FALSE);

INSERT INTO usuarios (id, nombre, pais_codigo, edad, genero) VALUES
    ('u001', 'Yusuf Kowalski', 'ZA', 59, 'F'),
    ('u021', 'Samuel Ivanov', 'ZA', 47, 'F');

-- Contraseña: admin123 (hash bcrypt, nunca texto plano)
INSERT INTO administradores (usuario, clave_hash) VALUES
    ('admin', '$2y$12$G3u25fAQTrUyIGHbYfHjqeX01bRMz5qD/lbaENU/NPh2QMb51SaCm');

INSERT INTO acciones (
    id, usuario_id, tipo_clave, marca_temporal, duracion_ms, ruta,
    ip, ip_pais_codigo, ip_hora_local, ip_proveedor,
    monto_local, moneda_codigo, monto_usd, comentario, endpoint, codigo_http, tamano_archivo_kb
) VALUES
    ('a00001', 'u001', 'login', '2026-08-09 19:47:10', 1200, '/app/auth/iniciar-sesion.php',
     '101.179.22.128', 'ZA', '21:47:10', 'Orbital Broadband',
     NULL, NULL, NULL, NULL, NULL, NULL, NULL),
    ('a00002', 'u001', 'payment', '2026-08-09 19:52:31', 18034, '/app/checkout/pago.php',
     '101.179.22.128', 'ZA', '21:52:31', 'Orbital Broadband',
     894.19, 'ZAR', 47.82, NULL, NULL, NULL, NULL),
    ('a00003', 'u001', 'review_submit', '2026-08-09 19:55:02', 15200, '/app/resenas/enviar.php',
     '101.179.22.128', 'ZA', '21:55:02', 'Orbital Broadband',
     NULL, NULL, NULL, 'Excelente atención, todo perfecto.', NULL, NULL, NULL),
    ('a00004', 'u021', 'api_call', '2026-08-21 01:47:57', 373, '/app/api/index.php',
     '135.149.222.243', 'CH', '02:47:57', 'NetConecta S.A.',
     NULL, NULL, NULL, NULL, '/api/pedidos', 200, NULL),
    ('a00005', 'u021', 'file_upload', '2026-08-21 01:52:40', 4210, '/app/soporte/adjuntos.php',
     '135.149.222.243', 'CH', '02:52:40', 'NetConecta S.A.',
     NULL, NULL, NULL, NULL, NULL, NULL, 464.8);
