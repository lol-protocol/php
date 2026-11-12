-- 017: la mayoria de edad de Puerto Rico es 21.
--
-- La 014 agrego los territorios habitados con codigo ISO 3166-1 propio con la mayoria de edad por defecto de
-- la 010 (18) y dejo dicha la duda: en Puerto Rico es 21 segun su codigo civil. La persona a cargo del
-- producto decidio corregirlo. El resto de los territorios nuevos se queda en 18 (esa lista no es asesoria
-- legal, ver «Mayoria de edad» en el README).
--
-- Solo cambia la regla para altas y ediciones nuevas: un cliente de Puerto Rico ya cargado con menos de 21
-- anos no se toca, pero el trigger de la 010 le exigira la edad nueva la proxima vez que se edite su fecha de
-- nacimiento o su pais. Para ver a quien afecta:
--     SELECT c.id, c.nombre, c.fecha_nacimiento FROM clientes c
--     WHERE c.pais_codigo = 'PR' AND c.fecha_nacimiento > CURRENT_DATE - make_interval(years => 21);

UPDATE paises SET mayoria_de_edad = 21 WHERE codigo = 'PR' AND mayoria_de_edad = 18;
