-- 014: correcciones del catalogo de paises y monedas de la 005.
--
-- La 005 ya corrio en las bases existentes, asi que lo que estaba mal se corrige aca (ver su
-- ultimo parrafo). Cuatro cosas:
--
-- 1. Zimbabue: desde abril de 2024 el dolar zimbabuense (ZWL) lo reemplaza el ZiG (ZWG). El pais
--    pasa a ZWG; ZWL se queda en el catalogo, porque las boletas y los pagos que ya se cobraron en
--    esa moneda la siguen usando (moneda_codigo apunta a monedas) y no se pueden volver a expresar.
-- 2. Bulgaria: desde el 1 de enero de 2026 usa el euro. El pais pasa a EUR; BGN se queda por la
--    misma razon que ZWL.
-- 3. Lo que le faltaba al catalogo:
--    - Liechtenstein (CHF), el unico de los 193 miembros de la ONU que no estaba.
--    - Los territorios habitados con codigo ISO 3166-1 propio, cada uno con la moneda que usa.
--      Con eso quedan alcanzables las monedas que faltaban: AWG (Aruba), BMD (Bermudas),
--      FKP (Malvinas), GIP (Gibraltar), KYD (Caiman), SHP (Santa Elena), XCG (florin del Caribe,
--      que desde marzo de 2025 reemplaza al ANG en Curazao y San Martin) y XPF (franco CFP,
--      el de la Polinesia, Nueva Caledonia y Wallis y Futuna), mas ZWG. No se agregan los
--      territorios sin poblacion permanente (Antartida, Isla Bouvet, Islas Heard y McDonald,
--      Territorio Britanico del Oceano Indico, Georgias del Sur, Tierras Australes Francesas,
--      Islas Menores Alejadas de EE. UU.) ni el Sahara Occidental, que no tiene moneda propia
--      ni una situacion juridica que esta migracion pueda resolver.
--    - El colon salvadoreno (SVC) no se agrega: sigue en la lista ISO, pero El Salvador usa el
--      dolar y nadie factura en colones.
--    Las tasas son, como en la 005, de ejemplo: las monedas ligadas a otra por ley (BMD, AWG, XCG,
--    KYD al dolar; FKP, GIP, SHP a la libra; XPF al euro) salen del par fijo, y ZWG es un valor
--    aproximado. bin/actualizar_tasas.php las reemplaza por las reales (las que la fuente no trae
--    quedan como estan, ver «sinDato»).
--    La mayoria de edad de los paises nuevos queda en 18, el valor por defecto de la 010, que ya
--    avisa que su lista no es asesoria legal. Duda conocida: en Puerto Rico es 21 segun su codigo
--    civil; corregirlo es un UPDATE (UPDATE paises SET mayoria_de_edad = 21 WHERE codigo = 'PR').
-- 4. Decimales de cada moneda. ISO 4217 da a cada moneda los decimales de su unidad menor: 0 para el
--    yen, el won, el peso chileno o el franco CFA (no existe «medio yen»), 2 para casi todas, 3 para el
--    dinar kuwaiti o el rial omani. monedas.decimales guarda ese dato. Lo que la aplicacion hace con
--    el: en las de 0 decimales los montos se piden y se muestran enteros (Validacion::montoEnMoneda(),
--    dinero_en_moneda()). Las de 3 se guardan con 2: boletas.monto, pagos.monto y
--    notas_credito.monto son NUMERIC(14, 2), y pasarlos a 3 cambiaria el centavo de todo el sistema
--    (Dinero::UN_CENTAVO, el estado SQL de la boleta, la cartera); queda dicho en el README como limite
--    conocido. Lo que ya estaba cargado no se toca: una boleta vieja en yenes con centavos sigue
--    mostrandose con ellos.
--
-- ON CONFLICT DO NOTHING en todos los INSERT: una base que ya tiene alguna de estas filas (porque
-- alguien la agrego a mano) no pierde ni cambia nada.

-- 4. (primero: es una columna, y el resto no la usa)
ALTER TABLE monedas ADD COLUMN IF NOT EXISTS decimales SMALLINT NOT NULL DEFAULT 2;

ALTER TABLE monedas DROP CONSTRAINT IF EXISTS monedas_decimales_rango;
ALTER TABLE monedas ADD CONSTRAINT monedas_decimales_rango CHECK (decimales BETWEEN 0 AND 3);

UPDATE monedas SET decimales = 0
WHERE codigo IN ('BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF');

UPDATE monedas SET decimales = 3
WHERE codigo IN ('BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND');

