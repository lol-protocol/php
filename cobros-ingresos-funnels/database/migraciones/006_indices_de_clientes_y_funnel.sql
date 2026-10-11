-- 006: dos indices para cuando la base crece. Con los datos del seed (55
-- clientes) no se nota; se midieron con 30 mil clientes y 150 mil visitantes
-- del funnel.
--
-- usuarios_funnel(cliente_id): la ficha de un cliente busca su recorrido de
-- funnel (FunnelRepository::viajeDeCliente) y cliente_id no tenia indice, asi
-- que leia la tabla entera: 24 ms, 0,08 ms con el indice. Es parcial porque casi
-- ningun visitante se convierte y casi todas las filas tienen cliente_id NULL:
-- esas no se buscan nunca, y asi el indice solo guarda a los convertidos.
--
-- clientes(nombre, id): el listado de clientes (ClienteRepository::buscar) y el
-- selector de los formularios (paraSelector) ordenan por nombre, id y se quedan
-- con una pagina. Sin indice ordenaban los 30 mil clientes para devolver 25
-- (32 ms; 0,2 ms con el indice, y la pagina 600 baja de 37 a 9 ms).
--
-- No hay indice para el buscador de texto (ILIKE '%...%'): necesitaria la
-- extension pg_trgm, que pide permisos que la base de produccion puede no darle
-- a esta app, y sin indice tarda unos 40 ms con 30 mil clientes.
--
-- IF NOT EXISTS: la migracion corre en cada despliegue contra bases con datos
-- reales, y un indice con el mismo nombre creado a mano no puede impedirlo.
-- CREATE INDEX frena las escrituras de la tabla mientras se construye (con
-- decenas de miles de filas son milisegundos); CONCURRENTLY no se puede usar,
-- porque el Migrador corre cada migracion dentro de una transaccion.

CREATE INDEX IF NOT EXISTS idx_funnel_cliente ON usuarios_funnel(cliente_id) WHERE cliente_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_clientes_nombre_id ON clientes(nombre, id);
