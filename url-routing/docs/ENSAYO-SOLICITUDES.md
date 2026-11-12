# Ensayo de las solicitudes de acceso, rectificación y supresión

Una guía para que **quien administra el sitio** corra, una vez y con datos reales, el recorrido de
[`SOLICITUDES.md`](SOLICITUDES.md) antes de abrir la plataforma al público. Los comandos de abajo se corrieron el 12 de
noviembre de 2026 contra la base de demostración (`bin/migrate.php genealogy --seed`), y las salidas que se citan son
las de esa corrida. Lo que **no** se ha hecho nunca es correrlos contra tu base real, en tu servidor: eso es lo que
esta guía pide.

Regla de oro: **la única prueba que cambia datos (supresión con `--confirmar`) se hace sobre una copia restaurada en
otra base, nunca sobre la de producción.** Todo lo demás solo lee o solo anota.

## Antes de empezar

- [ ] Una **copia de seguridad reciente** y que sepas restaurar ([`DESPLIEGUE.md`](DESPLIEGUE.md), sección 11).
- [ ] Una persona de confianza que **se preste a pedir sus propios datos** (paso 2), y que esté en la base.
- [ ] Una **hoja aparte** (fuera del repositorio) para anotar fechas y qué se hizo, sin copiar datos de nadie.

## 1. Encontrar a la persona

Entra como propietario ([`ACCESO.md`](ACCESO.md), «Entrar como propietario») y busca en `/b/10/{texto}/`.
**Mira:** que aparezca aunque pueda estar viva (un visitante no la ve), y que con homónimos puedas distinguir por
fechas y lugares. Apunta el id.

## 2. Comprobar quién pide (la decisión del 11 de noviembre de 2026)

Pide a tu persona de prueba que te escriba **desde su correo**. Pídele un dato de su ficha que **el sitio no
publica**, sin nombrarlo tú en la pregunta. Responde solo a ese mismo correo.

**Mira:**
- [ ] ¿Esa ficha tiene un dato así? Si no, `acceso` no se manda y se responde con lo que cualquier visitante ya ve
  (es el caso de las personas con poca información: anótalo, es información para decidir si la regla alcanza).
- [ ] ¿Sabrías decir cuánto tardó y qué te costó? Eso es lo que sirve para decidir el plazo (⚖️).

## 3. Acceso

```
php bin/persona.php acceso <id>
```

Imprime JSON. **Mira:**
- [ ] `persona`: los datos de la ficha; `familiares`: solo cantidades (nunca nombres de parientes).
- [ ] `sucesos`, `registros`, `colecciones`, `organizaciones`, `ids_que_redirigen_a_ella`.
- [ ] `pedidos_de_retiro`: número, motivo y estado, **sin** quién pidió ni qué escribió.
- [ ] `correcciones`: qué campo se corrigió y cuándo, **sin** valores.
- [ ] Que **nada** de lo que imprime sea el nombre de otra persona.

El JSON **no se manda**. Escribe a mano el resumen legible que decide `SOLICITUDES.md` (lo que un visitante ya ve más
cantidades) y compáralo con lo que ve un visitante en el sitio: no debe contener nada que el visitante no vea.

Para los textos libres que la nombran sin estar ligados a ella, corre la simulación de la supresión (paso 5, sin
`--confirmar`): lista tabla, columna e ids para que los leas a mano. **Mira** si encuentra su nombre escrito de otra
forma (un apodo, una falta de ortografía): no debería, y por eso esos textos se leen a mano y no se confía en el listado.

## 4. Rectificación

Con la copia de seguridad hecha, sigue «Rectificar» de [`SOLICITUDES.md`](SOLICITUDES.md): un `UPDATE` por fila, en una
transacción, con `actualizado_en = CURRENT_TIMESTAMP`, y mira que diga **1 fila**. Después anota la corrección:

```
php bin/persona.php rectificacion <id> personas.apellidos [--pedido=N]
```

Dice `Anotada la corrección 1: personas.apellidos de la persona <id>. No se guardó ningún valor.`

**Mira:**
- [ ] Que la página de la persona, como visitante y como propietario, muestre el dato nuevo.
- [ ] Que el campo se escriba como `tabla.columna` en minúsculas: `apellidos` solo falla con «no es una tabla.columna»
  y código de salida 1.
- [ ] Que `acceso` ahora traiga la corrección en `correcciones`, y **que ninguna columna de
  `rectificaciones_persona` guarde un valor**.
- [ ] Si lo que corriges es «está viva» o «ya falleció», **no es un `UPDATE`**: es la marca de vida (`/{id}/5/`).

## 5. Supresión

Primero, en producción, **solo la simulación**:

```
php bin/persona.php suprimir <id> [--pedido=N]
```

Imprime «Se quitaría:» con las cantidades y los textos libres que la nombran, y termina con «Nada cambió». **Mira** que las cantidades cuadren con lo que viste en `acceso`.

Después, **sobre una copia restaurada en otra base** (no la de producción):

1. Restaura la copia en una base nueva (`pg_restore -d otra_base --no-owner`, sección 11 de `DESPLIEGUE.md`).
2. Apunta el comando a esa base: `DB_DSN_GENEALOGY=pgsql:... php bin/persona.php suprimir <id> --confirmar`.
3. **Mira:**
   - [ ] Que la persona desaparezca y que sus hijos conserven su fila.
   - [ ] Que los sucesos compartidos se conserven, sin ella.
   - [ ] Que quede una fila en `supresiones_persona` (id, fecha, pedido y recuento; **ningún nombre**).
   - [ ] Que las filas de `rectificaciones_persona` de esa persona **se conserven**.
   - [ ] Con `--pedido=N`: que el pedido quede como «retirado» con su nota en `/0/3/`.
4. Lee los textos libres que el comando lista: son los que **quedan** y hay que corregir a mano.

## 6. Restaurar una copia vieja y volver a quitar

Con la base de la copia (la del paso 5), restaura la copia **anterior** a la supresión y corre:

```
php bin/persona.php reaplicar            # lista a quién volvió
php bin/persona.php reaplicar --confirmar  # lo quita otra vez
```

**Mira:** que liste exactamente a la persona suprimida y que después `reaplicar` diga «Ninguna persona suprimida está
en la base».

## 7. La respuesta

Redacta, con las plantillas de «Qué responder» de [`SOLICITUDES.md`](SOLICITUDES.md), la respuesta a tu persona de
prueba para acceso, rectificación y supresión. **Mira** que no prometa un plazo que no hayas decidido, que no incluya
el JSON y que salga de la dirección propia del sitio (cuando exista).

## Al terminar

Anota en la hoja aparte, sin datos de nadie: qué pasos fallaron o confundieron, cuánto tardó cada uno y qué
cambiarías. Con eso se decide lo que sigue pendiente en `SOLICITUDES.md` («Lo que falta»): el plazo de respuesta, el de
conservación de la nota de cada solicitud, la entrega de lo oculto y la dirección del correo. Cuando lo hayas
corrido, dilo y se actualiza la fila de ensayo de [`CUMPLIMIENTO-LEY-21719.md`](../../CUMPLIMIENTO-LEY-21719.md).
