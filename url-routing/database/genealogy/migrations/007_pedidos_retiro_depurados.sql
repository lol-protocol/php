-- Depuración de pedidos de retiro ya resueltos. Un pedido guarda el correo de quien lo
-- envió y lo que escribió (detalle, marcas del filtro); resuelto, ya no hacen falta.
-- bin/pedidos-retiro.php --depurar los borra pasado un plazo y anota aquí cuándo, para
-- no volver a tocarlo. Se conservan el número, la página, el motivo, el estado y el
-- historial: es lo que prueba qué se decidió y cuándo.
ALTER TABLE pedidos_retiro ADD COLUMN depurado_en TIMESTAMP;
