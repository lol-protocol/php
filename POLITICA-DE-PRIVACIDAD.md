# Política de privacidad — borrador

> **Borrador. No es asesoría legal y ningún abogado lo ha revisado.** Lo escribí a partir del
> registro de datos de [`CUMPLIMIENTO-LEY-21719.md`](CUMPLIMIENTO-LEY-21719.md) (§3), que se
> comprobó en el código, y de los [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md). No afirma nada
> que esos documentos no digan.
>
> - Lo que va entre corchetes (`[NOMBRE]`, `[CORREO]`…) **falta**: solo la persona a cargo lo sabe.
> - Lo marcado con **⚖️** depende de lo que diga el texto oficial de la Ley 21.719, que no se ha
>   cotejado (ver §7 de `CUMPLIMIENTO-LEY-21719.md`). No lo publiques con un plazo o una base
>   que no hayas comprobado.
> - Al final hay una lista de lo que hay que completar antes de publicarla.

## 1. Quién es el responsable

[NOMBRE DE LA PLATAFORMA] es un sitio de genealogía administrado por [NOMBRE DEL RESPONSABLE],
con domicilio en [DOMICILIO], Chile. Contacto para todo lo de esta política: [CORREO].

El sitio incluye una tienda (el mismo programa, otro dominio) y, aparte, guías telefónicas
históricas. Esta política cubre los tres. **⚖️** Si el servidor está fuera de Chile, ver el
punto 6.

## 2. Qué datos tratamos y de quién

**De las personas que aparecen en el árbol** (en su mayoría, personas fallecidas):
nombres, apellidos, sexo, padres, fechas y lugares de nacimiento, matrimonio y defunción, y los
documentos que respaldan cada dato (actas, censos). De las guías telefónicas históricas: nombre,
dirección y teléfono de personas, de guías de hasta 1975.

**De quien pide algo al sitio:**

- *Un pedido de retiro* (el formulario para pedir que se oculte una página): el motivo, lo que
  escribiste y tu correo de contacto.
- *Una solicitud de datos* (ver, corregir o borrar los datos de una persona): el derecho que
  pides, lo que escribiste y tu correo de contacto.
- *Quien aportó un árbol:* su nombre y su correo.

**De quien compra en la tienda:** la dirección de envío que escribas, que puede traer un nombre y
un teléfono si los escribes. **No se toman pagos** en el sitio.

**De quien solo visita:** nada que lo identifique. No hay cuentas de visitante. Para frenar el abuso
se guarda un archivo por visitante con una **huella** de la IP (no la IP), que se borra a la hora.
El servidor web, aparte, anota la IP, el navegador y la dirección pedida (ver el punto 5).

## 3. Para qué los usamos y con qué base

| Datos | Para qué | Base ⚖️ |
|---|---|---|
| La ficha, los sucesos y los documentos de las personas | Publicar una genealogía | [POR DECIDIR: interés legítimo, fuente de acceso público o investigación histórica. Depende de las preguntas 2 y 3 de §7 de `CUMPLIMIENTO-LEY-21719.md`] |
| Pedidos de retiro y solicitudes de datos | Atender lo que pides y dejar constancia de que se atendió | [POR DECIDIR: atender una solicitud del titular] |
| Dirección de envío | Entregar un pedido; la boleta, la garantía y las devoluciones tienen que mostrar adónde se mandó | [POR DECIDIR] |
| Huella de IP y registros del servidor | Mantener el sitio y frenar el abuso | [POR DECIDIR: interés legítimo] |

No tomamos decisiones automatizadas sobre personas. El filtro que revisa los textos solo **marca**
para quien revisa; decide una persona.

## 4. Qué se muestra y qué no

- **Quien pueda estar vivo no se muestra.** Un visitante no ve a nadie que haya nacido hace
  menos de 110 años y no tenga fecha de defunción, ni a quien el propietario haya marcado como vivo
  o como privado, ni una página que se haya retirado. Tampoco ve los sucesos que tengan entre sus
  participantes a alguien oculto: de esos solo sobrevive el año, en la ficha de la persona fallecida a
  quien pertenece el suceso.
- **De las guías telefónicas** solo se ve lo de hace más de 92 años, o lo de quien el propietario
  marcó como fallecido con su comprobante. Los negocios que llevan un nombre de pila o un apellido
  común se ocultan igual que una persona.
- **Los pedidos de retiro, las solicitudes de datos y el correo de quien aportó** los ve solo el
  propietario del sitio.

## 5. Cuánto tiempo los guardamos

| Dato | Cuánto dura |
|---|---|
| La ficha, sus sucesos y sus documentos | [PLAZO POR DECIDIR]: hoy no tienen plazo. Se borran si la persona, o quien corresponda, lo pide y procede |
| Pedido de retiro: correo, texto y marcas del filtro | 12 meses después de resolverse; el número, la página, el motivo, el estado y la historia quedan [CONFIRMAR que el sitio tiene programado el borrado] |
| Solicitud de datos: correo y texto | 12 meses después de cerrarla; el número, la persona, el derecho pedido y las fechas quedan [CONFIRMAR que el sitio tiene programado el borrado] |
| Registro de supresiones y de correcciones | Sin plazo. Guarda el id, la fecha y el campo; **nunca el nombre ni los valores** |
| Enlace y clave para leer lo que se guarda de una persona | 7 días por omisión (hasta 90). Solo se guardan sus huellas, y se borran 12 meses después de vencer |
| Cookie de sesión | Caduca a la hora sin actividad, o a las 12 horas |
| Huella de IP del limitador | 1 hora |
| Registro del servidor web (IP, navegador, dirección pedida) | 14 días [CONFIRMAR: vale si el servidor se armó con `vps-setup/`] |
| Registro de la aplicación (errores) | [PLAZO POR DECIDIR]: hoy no hay rotación definida. No trae quién pidió un retiro |
| Copias de seguridad | 30 días (aprobado el 22 de noviembre de 2026). Una copia trae también lo que el sitio oculta |
| Dirección de envío de un pedido | Mientras exista el pedido, sin borrarse sola |

