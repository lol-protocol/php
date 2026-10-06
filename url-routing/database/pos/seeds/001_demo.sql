-- Demo data for development and tests: a small apparel catalog and one
-- shipped order. Never loaded in production.

INSERT INTO usuarios (id, email, nombre) VALUES
    (1, 'ana@example.com', 'Ana Demo'),
    (2, 'luis@example.com', 'Luis Demo');

INSERT INTO grupos (id, nombre, padre_id) VALUES
    (1001, 'Ropa', NULL),
    (1004, 'Accesorios', NULL);

INSERT INTO grupos (id, nombre, padre_id) VALUES
    (1002, 'Camisetas', 1001),
    (1003, 'Sudaderas', 1001);

INSERT INTO productos (id, nombre, descripcion, precio_centavos, moneda, grupo_id, activo) VALUES
    (81372047, 'Camiseta básica', 'Algodón orgánico, corte recto.', 19900, 'MXN', 1002, TRUE),
    (81372048, 'Sudadera con capucha', 'Felpa perchada, bolsillo canguro.', 69900, 'MXN', 1003, TRUE),
    (81372049, 'Gorra de seis paneles', 'Ajuste trasero de metal.', 24900, 'MXN', 1004, TRUE),
    (81372050, 'Camiseta edición 2024', 'Descontinuada.', 34900, 'MXN', 1002, FALSE);

INSERT INTO atributos (id, tipo, nombre, valor) VALUES
    (48213, 'color', 'Azul marino', '#1F2A44'),
    (48214, 'color', 'Blanco', '#FFFFFF'),
    (48215, 'color', 'Rojo', '#C62828'),
    (48300, 'talla', 'S', NULL),
    (48301, 'talla', 'M', NULL),
    (48302, 'talla', 'L', NULL),
    (48400, 'material', 'Algodón', NULL);

INSERT INTO producto_atributos (producto_id, atributo_id) VALUES
    (81372047, 48213), (81372047, 48214), (81372047, 48300), (81372047, 48301), (81372047, 48302), (81372047, 48400),
    (81372048, 48213), (81372048, 48215), (81372048, 48301), (81372048, 48302),
    (81372049, 48213), (81372049, 48214);

INSERT INTO variantes (sku, producto_id, nombre, precio_centavos, stock) VALUES
    ('CAM-AZ-S', 81372047, 'Azul marino / S', NULL, 12),
    ('CAM-AZ-M', 81372047, 'Azul marino / M', NULL, 30),
    ('CAM-BL-L', 81372047, 'Blanco / L', NULL, 0),
    ('SUD-RO-M', 81372048, 'Rojo / M', NULL, 8),
    ('SUD-AZ-L', 81372048, 'Azul marino / L', 74900, 3),
    ('GOR-AZ', 81372049, 'Azul marino', NULL, 20);

INSERT INTO variante_atributos (sku, atributo_id) VALUES
    ('CAM-AZ-S', 48213), ('CAM-AZ-S', 48300),
    ('CAM-AZ-M', 48213), ('CAM-AZ-M', 48301),
    ('CAM-BL-L', 48214), ('CAM-BL-L', 48302),
    ('SUD-RO-M', 48215), ('SUD-RO-M', 48301),
    ('SUD-AZ-L', 48213), ('SUD-AZ-L', 48302),
    ('GOR-AZ', 48213);

INSERT INTO etiquetas (id, nombre) VALUES
    (520001, 'Algodón orgánico'),
    (520002, 'Nuevo'),
    (520003, 'Oferta');

INSERT INTO producto_etiquetas (producto_id, etiqueta_id) VALUES
    (81372047, 520001), (81372047, 520002),
    (81372048, 520002),
    (81372049, 520003);

INSERT INTO colecciones (id, nombre, descripcion, inicio, fin) VALUES
    (7300001, 'Primavera 2026', 'Básicos de temporada.', '2026-03-01', '2026-06-30');

INSERT INTO coleccion_productos (coleccion_id, producto_id, orden) VALUES
    (7300001, 81372048, 1),
    (7300001, 81372047, 2);

INSERT INTO ordenes (id, usuario_id, estado, total_centavos, moneda, direccion_envio, numero_guia, creada_en) VALUES
    (8137204719000, 1, 'enviada', 64700, 'MXN', 'Av. Juárez 100, Guadalajara, JAL 44100, MX', 'MX123456789', '2026-09-01 10:15:00'),
    (8137204719001, 2, 'pagada', 24900, 'MXN', 'Calle Uría 5, Oviedo 33003, ES', NULL, '2026-09-10 18:40:00');

INSERT INTO orden_items (orden_id, sku, producto_id, nombre, cantidad, precio_unitario_centavos) VALUES
    (8137204719000, 'CAM-AZ-M', 81372047, 'Camiseta básica — Azul marino / M', 2, 19900),
    (8137204719000, 'GOR-AZ', 81372049, 'Gorra de seis paneles — Azul marino', 1, 24900),
    (8137204719001, 'GOR-AZ', 81372049, 'Gorra de seis paneles — Azul marino', 1, 24900);

INSERT INTO orden_eventos (orden_id, ocurrido_en, estado, detalle) VALUES
    (8137204719000, '2026-09-01 10:15:00', 'pagada', 'Pago confirmado.'),
    (8137204719000, '2026-09-02 09:00:00', 'enviada', 'Entregado a paquetería, guía MX123456789.');

INSERT INTO deseos (usuario_id, producto_id) VALUES
    (1, 81372048);

INSERT INTO direcciones (usuario_id, alias, destinatario, calle, ciudad, codigo_postal, pais, principal) VALUES
    (1, 'Casa', 'Ana Demo', 'Av. Juárez 100', 'Guadalajara', '44100', 'MX', TRUE),
    (1, 'Oficina', 'Ana Demo', 'Av. Vallarta 3000', 'Guadalajara', '44690', 'MX', FALSE);
