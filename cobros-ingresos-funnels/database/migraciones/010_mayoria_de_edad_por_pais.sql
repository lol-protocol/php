-- 010: la mayoria de edad es la del pais del cliente, no una sola para todos.
--
-- La 007 exigia 18 anios a todos. Pero la mayoria de edad civil depende del pais
-- (Tailandia 20, Singapur 21...), y la empresa no atiende a menores de edad. Cada
-- pais guarda ahora la edad desde la que se admite un cliente, y el trigger de la
-- 007 la busca por el pais del cliente. ClientesController hace lo mismo en el alta
-- (MayoriaDeEdad, PaisRepository::mayoriaDeEdad); MayoriaDeEdadTest y
-- ClientesMayoresDeEdadTest comprueban que las dos coinciden en el borde, pais por
-- pais.
--
-- Cuales son (solo los que cambian; el resto queda en 18, el valor por defecto):
--   19: Corea del Sur, Argelia, Canada (18 o 19 segun la provincia: se toma la mas
--       alta, para no dejar pasar a nadie que sea menor en alguna)
--   20: Tailandia
--   21: Singapur, Egipto, Emiratos Arabes Unidos, Kuwait, Bahrein, Honduras
--
-- Esta lista NO es asesoria legal. Hay paises donde la edad depende del acto
-- (contratar, casarse, votar) o de la region, y los que se dejaron en 18 con dudas
-- son: Estados Unidos (19 en Alabama y Nebraska, 21 en Mississippi), Nueva Zelanda
-- (20 para algunos actos civiles), Indonesia, Tunez, Camerun, Nicaragua y Bolivia.
-- Antes de atender clientes de esos paises hay que revisarlos con quien responda
-- por lo legal; corregirlo es un UPDATE de una fila, sin tocar codigo:
--     UPDATE paises SET mayoria_de_edad = 21 WHERE codigo = 'US';
-- (La base acepta de 16 a 25: un valor fuera de eso es un error de tipeo.)
--
-- Cambiar la edad de un pais, igual que esta migracion, no revisa a los clientes
-- que ya existen: a quien ya esta cargado no se le pide nada. Para encontrar a los
-- que hoy no llegarian a la edad de su pais (reemplaza la consulta de la 007):
--     SELECT c.id, c.nombre, c.fecha_nacimiento, p.mayoria_de_edad
--     FROM clientes c JOIN paises p ON p.codigo = c.pais_codigo
--     WHERE c.fecha_nacimiento > CURRENT_DATE - make_interval(years => p.mayoria_de_edad);
--
-- El trigger de cambios ahora mira tambien pais_codigo: pasar a un cliente de un
-- pais a otro es cambiar la edad que se le exige, y tiene que cumplirla. Mientras
-- nadie le toque la fecha de nacimiento ni el pais, un cliente anterior a la regla
-- sigue pudiendo editarse (el email, la ciudad), como en la 007.

ALTER TABLE paises ADD COLUMN mayoria_de_edad SMALLINT NOT NULL DEFAULT 18;

ALTER TABLE paises DROP CONSTRAINT IF EXISTS paises_mayoria_de_edad_rango;
ALTER TABLE paises ADD CONSTRAINT paises_mayoria_de_edad_rango
    CHECK (mayoria_de_edad BETWEEN 16 AND 25);

UPDATE paises SET mayoria_de_edad = 19 WHERE codigo IN ('KR', 'DZ', 'CA');
UPDATE paises SET mayoria_de_edad = 20 WHERE codigo = 'TH';
UPDATE paises SET mayoria_de_edad = 21 WHERE codigo IN ('SG', 'EG', 'AE', 'KW', 'BH', 'HN');

-- Mismo nombre que en la 007, asi que los dos triggers siguen apuntando a ella.
CREATE OR REPLACE FUNCTION exigir_cliente_mayor_de_edad() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    edad SMALLINT;
BEGIN
    SELECT mayoria_de_edad INTO edad FROM paises WHERE codigo = NEW.pais_codigo;
    -- Un pais que no existe lo frena la clave foranea; hasta ahi, la regla general.
    edad := COALESCE(edad, 18);

    IF NEW.fecha_nacimiento > CURRENT_DATE - make_interval(years => edad) THEN
        RAISE EXCEPTION 'Solo se admiten clientes mayores de edad (% anios cumplidos en %): fecha de nacimiento %',
            edad, NEW.pais_codigo, NEW.fecha_nacimiento
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS clientes_mayor_de_edad_cambio ON clientes;
CREATE TRIGGER clientes_mayor_de_edad_cambio
    BEFORE UPDATE OF fecha_nacimiento, pais_codigo ON clientes
    FOR EACH ROW WHEN (
        NEW.fecha_nacimiento IS DISTINCT FROM OLD.fecha_nacimiento
        OR NEW.pais_codigo IS DISTINCT FROM OLD.pais_codigo
    )
    EXECUTE FUNCTION exigir_cliente_mayor_de_edad();