**⚖️** Si la ley fija un plazo máximo de conservación, esta tabla tiene que respetarlo.

## 6. Con quién se comparten y dónde se guardan

- Los datos se guardan en un servidor de [PROVEEDOR], en [PAÍS DEL SERVIDOR]. **⚖️** Si es fuera
  de Chile, comprobar qué exige la ley para una transferencia al extranjero.
- Las copias de seguridad se guardan en [DÓNDE]. Se **podrían cifrar** de modo que el servidor no
  pueda descifrar sus propias copias, pero **por ahora no se cifran** (decidido el 22 de noviembre de 2026): por eso
  viven fuera del servidor y con acceso solo del propietario.
- [CONFIRMAR: no vendemos ni cedemos datos a terceros, y el sitio no carga scripts, anuncios ni
  analítica de terceros.] Solo se entregan a una autoridad que los pida con una orden judicial o la
  facultad para hacerlo.

## 7. Tus derechos y cómo ejercerlos

Puedes pedir **acceso, rectificación, supresión u oposición**, y **⚖️** portabilidad si aplica a estos
datos, respecto de los tuyos o, si ya falleció, de un familiar.

- **Cómo:** por el formulario que enlaza el pie de la página de cada persona (`/s/{id}/`), o escribiendo
  a [CORREO]. Para pedir que se oculte una página, el formulario `/r/{id}/`. Recibir el pedido no
  oculta nada por sí solo: la página se oculta mientras se revisa si el propietario lo decide, o sola
  si pasa el plazo sin respuesta [CONFIRMAR el plazo: 24 horas hoy, y que el sitio lo tiene activado].
- **Recibir tu solicitud no entrega, cambia ni borra nada.** Una persona la lee, **comprueba que
  quien pide es quien dice** [CÓMO SE COMPRUEBA: ver `url-routing/docs/SOLICITUDES.md`] y recién
  entonces responde.
- **Qué te mandamos si pides ver tus datos:** un resumen escrito a mano. Si necesitas ver también lo que
  el sitio oculta a un visitante, te hacemos un enlace y una clave que van **por dos medios
  distintos**, válidos unos días. No mandamos por correo, en claro, lo que el sitio oculta.
- **Corregir** un dato lo corregimos y dejamos constancia de qué campo se cambió, sin guardar los
  valores. **Borrar** a una persona la quita de la base, con sus vínculos y su lugar en colecciones y
  organizaciones; se conservan los documentos y los sucesos que comparte con otras personas, y el
  registro de que se hizo no lleva el nombre.
- **Plazo para responder:** [PLAZO ⚖️]. No lo firmes sin comprobarlo: el borrador de los términos
  promete 10 días hábiles y la ley podría decir otra cosa.
- **Si no estás de acuerdo**, puedes reclamar ante la Agencia de Protección de Datos Personales.
  **⚖️** Confirmar el procedimiento.

## 8. Cómo los protegemos

Todo el sitio va por HTTPS. No hay contraseñas de visitantes. El acceso del propietario es un solo
secreto que se puede rotar, y rotarlo cierra todas las sesiones. Los formularios tienen límite de
intentos. Lo que se guarda de quien visita es una huella, no la IP. Los enlaces y claves de lectura
guardan solo huellas. **No** ciframos la base de datos en reposo (depende del servidor) ni, por ahora, las copias. Si hay una fuga de datos, se sigue [`SI-HAY-UNA-FUGA.md`](SI-HAY-UNA-FUGA.md).

## 9. Cookies

Una sola, técnica (`PHPSESSID`), y solo si usas un formulario (como el pedido de retiro o la compra) o
entras como propietario. Quien solo lee páginas no recibe ninguna. No hay cookies de seguimiento.

## 10. Menores y datos sensibles

No mostramos a nadie que pueda estar vivo, así que no hay fichas visibles de menores. El formulario
de retiro pide un correo a quien lo use, sea quien sea. **⚖️** Un acta puede decir la causa de una
muerte, una religión o un origen: comprobar si la ley los trata como datos de categorías especiales
(pregunta 4 de §7 de `CUMPLIMIENTO-LEY-21719.md`) y qué cambia.

## 11. Cambios a esta política

Si cambia, se publica aquí con su fecha. Última revisión: [FECHA].

---

## Qué falta para poder publicarla

1. Completar los huecos: responsable, domicilio, correo, proveedor, país del servidor y fecha.
2. Elegir la **base** de cada fila del punto 3 (depende de la ley: personas fallecidas, investigación
   histórica) y el **plazo de respuesta** del punto 7. **⚖️**
3. Decidir los plazos marcados **POR DECIDIR** (el de las copias, 30 días, ya está aprobado).
4. Confirmar lo marcado **CONFIRMAR** contra el servidor real: que los borrados programados corren, que
   el registro del servidor dura 14 días y que no hay scripts de terceros.
5. Cambiar «**Hoy**, pedir la supresión no tiene pantalla» en [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md)
   (§7): ya existe el formulario `/s/{id}/`. Y que los términos enlacen a esta política.
6. Que alguien la lea como la leería quien aparece en el árbol, y que un abogado la revise si algún día
   se decide consultarlo ([`PREGUNTAS-PARA-ABOGADO.md`](PREGUNTAS-PARA-ABOGADO.md)).
