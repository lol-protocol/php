-- Lo que el filtro de difamación encontró en el texto de un pedido de retiro («insulto»,
-- «acusación de delito», «dato personal (RUT)»…), para que el propietario revise primero lo
-- que trae amenazas o insultos. Es un aviso para quien revisa: nunca bloquea un pedido, porque
-- quien se queja de una acusación suele repetirla. Vacío si el filtro no encontró nada o si no
-- estaba disponible (ver Support/FiltroPedidos.php).
ALTER TABLE pedidos_retiro ADD COLUMN marcas TEXT;
