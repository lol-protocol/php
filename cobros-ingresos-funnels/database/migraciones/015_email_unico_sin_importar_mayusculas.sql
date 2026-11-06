-- 015: el email de un cliente es unico sin importar las mayusculas.
--
-- clientes.email y usuarios_funnel.email eran UNIQUE a secas, y en PostgreSQL eso distingue mayusculas:
-- «JUAN@x.com» y «juan@x.com» entraban como dos clientes distintos, y el mismo correo escrito dos veces
-- creaba dos personas. Un email es el mismo con cualquier combinacion de mayusculas.
--
-- Indices unicos sobre lower(email): rigen para toda fila nueva o modificada. Si ya hay dos emails que solo
-- difieren en mayusculas, esta migracion falla (y el Migrador la revierte entera) hasta que se unan a mano:
--     SELECT lower(email), count(*) FROM clientes GROUP BY 1 HAVING count(*) > 1;
--     SELECT lower(email), count(*) FROM usuarios_funnel GROUP BY 1 HAVING count(*) > 1;
-- El UNIQUE de la 001 se queda: sigue evitando el duplicado exacto, y el indice nuevo cubre el resto.

CREATE UNIQUE INDEX clientes_email_minusculas_idx ON clientes (lower(email));
CREATE UNIQUE INDEX usuarios_funnel_email_minusculas_idx ON usuarios_funnel (lower(email));
