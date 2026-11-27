# Privacidad: qué hace esta carpeta y qué hay que decidir antes de ponerla en una ficha

> **No es asesoría legal.** Describe lo que el código de esta carpeta hace hoy (se puede comprobar en el código y en
> las pruebas) y lista lo que habría que decidir **antes** de que la plataforma use estos datos al lado de personas
> concretas. Lo que dice la ley no está comprobado contra el texto oficial: ver
> [`CUMPLIMIENTO-LEY-21719.md`](../CUMPLIMIENTO-LEY-21719.md), que explica con cuánta confianza se sabe cada cosa, y
> [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md), el porqué de todo.

La salud y las causas de muerte son **datos sensibles**, y en genealogía los de un antepasado alcanzan a sus
descendientes vivos. Por eso esta carpeta se mantiene a propósito del lado de la estadística y el vocabulario.

## 1. Qué guarda la carpeta

| Dato | ¿De personas? | Comentario |
|---|---|---|
| Esperanza de vida, mortalidad, bandas | No | Cifras de población por país y década; salen de fuentes abiertas. |
| Epidemias, muertes por año en Londres, tasas de Inglaterra | No | Totales de una ciudad o un país, no de personas. |
| Glosario de causas de defunción | No | Vocabulario: qué significaba «hidropesía» en un registro. |
| Enfermedades hereditarias | No, pero **delicado** | Condiciones y las poblaciones donde su frecuencia es mayor. Atribuida a una persona o a una familia, esa información sería sensible (salud y origen étnico a la vez): aquí no se atribuye a nadie. |
| `datos/revisiones.csv` | **Sí: de quien revisa** | Lleva el nombre que cada revisor escribe y la fecha, y queda en el historial de git. Se usa el nombre que la persona quiera publicar; si pide que se quite, se quita la fila (el historial de git conserva la anterior). |
| `vista.html` | No | Funciona sin servidor: nada sale del navegador y no carga nada de afuera (una prueba lo comprueba). Lo que se marca al revisar el glosario no se guarda. |

No hay datos de personas reales en las pruebas ni en los ejemplos.

## 2. Lo que el código impide

- **Las consultas de edades de muerte y supervivencia** (`probabilidad_de_llegar_a`, `edades_de_muerte_comunes`…) son
  **estadística de población por país y década**, y **solo reciben país, año, sexo y edades o cantidades**: una prueba
  (`test_el_contrato_ninguna_funcion_recibe_una_persona`) falla si alguien les agrega un parámetro que no esté en
  `PARAMETROS_PERMITIDOS`. Dicen cómo se repartían las muertes en una población, no si algo es verosímil en una
  familia concreta ni qué riesgo tiene alguien.
- **Ninguna consulta recibe una persona, una ficha, un apellido ni un origen.** Las funciones de `consultas.py`, de
  `php/src/Mortalidad.php` y de `js/mortalidad.js` reciben términos, años, países, regiones y edades. Una prueba
  (`test_las_hereditarias_son_de_poblacion_no_de_personas`) fija que la tabla de hereditarias solo habla de
  poblaciones.
- **El glosario devuelve posibilidades con su confianza, no diagnósticos.** `causa_moderna` puede devolver varias
  causas para un mismo término, o ninguna, y «calentura» sale como `baja` («no dice la causa»).
- **Una cifra de esperanza de vida no es la de una persona.** Es de período y de toda una población, y las
  consultas lo dicen.

## 3. Si la plataforma muestra causas de muerte o enfermedades en fichas

Es otro cambio, con su revisión, y no lo hace esta carpeta. Estas son las decisiones que habría que tomar; no están
tomadas:

1. **Mostrar el registro, no la interpretación.** Poner la causa tal como está escrita en el documento (con su cita)
   y, aparte, la lectura moderna **como pista con su confianza**, marcada «no es un diagnóstico». Nunca «murió de
   tuberculosis» a partir de «tisis» sin decir que es una interpretación.
2. **No ligar enfermedades hereditarias a una persona o familia**, ni inferir quién es portador por su origen, su
   apellido o su lugar de nacimiento. La tabla de hereditarias es contexto médico general, no un cruce.
3. **Vivos y muertos.** La causa de muerte de un fallecido reciente la leen sus descendientes vivos. Hay que fijar
   desde cuándo se muestra y a quién (ver cómo trata [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md) a los vivos y a
   los muertos).
4. **Texto libre.** Lo que una persona escriba como causa de muerte debe pasar por el filtro de
   [`defamatory-content-review/`](../defamatory-content-review/README.md) antes de publicarse (la salud y el suicidio
   figuran entre los temas íntimos que ya detecta, según `PRIVACIDAD-Y-HONRA.md`).
5. **Rectificar y ocultar.** Quien reclame por una causa de muerte debe poder pedir que se oculte o se corrija, con el
   procedimiento de [`RECLAMOS.md`](../RECLAMOS.md).
6. **Comparar con el promedio.** «Tu bisabuelo vivió más que el promedio de su época» es una estadística; «tienes
   riesgo de…» sobre un descendiente vivo sería **perfilado de salud**, y no debe salir de estos datos.
7. **Causas especialmente delicadas** (suicidio, enfermedad mental, sífilis, lepra, partos): decidir si se muestran
   por defecto o solo a quien las pida. El glosario las incluye porque aparecen en los registros, no para destacarlas.
   **Lo que hace hoy `vista.html`** (decisión de la persona a cargo, 25 de noviembre de 2026): en la consulta del
   glosario, un término que puede significar una de esas causas (`suicidio`, `locura`, `sifilis`, `lepra`,
   `muerte_de_parto` y `fiebre_puerperal`; la lista es `CAUSAS_DELICADAS` en `comun.py`) sale **detrás de un aviso** con
   un botón «Mostrar de todos modos»; el aviso se repone al cambiar de término. En la pestaña «Revisar el glosario» esos
   términos quedan ocultos hasta marcar «Mostrar también las causas delicadas», y en las páginas de `por-idioma/` van en
   un desplegable cerrado (`<details>`) aparte de la tabla (decisión del 26 de noviembre de 2026). Es solo
   presentación: los datos (`causas_historicas.csv`), `causa_moderna` y los ports no cambian, y el CSV sigue en GitHub
   tal cual. Lo que decida la plataforma para sus fichas es otra cosa y sigue abierto.

## 4. Preguntas abiertas

Para la persona a cargo o, si se decide consultarla, para un abogado
([`PREGUNTAS-PARA-ABOGADO.md`](../PREGUNTAS-PARA-ABOGADO.md) es el documento donde van las del sitio entero):

- ¿La Ley 21.719 protege los datos de personas **fallecidas**, o solo los de vivas? ¿Y los datos de salud de un
  antepasado cuando identifican, por parentesco, a un descendiente vivo?
- ¿Cuántos años después de la muerte deja de ser sensible una causa de muerte?
- ¿La interpretación moderna de un término («tisis» → tuberculosis) es un dato de salud **nuevo** que crea el sitio, o
  solo una lectura del registro original?
- ¿Se necesita el consentimiento de quien revisa el glosario para publicar su nombre (§1), o basta con que lo escriba
  él mismo?
