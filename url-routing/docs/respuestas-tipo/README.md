# Respuestas tipo a un pedido de retiro

El sitio **no escribe solo** a quien pide el retiro de una página (ver
[`ACCESO.md`](../ACCESO.md), «Pedidos de retiro»): quien administra responde por
correo, a la dirección que se dejó en el formulario. Estos textos son la base
de esa respuesta, para que sea parecida cada vez y llegue en el idioma de quien
la recibe.

> **Son un borrador sin revisión nativa ni de un abogado.** Los 16 idiomas son
> los de [`marketing/idiomas/`](../../../marketing/idiomas/) más el español; el
> orden para revisarlos está en su
> [`GUIA-REVISION.md`](../../../marketing/idiomas/GUIA-REVISION.md). Lo que se
> promete en ellos (el plazo, qué se hace con los datos) tiene que coincidir con
> [`TERMINOS-DE-USO.md`](../../../TERMINOS-DE-USO.md) una vez que lo revise el
> abogado. **⚖️**

## Cuál usar

Cada archivo, `respuestas.<idioma>.md`, tiene cuatro textos, con los mismos
títulos en todos:

| # | Cuándo | Qué dice |
|---|---|---|
| 1 | Llegó el pedido y lo estás revisando (la página queda oculta) | Que se recibió, que la página ya no la ven los visitantes y en cuántos días hábiles se responde |
| 2 | Decidiste **retirar** | Que se retiró, que si era una persona su nombre deja de aparecer en las demás páginas, que **retirar no es borrar** y que, si quiere que además se corrijan o se borren los datos, lo diga |
| 3 | Decidiste **rechazar** | Que se revisó y no se retira, la razón y que puede aportar más información |
| 4 | No alcanza para decidir | Qué falta, y que se sigue cuando llegue |

Responde en el idioma en que escribió quien pidió el retiro. Si el pedido no
dice en cuál y no lo puedes saber, usa el de la página (el subdominio: `spa.` o
`eng.`) o el inglés.

## Qué reemplazar

Todo lo que va entre llaves se reemplaza antes de enviar:

| Marca | Qué poner |
|---|---|
| `{numero}` | El número del pedido, el que se ve en `/0/3/` |
| `{pagina}` | La página, con su dirección completa, por ejemplo `https://…/6128473105/` |
| `{dias}` | Los días hábiles que prometen los términos de uso (hoy **[10] ⚖️**) |
| `{razon}` | Solo en el texto 3: la razón en una o dos frases, sin repetir lo que se dijo de la persona |
| `{que_falta}` | Solo en el texto 4: qué información se necesita |
| `{firma}` | Tu nombre o el del sitio |

## Cuidado al responder

- **No repitas la acusación ni los datos** que contenía el pedido o la página. La respuesta viaja por correo, sin cifrar.
- **No prometas más de lo que hace el sitio**: retirar oculta la página, no borra los datos.
- Anota la respuesta en la nota de la decisión de `/0/3/`, para que quede en el historial.
- Si el pedido trae amenazas, no respondas con prisa: ver [`RECLAMOS.md`](../../../RECLAMOS.md) y consulta al abogado.