-- 3. Monedas que faltaban.
INSERT INTO monedas (codigo, nombre, simbolo, tasa_a_usd, decimales)
SELECT 'FKP', 'Libra malvinense', '£', tasa_a_usd, 2 FROM monedas WHERE codigo = 'GBP'
UNION ALL
SELECT 'GIP', 'Libra de Gibraltar', '£', tasa_a_usd, 2 FROM monedas WHERE codigo = 'GBP'
UNION ALL
SELECT 'SHP', 'Libra de Santa Elena', '£', tasa_a_usd, 2 FROM monedas WHERE codigo = 'GBP'
UNION ALL
SELECT 'XPF', 'Franco CFP', '₣', round(tasa_a_usd / 119.33174, 8), 0 FROM monedas WHERE codigo = 'EUR'
ON CONFLICT (codigo) DO NOTHING;

INSERT INTO monedas (codigo, nombre, simbolo, tasa_a_usd, decimales) VALUES
    ('AWG', 'Florin arubeno', 'ƒ', 0.5587, 2),
    ('BMD', 'Dolar bermudeno', 'BD$', 1.0, 2),
    ('KYD', 'Dolar de las Islas Caiman', 'CI$', 1.2, 2),
    ('XCG', 'Florin del Caribe', 'Cg', 0.5587, 2),
    ('ZWG', 'ZiG (oro de Zimbabue)', 'ZiG', 0.037, 2)
ON CONFLICT (codigo) DO NOTHING;

-- 1 y 2. Zimbabue y Bulgaria cambian de moneda. Un cliente que ya existia conserva su pais; sus
-- boletas nuevas salen en la moneda nueva y las viejas se quedan en la que tenian.
UPDATE paises SET moneda_codigo = 'ZWG' WHERE codigo = 'ZW' AND moneda_codigo = 'ZWL';
UPDATE paises SET moneda_codigo = 'EUR' WHERE codigo = 'BG' AND moneda_codigo = 'BGN';

-- 3. Paises y territorios que faltaban.
INSERT INTO paises (codigo, nombre, moneda_codigo) VALUES
    ('LI', 'Liechtenstein', 'CHF'),
    ('AI', 'Anguila', 'XCD'),
    ('AS', 'Samoa Americana', 'USD'),
    ('AW', 'Aruba', 'AWG'),
    ('AX', 'Islas Aland', 'EUR'),
    ('BL', 'San Bartolome', 'EUR'),
    ('BM', 'Bermudas', 'BMD'),
    ('BQ', 'Bonaire, San Eustaquio y Saba', 'USD'),
    ('CC', 'Islas Cocos', 'AUD'),
    ('CK', 'Islas Cook', 'NZD'),
    ('CW', 'Curazao', 'XCG'),
    ('CX', 'Isla de Navidad', 'AUD'),
    ('FK', 'Islas Malvinas', 'FKP'),
    ('FO', 'Islas Feroe', 'DKK'),
    ('GF', 'Guayana Francesa', 'EUR'),
    ('GG', 'Guernsey', 'GBP'),
    ('GI', 'Gibraltar', 'GIP'),
    ('GL', 'Groenlandia', 'DKK'),
    ('GP', 'Guadalupe', 'EUR'),
    ('GU', 'Guam', 'USD'),
    ('IM', 'Isla de Man', 'GBP'),
    ('JE', 'Jersey', 'GBP'),
    ('KY', 'Islas Caiman', 'KYD'),
    ('MF', 'San Martin (Francia)', 'EUR'),
    ('MP', 'Islas Marianas del Norte', 'USD'),
    ('MQ', 'Martinica', 'EUR'),
    ('MS', 'Montserrat', 'XCD'),
    ('NC', 'Nueva Caledonia', 'XPF'),
    ('NF', 'Isla Norfolk', 'AUD'),
    ('NU', 'Niue', 'NZD'),
    ('PF', 'Polinesia Francesa', 'XPF'),
    ('PM', 'San Pedro y Miquelon', 'EUR'),
    ('PN', 'Islas Pitcairn', 'NZD'),
    ('PR', 'Puerto Rico', 'USD'),
    ('RE', 'Reunion', 'EUR'),
    ('SH', 'Santa Elena, Ascension y Tristan de Acuna', 'SHP'),
    ('SJ', 'Svalbard y Jan Mayen', 'NOK'),
    ('SX', 'San Martin (Paises Bajos)', 'XCG'),
    ('TC', 'Islas Turcas y Caicos', 'USD'),
    ('TK', 'Tokelau', 'NZD'),
    ('VG', 'Islas Virgenes Britanicas', 'USD'),
    ('VI', 'Islas Virgenes de los Estados Unidos', 'USD'),
    ('WF', 'Wallis y Futuna', 'XPF'),
    ('YT', 'Mayotte', 'EUR')
ON CONFLICT (codigo) DO NOTHING;
