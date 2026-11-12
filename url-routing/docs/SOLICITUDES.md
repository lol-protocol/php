# Solicitudes de acceso, rectificación y supresión de una persona

Qué hace quien administra el sitio cuando alguien pide **ver qué se tiene de él o de ella**, que se **corrija**
un dato o que se **borre**. Es el recorrido de esos tres derechos; el de **retirar una página** (el formulario
`/r/{id}/`) está en [`RECLAMOS.md`](../../RECLAMOS.md), y las herramientas, en
[`ACCESO.md`](ACCESO.md) («Acceso y supresión de una persona»).

Lo que está marcado con **⚖️** no está decidido (ver
[`CUMPLIMIENTO-LEY-21719.md`](../../CUMPLIMIENTO-LEY-21719.md)): este documento no inventa un plazo ni un
requisito que la ley pueda tener. Los comandos de abajo se probaron el 11 de noviembre de 2026 contra la base
de demostración.

## El recorrido

| # | Qué pasa | Dónde |
|---|---|---|
| 1 | **Llega la solicitud.** Hoy no hay un formulario para estos tres derechos: [`TERMINOS-DE-USO.md`](../../TERMINOS-DE-USO.md) dice «escribiendo a [CORREO]», y ese dato **falta**: se decidió que sea un correo propio del sitio, pero la dirección real aún no está. (`/r/{id}/` sirve para pedir el retiro o la oposición; si pide además borrar, se atiende aquí.) **Anota la fecha y la hora en que llegó**: de ahí corre el plazo ⚖️ (pregunta 7 de la sección 7 de `CUMPLIMIENTO-LEY-21719.md`). | correo |
| 2 | **Comprobar que quien pide es la persona** (o, si ya falleció, un familiar) antes de mandar o cambiar nada. **⚖️ Sin decidir**: ver la sección siguiente. | — |
| 3 | **Encontrar el id.** Busca por nombre en `/b/10/{texto}/` **entrando como propietario** ([`ACCESO.md`](ACCESO.md), «Entrar como propietario»): un visitante no ve a una persona que puede estar viva, y es justo quien suele pedir. Con homónimos, confirma por fechas y lugares, no solo por el nombre. | `/b/10/…` |
| 4 | **Acceso.** `php bin/persona.php acceso <id>` (JSON). Mira abajo qué contiene y qué no. | servidor |
| 5 | **Rectificación.** A mano, en una transacción y con un respaldo antes, y después `php bin/persona.php rectificacion <id> <tabla.columna> [--pedido=N]`: sección «Rectificar». | base de datos |
| 6 | **Supresión.** `php bin/persona.php suprimir <id> [--pedido=N]` (simulación) y luego `--confirmar`. Retirar **no** es borrar. | servidor |
| 7 | **Responder** a quien pidió, por una persona y no por el sitio (que no escribe solo): sección «Qué responder». | correo |
| 8 | **Anotar**: cuándo llegó, cuándo se respondió y qué se hizo, **sin copiar los datos de la persona** a la nota. | fuera del repositorio |

### Qué trae `acceso`

Una persona con el id dado, en JSON: `persona` (la ficha, con `viva` y las fechas de alta y de cambio),
`familiares` (**solo cantidades**: si tiene padre y madre registrados, cuántos hijos y otros vínculos; los
nombres de los parientes son datos de ellos), `sucesos` (con descripción y su papel en cada uno), `registros`
que los documentan, `colecciones` y `organizaciones` en las que está, `ids_que_redirigen_a_ella`,
`pedidos_de_retiro` (número, motivo y estado: **no** quién los pidió ni qué escribió), `correcciones` (qué campo se
corrigió y cuándo: **no** los valores) y `generado_en`.

**No trae los textos libres** que la nombran sin estar ligados a ella (la descripción de otro suceso, el título
de un registro, la nota de una colección). Para verlos, corre la simulación `php bin/persona.php suprimir <id>`
**sin** `--confirmar`: imprime esa lista (tabla, columna e ids) y termina con «Nada cambió». Esos textos hay que
leerlos a mano, y la búsqueda que los encuentra es una red con agujeros (un apodo, una falta de ortografía).

## Comprobar quién pide ⚖️

