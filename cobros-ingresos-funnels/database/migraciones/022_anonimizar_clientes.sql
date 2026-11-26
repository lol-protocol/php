-- 022: anonimizar a un cliente (borrar su nombre, email y ciudad) y poder deshacerlo durante 7 dias.
--
-- Anonimizar sobrescribe en clientes el nombre («Cliente anonimizado #id»), el email («anonimizado-id@anonimizado.invalid», que sigue
-- siendo unico) y la ciudad, y hace lo mismo con el nombre y el email de su usuario del funnel (usuarios_funnel.cliente_id). Quedan el pais,
-- el idioma, el genero, el rango de edad y todas sus boletas, pagos y notas: las cifras y la segmentacion no cambian. La entrada de
-- auditoria del alta del cliente (que dice «Cliente #id: nombre (email)») se reescribe sin esos datos.
--
-- Se puede deshacer durante 7 dias: los datos originales se guardan aparte, en datos_anonimizados (y datos_anonimizados_funnel), con su
-- fecha de vencimiento (vence_en). Pasada esa fecha bin/purgar_anonimizados.php (cron) los borra y la anonimizacion ya es definitiva. Mientras
-- tanto siguen siendo datos personales guardados: el plazo es el precio de poder arrepentirse. Los respaldos hechos antes de anonimizar
-- tambien los conservan.
--
-- Un cliente anonimizado no recibe boletas ni pagos nuevos (trigger exigir_cliente_no_anonimizado, que cubre cualquier camino). Anular una
-- boleta suya o devolver una nota sigue funcionando: no inserta ni boletas ni pagos.
--
-- Idempotente: IF NOT EXISTS / OR REPLACE / DROP ... IF EXISTS.

ALTER TABLE clientes        ADD COLUMN IF NOT EXISTS anonimizado_en TIMESTAMPTZ;
ALTER TABLE usuarios_funnel ADD COLUMN IF NOT EXISTS anonimizado_en TIMESTAMPTZ;

CREATE TABLE IF NOT EXISTS datos_anonimizados (
    cliente_id         INTEGER PRIMARY KEY REFERENCES clientes (id) ON DELETE CASCADE,
    nombre             TEXT NOT NULL,
    email              TEXT NOT NULL,
    ciudad             TEXT NOT NULL,
    auditoria_id       INTEGER,
    auditoria_detalle  TEXT,
    anonimizado_en     TIMESTAMPTZ NOT NULL DEFAULT now(),
    vence_en           TIMESTAMPTZ NOT NULL
);

CREATE TABLE IF NOT EXISTS datos_anonimizados_funnel (
    usuario_id INTEGER PRIMARY KEY REFERENCES usuarios_funnel (id) ON DELETE CASCADE,
    cliente_id INTEGER NOT NULL REFERENCES datos_anonimizados (cliente_id) ON DELETE CASCADE,
    nombre     TEXT NOT NULL,
    email      TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS datos_anonimizados_por_vencimiento ON datos_anonimizados (vence_en);

CREATE OR REPLACE FUNCTION exigir_cliente_no_anonimizado() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM clientes WHERE id = NEW.cliente_id AND anonimizado_en IS NOT NULL) THEN
        RAISE EXCEPTION 'El cliente % esta anonimizado: no se le pueden cargar boletas ni pagos nuevos', NEW.cliente_id
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS boletas_cliente_no_anonimizado ON boletas;
CREATE TRIGGER boletas_cliente_no_anonimizado
    BEFORE INSERT ON boletas
    FOR EACH ROW EXECUTE FUNCTION exigir_cliente_no_anonimizado();

DROP TRIGGER IF EXISTS pagos_cliente_no_anonimizado ON pagos;
CREATE TRIGGER pagos_cliente_no_anonimizado
    BEFORE INSERT ON pagos
    FOR EACH ROW EXECUTE FUNCTION exigir_cliente_no_anonimizado();
