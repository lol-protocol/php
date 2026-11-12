-- Aviso de pedidos nuevos. bin/pedidos-retiro.php --solo-nuevos manda un correo (el que cron envía con lo que el
-- script imprime) cuando llega un pedido, y anota aquí cuándo lo hizo para no avisar dos veces del mismo. Los
-- pedidos que ya había cuando se aplica esta migración quedan como avisados (con su fecha de llegada): el
-- recordatorio diario ya los cubre, y avisar de golpe de todos los viejos sería ruido.
ALTER TABLE pedidos_retiro ADD COLUMN avisado_en TIMESTAMP;

UPDATE pedidos_retiro SET avisado_en = creado_en;