`acceso` imprime también lo que un visitante **no** ve. Una solicitud falsa podría usarse para sacar esos datos,
sobre todo los de una persona viva, que es justo lo que el sitio oculta. **Decidido el 11 de noviembre de 2026:** pedir un dato que solo la persona sabría y que **no esté en lo que se
muestra** (un dato de la ficha que el sitio no publica), y responder **solo al correo desde el que se escribió**,
sin recoger documentos nuevos. Falta ponerlo en práctica y ensayarlo con datos reales. Las demás salidas, con lo que
cuestan, para tenerlas delante:

| Salida | Protege de | Cuesta |
|---|---|---|
| Responder solo al correo desde el que se escribió | Nada de la identidad: demuestra que quien escribe controla ese buzón, no que sea la persona | Nada |
| Pedir un dato que solo la persona sabría **y que no esté en lo que se muestra** | Quien solo copió lo público | Hay que tener ese dato en la ficha; y pedirlo puede revelar el dato |
| Pedir copia de un documento de identidad | Casi todo, si se revisa | Se recogen datos **nuevos** (lo contrario de guardar lo mínimo) y hay que decidir dónde se guardan y cuándo se borran |
| Responder con lo que cualquier visitante ya ve, y confirmar si hay algo más | Filtrar lo oculto | La persona no recibe todo lo que pidió |
| Si ya falleció: pedir el parentesco | Un tercero que se hace pasar por familiar | Qué prueba basta ⚖️ |

Pedir el dato puede revelarlo: no lo nombres en la pregunta («dime una fecha o un lugar de la ficha que no sea
pública», no «¿naciste en X?»). Si no responde bien, o la ficha no tiene un dato así, **la salida de `acceso` no se
manda**: sirve para que quien administra mire qué hay, y se responde con lo que cualquier visitante ya ve.

## Rectificar

Un dato cambiado de la ficha puede cambiar **quién la ve** (una fecha de nacimiento nueva puede dejar a la persona
como «Persona viva»). Hay un comando para los datos que más se piden, y todo lo demás se hace a mano.

**Con el comando** (`bin/persona.php rectificar`). Primero **respaldo** ([`DESPLIEGUE.md`](DESPLIEGUE.md)). Sin
`--confirmar` solo cuenta lo que haría, y lo cuenta de verdad: hace el cambio dentro de una transacción y la deshace.

```
php bin/persona.php rectificar <id> personas.apellidos='Quintana Soto' [--pedido=N]                # simulación
php bin/persona.php rectificar <id> personas.apellidos='Quintana Soto' [--pedido=N] --confirmar    # lo hace
php bin/persona.php rectificar <id> sucesos.fecha=1950-05-05 --suceso=<N> --confirmar              # un suceso suyo
```

- **Campos:** `personas.nombres`, `personas.apellidos`, `personas.sexo` (F, M o X), `sucesos.fecha` (AAAA-MM-DD, entre
  el año 1000 y hoy) y `sucesos.descripcion`. Con otro campo se niega y dice cuáles hay.
- **Los de `sucesos`** piden `--suceso=N` (los ids salen de `acceso`) y solo se aceptan si la persona es **el sujeto
  del suceso y el único**: un matrimonio tiene dos, y también son datos de la otra persona; ese se corrige a mano.
- **Un cambio, una fila:** cambia exactamente una fila, pone `actualizado_en = CURRENT_TIMESTAMP`, se niega si el valor
  ya es ese y anota la corrección en `rectificaciones_persona` (el campo, nunca los valores). El valor **no** se
  repite en la salida.
- **Dice quién la ve:** «CAMBIA lo que ve un visitante: la ve visible y ahora oculta» si, por ejemplo, la fecha nueva la
  hace pasar por una persona viva; mira eso antes de confirmar.
- **Apellidos:** no cambia `personas.grupo_id` (el grupo de ese apellido). Si el grupo ya no está en los apellidos
  nuevos, lo avisa para que lo revises (`bin/fusionar.php grupo` o `personas.grupo_id` a mano).
- Si lo que se corrige es «está viva» o «ya falleció», no es esto: es la marca de vida (`/{id}/5/`).
- Miras la página como visitante y como propietario.

**A mano**, para cualquier otro dato (los vínculos, los padres, el lugar de un suceso, un suceso compartido). Con
cuidado y en este orden:

