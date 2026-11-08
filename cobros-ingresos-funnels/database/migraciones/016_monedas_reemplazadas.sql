-- 016: una moneda que otra reemplazó se cotiza por la equivalencia fija con su reemplazo.
--
-- La 014 dejó el ZWL (reemplazado por el ZWG en 2024) y el BGN (por el euro, el 1 de enero de 2026)
-- en el catálogo, porque las boletas y los pagos ya cobrados en ellas siguen usándolas. Pero ninguna
-- de las dos tiene ya un país ni una cotización propia: la fuente de tasas puede dejar de traerlas, o
-- traerlas viejas. Entonces bin/actualizar_tasas.php las reportaba sin dato en cada corrida, y
-- MonedaRepository::estadoDeLasTasas() las listaba como «pendientes» para siempre mientras tuvieran
-- boletas: una alerta que nunca se apaga se termina ignorando.
--
-- Una moneda reemplazada vale, por ley, una cantidad fija de su reemplazo:
--
--   reemplazada_por         la moneda que la reemplazó (NULL en las vigentes)
--   unidades_por_reemplazo  cuántas unidades de la reemplazada valen 1 de su reemplazo
--
--   BGN: 1,95583 por 1 EUR (la paridad fija con la que Bulgaria entró al euro).
--   ZWL: 2.498,7242 por 1 ZWG (la equivalencia con la que el banco central convirtió los saldos en abril
--        de 2024; no la pude cotejar contra la fuente desde el entorno de desarrollo).
--
-- ActualizadorDeTasas deriva de ahí la tasa de cada una a partir de la de su reemplazo, en la misma
-- corrida y con las mismas reglas que las demás (salto máximo, primera tasa real que reexpresa lo
-- grabado con la de ejemplo). Si la fuente no trae el reemplazo, la reemplazada tampoco tiene dato.
--
-- Esta migración no toca monedas.tasa_a_usd: lo grabado con la tasa de ejemplo de esas dos monedas se
-- reexpresa en la primera corrida real solo si la tasa de la moneda sigue siendo exactamente la de
-- ejemplo (ver ActualizadorDeTasas). Y si una base ya las había actualizado con la tasa que traía la
-- fuente, la primera derivada puede saltar más del 50 % (el ZWL real se movía muy distinto del ZWG
-- que lo reemplazó): sale como «sospechosa» y se acepta con --forzar.

ALTER TABLE monedas ADD COLUMN IF NOT EXISTS reemplazada_por CHAR(3) REFERENCES monedas (codigo);
ALTER TABLE monedas ADD COLUMN IF NOT EXISTS unidades_por_reemplazo NUMERIC(18, 8);

ALTER TABLE monedas DROP CONSTRAINT IF EXISTS monedas_reemplazo_completo;
ALTER TABLE monedas ADD CONSTRAINT monedas_reemplazo_completo CHECK (
    (reemplazada_por IS NULL AND unidades_por_reemplazo IS NULL)
    OR (reemplazada_por IS NOT NULL AND reemplazada_por <> codigo AND unidades_por_reemplazo IS NOT NULL AND unidades_por_reemplazo > 0)
);
-- (El IS NOT NULL no sobra: un CHECK que da NULL se cumple, así que «reemplazada_por = 'EUR'» sin equivalencia
-- pasaría con solo el `> 0`.)

UPDATE monedas SET reemplazada_por = 'EUR', unidades_por_reemplazo = 1.95583 WHERE codigo = 'BGN' AND reemplazada_por IS NULL;
UPDATE monedas SET reemplazada_por = 'ZWG', unidades_por_reemplazo = 2498.7242 WHERE codigo = 'ZWL' AND reemplazada_por IS NULL;
