-- Cuándo cambió por última vez el contenido de una página, para el <lastmod> de cada URL en los sitemaps.
--
-- Nullable y sin valor por omisión, a propósito: una fila sin fecha no lleva <lastmod> (mejor ninguna que una
-- inventada, y un buscador deja de creerle a un sitio cuyas fechas no se mueven). Quien cambie el contenido de
-- una página (una importación, una edición, las herramientas de bin/) pone `actualizado_en = CURRENT_TIMESTAMP`
-- en esa fila. NO se mueve cuando solo cambia a quién se le muestra la página (marcar una persona como viva,
-- un pedido de retiro): eso no cambia lo que la página dice, y la fecha delataría el cambio.
ALTER TABLE personas ADD COLUMN actualizado_en TIMESTAMP;
ALTER TABLE sucesos ADD COLUMN actualizado_en TIMESTAMP;
ALTER TABLE registros ADD COLUMN actualizado_en TIMESTAMP;
ALTER TABLE colecciones ADD COLUMN actualizado_en TIMESTAMP;
ALTER TABLE grupos ADD COLUMN actualizado_en TIMESTAMP;
ALTER TABLE organizaciones ADD COLUMN actualizado_en TIMESTAMP;
