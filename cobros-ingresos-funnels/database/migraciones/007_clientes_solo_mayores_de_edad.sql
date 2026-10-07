-- 007: no se admiten clientes menores de edad, tampoco en la base.
--
-- La empresa no atiende a menores de edad (ni a personas privadas de libertad o
-- interdictas, pero eso no es un dato que la app tenga). ClienteController lo
-- valida en el alta con un mensaje claro; este trigger es la red de seguridad
-- para cualquier otro camino: un script, una carga directa, un bug nuevo. Es lo
-- mismo que hizo la 004 con los valores cerrados: lo que la app valida, la base
-- lo exige.
--
-- Un CHECK no sirve: la edad depende de la fecha de hoy, y un CHECK tiene que dar
-- siempre lo mismo para la misma fila (si no, restaurar un respaldo puede
-- rechazar filas que eran validas cuando se guardaron). El trigger mira solo las
-- altas y los cambios de fecha_nacimiento: un cliente anterior a esta regla que
-- hoy tenga menos de 18 sigue pudiendo editarse (el email, la ciudad) mientras
-- nadie le toque la fecha. Esta migracion no revisa, ni cambia, ni borra las
-- filas que ya existen. Para encontrarlas:
--     SELECT id, nombre, fecha_nacimiento FROM clientes
--     WHERE fecha_nacimiento > CURRENT_DATE - INTERVAL '18 years';
--
-- 18 anios cumplidos, como los cuenta age(): quien cumple 18 hoy ya es mayor. Es
-- el mismo borde de MayoriaDeEdad::EDAD y del primer tramo de adultos de
-- RangoEdad; MayoriaDeEdadTest comprueba que los tres coinciden.
--
-- usuarios_funnel queda como esta: un visitante puede ser menor de edad (un lead
-- no es un cliente); lo que no puede es convertirse, y eso lo frena el trigger de
-- clientes cuando se intenta crear su cliente.

CREATE OR REPLACE FUNCTION exigir_cliente_mayor_de_edad() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.fecha_nacimiento > CURRENT_DATE - INTERVAL '18 years' THEN
        RAISE EXCEPTION 'Solo se admiten clientes mayores de edad (18 anios cumplidos): fecha de nacimiento %', NEW.fecha_nacimiento
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS clientes_mayor_de_edad_alta ON clientes;
CREATE TRIGGER clientes_mayor_de_edad_alta
    BEFORE INSERT ON clientes
    FOR EACH ROW EXECUTE FUNCTION exigir_cliente_mayor_de_edad();

DROP TRIGGER IF EXISTS clientes_mayor_de_edad_cambio ON clientes;
CREATE TRIGGER clientes_mayor_de_edad_cambio
    BEFORE UPDATE OF fecha_nacimiento ON clientes
    FOR EACH ROW WHEN (NEW.fecha_nacimiento IS DISTINCT FROM OLD.fecha_nacimiento)
    EXECUTE FUNCTION exigir_cliente_mayor_de_edad();
