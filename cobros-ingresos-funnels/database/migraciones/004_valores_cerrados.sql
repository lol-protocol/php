-- 004: los valores cerrados que la app valida, tambien en la base.
--
-- pagos.metodo, clientes.genero y clientes.segmento tienen una lista de valores
-- validos (Etiquetas::metodosPago(), ClienteRepository::GENEROS y ::SEGMENTOS)
-- que la app valida en el servidor, pero la base aceptaba cualquier texto: una
-- carga directa, un script o un bug nuevo podia guardar "bitcoin" como metodo de
-- pago, y aparecia como una barra mas en la pantalla de Pagos.
--
-- NOT VALID: la restriccion rige para toda fila nueva o modificada, pero no
-- revisa las que ya existen. Esta migracion corre en cada despliegue contra
-- bases con datos reales y nunca borra ni cambia datos: una fila vieja con un
-- valor fuera de la lista no puede impedir el despliegue. Para encontrarlas:
--     SELECT id, metodo FROM pagos WHERE metodo NOT IN ('efectivo', 'tarjeta', 'transferencia');
-- y, corregidas, para que la base las revise tambien:
--     ALTER TABLE pagos VALIDATE CONSTRAINT pagos_metodo_valido;
-- Ojo: hasta corregirla, modificar una fila vieja fuera de la lista (aunque sea
-- solo para anularla) falla, porque la fila nueva tiene que cumplir la regla.
--
-- Las listas estan en dos lugares (PHP y esta migracion): para agregar un valor
-- se cambia la lista de la app y se agrega una migracion nueva que reemplace la
-- restriccion. tests/Integration/ValoresCerradosTest.php compara las dos.

ALTER TABLE pagos
    ADD CONSTRAINT pagos_metodo_valido
    CHECK (metodo IN ('transferencia', 'tarjeta', 'efectivo')) NOT VALID;

ALTER TABLE clientes
    ADD CONSTRAINT clientes_genero_valido
    CHECK (genero IN ('Femenino', 'Masculino', 'No especifica')) NOT VALID;

ALTER TABLE clientes
    ADD CONSTRAINT clientes_segmento_valido
    CHECK (segmento IN ('general', 'starter', 'pro', 'enterprise')) NOT VALID;
