-- Demo data for development and tests: four generations of one family that
-- migrates from Asturias to Guadalajara. Never loaded in production.

INSERT INTO usuarios (id, email, nombre) VALUES
    (1, 'ana@example.com', 'Ana Demo'),
    (2, 'luis@example.com', 'Luis Demo');

INSERT INTO lugares (ruta, padre_ruta, codigo, nombre, nivel) VALUES
    ('mx', NULL, 'mx', 'México', 1),
    ('mx/jal', 'mx', 'jal', 'Jalisco', 2),
    ('mx/jal/gdl', 'mx/jal', 'gdl', 'Guadalajara', 3),
    ('mx/jal/tlq', 'mx/jal', 'tlq', 'Tlaquepaque', 3),
    ('es', NULL, 'es', 'España', 1),
    ('es/ast', 'es', 'ast', 'Asturias', 2),
    ('es/ast/ovi', 'es/ast', 'ovi', 'Oviedo', 3);

INSERT INTO grupos (id, apellido, origen, descripcion) VALUES
    (582317, 'García', 'es', 'Patronímico castellano muy extendido.'),
    (582318, 'Hernández', 'es', 'Patronímico: «hijo de Hernando».');

INSERT INTO personas (id, nombres, apellidos, sexo, padre_id, madre_id, grupo_id, aportado_por) VALUES
    (6128473101, 'José', 'García Álvarez', 'M', NULL, NULL, 582317, 1),
    (6128473102, 'María', 'Fernández López', 'F', NULL, NULL, NULL, 1),
    (6128473104, 'Carmen', 'Hernández Ruiz', 'F', NULL, NULL, 582318, 1),
    (6128473107, 'Elena', 'Martínez Soto', 'F', NULL, NULL, NULL, 2);

INSERT INTO personas (id, nombres, apellidos, sexo, padre_id, madre_id, grupo_id, aportado_por) VALUES
    (6128473103, 'Antonio', 'García Fernández', 'M', 6128473101, 6128473102, 582317, 1);

INSERT INTO personas (id, nombres, apellidos, sexo, padre_id, madre_id, grupo_id, aportado_por) VALUES
    (6128473105, 'Juan', 'García Hernández', 'M', 6128473103, 6128473104, 582317, 1),
    (6128473106, 'Rosa', 'García Hernández', 'F', 6128473103, 6128473104, 582317, 1);

INSERT INTO personas (id, nombres, apellidos, sexo, padre_id, madre_id, grupo_id, aportado_por) VALUES
    (6128473108, 'Carlos', 'García Martínez', 'M', 6128473105, 6128473107, 582317, 2),
    (6128473109, 'Lucía', 'García Martínez', 'F', 6128473105, 6128473107, 582317, 2);

INSERT INTO vinculos (persona_a, persona_b, tipo) VALUES
    (6128473101, 6128473102, 'conyuge'),
    (6128473103, 6128473104, 'conyuge'),
    (6128473105, 6128473107, 'conyuge'),
    (6128473106, 6128473108, 'padrino');

INSERT INTO sucesos (id, tipo, fecha, lugar_ruta, descripcion) VALUES
    (412000001, 'nacimiento', '1868-05-12', 'es/ast/ovi', NULL),
    (412000002, 'nacimiento', '1872-09-03', 'es/ast/ovi', NULL),
    (412000003, 'nacimiento', '1898-01-20', 'es/ast/ovi', NULL),
    (412000004, 'nacimiento', '1901-11-08', 'mx/jal/gdl', NULL),
    (412000005, 'migracion', '1919-04-02', 'mx/jal/gdl', 'Llega a Guadalajara desde Oviedo.'),
    (412000006, 'matrimonio', '1923-06-16', 'mx/jal/gdl', NULL),
    (412000007, 'nacimiento', '1925-02-14', 'mx/jal/gdl', NULL),
    (412000008, 'nacimiento', '1928-07-30', 'mx/jal/tlq', NULL),
    (412000009, 'nacimiento', '1929-12-01', 'mx/jal/gdl', NULL),
    (412000010, 'nacimiento', '1952-03-22', 'mx/jal/gdl', NULL),
    (412000011, 'nacimiento', '1955-08-09', 'mx/jal/gdl', NULL),
    (412000012, 'defuncion', '1941-10-05', 'es/ast/ovi', NULL),
    (412000013, 'defuncion', '1977-01-18', 'mx/jal/gdl', NULL);

INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES
    (412000001, 6128473101, 'principal'),
    (412000002, 6128473102, 'principal'),
    (412000003, 6128473103, 'principal'),
    (412000004, 6128473104, 'principal'),
    (412000005, 6128473103, 'principal'),
    (412000006, 6128473103, 'contrayente'),
    (412000006, 6128473104, 'contrayente'),
    (412000007, 6128473105, 'principal'),
    (412000008, 6128473106, 'principal'),
    (412000009, 6128473107, 'principal'),
    (412000010, 6128473108, 'principal'),
    (412000011, 6128473109, 'principal'),
    (412000012, 6128473101, 'principal'),
    (412000013, 6128473103, 'principal');

INSERT INTO organizaciones (id, nombre, tipo, lugar_ruta) VALUES
    (10231, 'Archivo Histórico de Jalisco', 'archivo', 'mx/jal/gdl'),
    (10232, 'Parroquia de San José de Analco', 'parroquia', 'mx/jal/gdl'),
    (10233, 'Archivo Histórico Provincial de Asturias', 'archivo', 'es/ast/ovi');

INSERT INTO organizacion_miembros (organizacion_id, persona_id, rol, desde, hasta) VALUES
    (10232, 6128473105, 'feligrés', '1925-02-21', NULL),
    (10232, 6128473107, 'feligrés', '1950-01-01', NULL);

INSERT INTO registros (id, titulo, tipo, fecha, lugar_ruta, organizacion_id, fuente, url, aportado_por) VALUES
    (81372001, 'Acta de bautizo de Juan García Hernández', 'acta', '1925-02-21', 'mx/jal/gdl', 10232,
     'Libro de bautizos 1925, foja 112, partida 348', NULL, 1),
    (81372002, 'Acta de matrimonio de Antonio García y Carmen Hernández', 'acta', '1923-06-16', 'mx/jal/gdl', 10231,
     'Registro Civil de Guadalajara, libro 14, acta 205', NULL, 1),
    (81372003, 'Partida de nacimiento de Antonio García Fernández', 'acta', '1898-01-22', 'es/ast/ovi', 10233,
     'Registro Civil de Oviedo, tomo 87, folio 41', NULL, 1);

INSERT INTO registro_sucesos (registro_id, suceso_id) VALUES
    (81372001, 412000007),
    (81372002, 412000006),
    (81372003, 412000003);

INSERT INTO colecciones (id, nombre, descripcion, usuario_id, publica) VALUES
    (1048293, 'Familia García', 'De Oviedo a Guadalajara, cuatro generaciones.', 1, TRUE),
    (1048294, 'Borrador de Luis', 'Investigación en curso.', 2, FALSE);

INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES
    (1048293, 6128473101), (1048293, 6128473102), (1048293, 6128473103),
    (1048293, 6128473104), (1048293, 6128473105), (1048293, 6128473106),
    (1048293, 6128473107), (1048293, 6128473108), (1048293, 6128473109),
    (1048294, 6128473105), (1048294, 6128473107);
