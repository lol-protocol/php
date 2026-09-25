-- 002: idempotencia de los formularios de alta (ver App\EnvioUnico).
--
-- Cada formulario de alta renderizado lleva un token al azar de un solo uso.
-- El primer POST que lo trae lo registra aca, en la misma transaccion que la
-- escritura, junto con a donde redirigio. Un reenvio del mismo formulario -el
-- doble clic en "Guardar", que llega antes de la redireccion- encuentra el
-- token y recibe la misma redireccion en vez de crear otro pago u otra
-- boleta. Si los dos envios llegan a la vez, el segundo choca contra la
-- clave primaria y su transaccion entera se deshace.
CREATE TABLE envios_formulario (
    token CHAR(64) PRIMARY KEY,
    redireccion TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);

-- Para la purga de tokens viejos (EnvioUnico los borra a los 7 dias).
CREATE INDEX idx_envios_formulario_creado_en ON envios_formulario(creado_en);