1. **Respaldo** antes ([`DESPLIEGUE.md`](DESPLIEGUE.md)).
2. **Comprueba la fila** con el id (`SELECT … WHERE id = …`) antes de cambiarla.
3. **Una transacción**, un `UPDATE` por fila, y mira que diga **1 fila** antes de confirmar:

   ```sql
   BEGIN;
   UPDATE personas SET nombres = 'Rosa Elena', apellidos = 'Quintana Soto', actualizado_en = CURRENT_TIMESTAMP
    WHERE id = 6128473105;
   -- debe decir 1 fila; si no, ROLLBACK;
   COMMIT;
   ```

   El mismo patrón sirve para una fecha (`UPDATE sucesos SET fecha = '1950-05-05', actualizado_en = … WHERE id = …`)
   o una descripción. **Pon `actualizado_en = CURRENT_TIMESTAMP`** en la fila que cambió el contenido: es la fecha
   del mapa del sitio y no se mueve sola (migración `010_actualizado_en.sql`). Si cambia el apellido, mira también
   `personas.grupo_id`, el grupo de ese apellido.
4. **Si lo que se corrige es «está viva» o «ya falleció»**, no es un `UPDATE`: es la marca de vida, que tiene su
   página para el propietario (`/{id}/5/`, ver [`ACCESO.md`](ACCESO.md), «Personas que podrían estar vivas»).
5. **Mira la página** como visitante y como propietario.
6. **Anota en la base** qué campo se corrigió: `php bin/persona.php rectificacion <id> <tabla.columna> [--pedido=N]`
   (por ejemplo `personas.apellidos`). Queda en `rectificaciones_persona` con la persona, la fecha, el campo y el
   pedido, y **nunca el valor viejo ni el nuevo**: la nota no debe guardar el dato que se quiso corregir. La fila
   se conserva aunque la persona se suprima después, igual que `supresiones_persona`, y `acceso` la muestra
   (`correcciones`). La referencia de la solicitud y la fecha de respuesta siguen anotándose fuera del repositorio.

## Qué responder

Desde el correo de contacto y por una persona (el sitio no escribe solo). **Sin cifrar no se manda el JSON**:
el correo viaja en claro, y es la misma razón por la que el aviso de `bin/pedidos-retiro.php` no dice quién pidió
ni qué escribió. **Decidido el 12 de noviembre de 2026:** se manda un **resumen legible**, escrito a mano a partir de
`acceso`, sin JSON: lo que un visitante ya ve de esa persona, más las **cantidades** de lo demás (sucesos, registros,
colecciones, parientes) y qué campos se corrigieron. **Lo que el sitio oculta no se manda por correo**: si la persona
necesita verlo, queda sin decidir (una entrega con clave por otro medio) hasta que pase con una solicitud real.
Estos textos son un punto de partida; **el plazo de respuesta sigue ⚖️ sin decidir**, no prometas uno:

- **Acceso.** «Recibimos tu solicitud el [FECHA]. Comprobamos [lo decidido en la sección anterior]. Esto es lo que
  tenemos de [NOMBRE]: [resumen]. Si algo no es correcto, dínoslo y lo corregimos.»
- **Rectificación.** «Recibimos tu solicitud el [FECHA]. Corregimos [qué dato] el [FECHA]. Puedes verlo en [enlace].»
- **Supresión.** «Recibimos tu solicitud el [FECHA]. Borramos la ficha de [NOMBRE]. Los sucesos que comparte con
  otras personas **se conservan** sin ella, porque también son datos de las otras. Una copia de seguridad puede
  guardarla hasta [PLAZO: 30 días si se aprueba la propuesta de `DESPLIEGUE.md`, sección 11 ⚖️].»
- **Si no se puede atender** (no se comprobó quién pide, o no hay nada de esa persona): dilo, con la razón.

Los textos tipo en 16 idiomas de [`respuestas-tipo/`](respuestas-tipo/README.md) son solo para **retiros**; para
estas solicitudes todavía no hay.

## Lo que falta

- **Poner en práctica la comprobación de quién pide** (sección anterior) y ensayarla.
- **La dirección del correo propio del sitio** que reemplaza el `[CORREO]` de los términos (o un formulario como
  `/r/{id}/`): hay que preguntarla, no se inventa.
- **Los plazos** de respuesta y de conservación de la nota de cada solicitud. El de las copias de seguridad tiene una
  propuesta de 30 días en [`DESPLIEGUE.md`](DESPLIEGUE.md) (sección 11), sin aprobar ni puesta en práctica.
- Ensayar todo con **datos reales**, no con la base de demostración: la guía está en
  [`ENSAYO-SOLICITUDES.md`](ENSAYO-SOLICITUDES.md).
