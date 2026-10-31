-- 013: las etapas del funnel van en orden, tambien en la base.
--
-- Un usuario pasa de visitante a registrado, de ahi a lead y de ahi a cliente, y FunnelRepository cuenta cada
-- etapa por su cuenta (SUM(CASE WHEN fecha_x IS NOT NULL ...)) y las pone una sobre otra. Nada impedia guardar
-- un cliente sin lead, o un registro anterior a la visita: la tasa de lead a cliente pasaba del 100 %, el
-- tiempo promedio de conversion salia negativo y la cohorte contaba esa conversion en el mes 0.
--
-- La regla: fecha_visita <= fecha_registro <= fecha_lead <= fecha_conversion, y una etapa solo existe si existe
-- la anterior (no hay lead sin registro ni cliente sin lead).
--
-- NOT VALID, como en la 004: rige para toda fila nueva o modificada y no revisa las que ya existen, para que una
-- fila vieja fuera de orden no impida el despliegue. Para encontrarlas:
--     SELECT id FROM usuarios_funnel WHERE NOT (
--         (fecha_registro IS NULL OR fecha_registro >= fecha_visita)
--         AND (fecha_lead IS NULL OR (fecha_registro IS NOT NULL AND fecha_lead >= fecha_registro))
--         AND (fecha_conversion IS NULL OR (fecha_lead IS NOT NULL AND fecha_conversion >= fecha_lead)));
-- y, corregidas, para que la base las revise tambien:
--     ALTER TABLE usuarios_funnel VALIDATE CONSTRAINT usuarios_funnel_etapas_en_orden;

ALTER TABLE usuarios_funnel
    ADD CONSTRAINT usuarios_funnel_etapas_en_orden
    CHECK (
        (fecha_registro IS NULL OR fecha_registro >= fecha_visita)
        AND (fecha_lead IS NULL OR (fecha_registro IS NOT NULL AND fecha_lead >= fecha_registro))
        AND (fecha_conversion IS NULL OR (fecha_lead IS NOT NULL AND fecha_conversion >= fecha_lead))
    ) NOT VALID;
