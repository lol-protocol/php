-- Aviso de solicitudes de datos nuevas. bin/solicitudes.php --solo-nuevas manda un correo (el que cron envía con lo que el
-- script imprime) cuando llega una solicitud en /s/{id}/, y anota aquí cuándo lo hizo para no avisar dos veces de la misma.
-- Las solicitudes que ya había cuando se aplica esta migración quedan como avisadas (con su fecha de llegada): el
-- recordatorio de la mañana ya las cubre, y avisar de golpe de todas las viejas sería ruido. Es lo mismo que la migración
-- 012 hizo para pedidos_retiro.
ALTER TABLE solicitudes_persona ADD COLUMN avisada_en TIMESTAMP;

UPDATE solicitudes_persona SET avisada_en = creado_en;
