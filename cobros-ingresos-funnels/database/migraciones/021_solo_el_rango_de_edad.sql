-- 021: la base guarda el RANGO DE EDAD de cada persona, no su fecha de nacimiento.
--
-- clientes.fecha_nacimiento y usuarios_funnel.fecha_nacimiento eran un dato personal que ninguna pantalla ni reporte necesita
-- completo: solo se usaba para dos cosas, validar la mayoria de edad al dar de alta (eso se hace con la fecha que se escribe en
-- el formulario, sin guardarla) y agrupar por tramo (segmentacion y funnel). Ahora queda solo el tramo.
--
-- Lo que se pierde, a proposito:
--   * La fecha se borra y NO se puede recuperar de esta base. Los respaldos hechos antes de esta migracion (bin/respaldar.php)
--     todavia la tienen: si la idea es dejar de guardarla, hay que depurarlos segun la politica de retencion de cada uno.
--   * El tramo queda como estaba AL ALTA (o, en lo que ya existe, el dia de esta migracion): no envejece. Quien tenia 24 y
--     cumple 25 sigue en «18-24» hasta que alguien lo corrija. Es el precio de no guardar la fecha.
--   * La base ya no puede comprobar la edad de mayoria de cada pais (19 en Canada, 20 en Tailandia, 21 en Singapur...): sin la
--     fecha solo ve el tramo, y «18-24» no dice si son 18 o 21. Esa regla (paises.mayoria_de_edad, que sigue) la exige
--     ahora ClienteRepository::crear con la fecha exacta, antes de guardar. Tampoco puede comprobar un cambio de pais
--     (el trigger de la 010 lo hacia): la app no tiene un flujo que cambie el pais de un cliente; si se agrega, tiene que pedir la fecha de nuevo. La base conserva lo que si puede exigir: un cliente
--     no puede entrar ni pasar a «Menor de 18» (un trigger sobre altas y cambios del rango). Los triggers de las migraciones 007 y
--     010 se retiran porque leian la fecha.
--
-- Los tramos son los de RangoEdad (que calcula lo mismo en PHP): «Menor de 18», «18-24», «25-34», «35-44», «45-54», «55-64»,
-- «65+», y «Fecha inválida» para una fecha de nacimiento posterior a hoy. Un usuario del funnel puede ser menor (todavia no es
-- cliente); un cliente no.
--
-- Clientes que ya eran menores (la 007 avisaba que la base de produccion podia tenerlos): no frenan la migracion y siguen siendo
-- editables en todo lo demas, como con la 007; el trigger solo mira las altas y los cambios del rango. Para verlos despues:
--     SELECT id, nombre FROM clientes WHERE rango_de_edad IN ('Menor de 18', 'Fecha inválida');
--
-- Corre en una transaccion: si algo falla, no queda nada a medias.

ALTER TABLE clientes        ADD COLUMN IF NOT EXISTS rango_de_edad TEXT;
ALTER TABLE usuarios_funnel ADD COLUMN IF NOT EXISTS rango_de_edad TEXT;

DO $$
BEGIN
    -- Solo si todavia existe la fecha: la migracion puede volver a correr sin la columna y no tiene que fallar.
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'clientes' AND column_name = 'fecha_nacimiento') THEN
        UPDATE clientes SET rango_de_edad = CASE
            WHEN fecha_nacimiento > CURRENT_DATE THEN 'Fecha inválida'
            WHEN date_part('year', age(fecha_nacimiento)) < 18 THEN 'Menor de 18'
            WHEN date_part('year', age(fecha_nacimiento)) < 25 THEN '18-24'
            WHEN date_part('year', age(fecha_nacimiento)) < 35 THEN '25-34'
            WHEN date_part('year', age(fecha_nacimiento)) < 45 THEN '35-44'
            WHEN date_part('year', age(fecha_nacimiento)) < 55 THEN '45-54'
            WHEN date_part('year', age(fecha_nacimiento)) < 65 THEN '55-64'
            ELSE '65+'
        END
        WHERE rango_de_edad IS NULL;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'usuarios_funnel' AND column_name = 'fecha_nacimiento') THEN
        UPDATE usuarios_funnel SET rango_de_edad = CASE
            WHEN fecha_nacimiento > CURRENT_DATE THEN 'Fecha inválida'
            WHEN date_part('year', age(fecha_nacimiento)) < 18 THEN 'Menor de 18'
            WHEN date_part('year', age(fecha_nacimiento)) < 25 THEN '18-24'
            WHEN date_part('year', age(fecha_nacimiento)) < 35 THEN '25-34'
            WHEN date_part('year', age(fecha_nacimiento)) < 45 THEN '35-44'
            WHEN date_part('year', age(fecha_nacimiento)) < 55 THEN '45-54'
            WHEN date_part('year', age(fecha_nacimiento)) < 65 THEN '55-64'
            ELSE '65+'
        END
        WHERE rango_de_edad IS NULL;
    END IF;
END
$$;

ALTER TABLE clientes        ALTER COLUMN rango_de_edad SET NOT NULL;
ALTER TABLE usuarios_funnel ALTER COLUMN rango_de_edad SET NOT NULL;

-- La lista cerrada de tramos, igual en las dos tablas: nada que no sea uno de los que calcula RangoEdad.
ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_rango_de_edad_conocido;
ALTER TABLE clientes ADD CONSTRAINT clientes_rango_de_edad_conocido
    CHECK (rango_de_edad IN ('Menor de 18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Fecha inválida'));

ALTER TABLE usuarios_funnel DROP CONSTRAINT IF EXISTS usuarios_funnel_rango_de_edad_conocido;
ALTER TABLE usuarios_funnel ADD CONSTRAINT usuarios_funnel_rango_de_edad_conocido
    CHECK (rango_de_edad IN ('Menor de 18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Fecha inválida'));

-- Un cliente es adulto: no entra, ni pasa a, «Menor de 18» ni «Fecha inválida». Solo se mira una alta o un cambio del rango, para que
-- un cliente anterior a la regla siga siendo editable. El mensaje dice «mayores de edad» porque Validacion::mensajeDeConflicto lo busca.
CREATE OR REPLACE FUNCTION exigir_cliente_adulto() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.rango_de_edad IN ('Menor de 18', 'Fecha inválida') THEN
        RAISE EXCEPTION 'Solo se admiten clientes mayores de edad: el rango de edad «%» no es el de un adulto', NEW.rango_de_edad
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS clientes_adulto_alta ON clientes;
CREATE TRIGGER clientes_adulto_alta
    BEFORE INSERT ON clientes
    FOR EACH ROW EXECUTE FUNCTION exigir_cliente_adulto();

DROP TRIGGER IF EXISTS clientes_adulto_cambio ON clientes;
CREATE TRIGGER clientes_adulto_cambio
    BEFORE UPDATE OF rango_de_edad ON clientes
    FOR EACH ROW WHEN (NEW.rango_de_edad IS DISTINCT FROM OLD.rango_de_edad)
    EXECUTE FUNCTION exigir_cliente_adulto();

-- Los triggers de la 007 y la 010 leian NEW.fecha_nacimiento: sin la columna fallarian en cada alta.
DROP TRIGGER IF EXISTS clientes_mayor_de_edad_alta ON clientes;
DROP TRIGGER IF EXISTS clientes_mayor_de_edad_cambio ON clientes;
DROP FUNCTION IF EXISTS exigir_cliente_mayor_de_edad();

ALTER TABLE clientes        DROP COLUMN IF EXISTS fecha_nacimiento;
ALTER TABLE usuarios_funnel DROP COLUMN IF EXISTS fecha_nacimiento;
