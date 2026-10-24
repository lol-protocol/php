# Citas célebres para Contrastocolor — ideas y plan

Apoyo de marketing para el etiquetador POS **Contrastocolor** (el proyecto de etiquetado *part-of-speech* de [`Listado-colores/`](../Listado-colores/)): citas de personas célebres, en varios idiomas, para redes, correo y el sitio.

**Estado: plan, sin contenido publicado.** Esta carpeta aún no tiene citas ni código. Lo que sigue es la propuesta para revisar antes de escribir nada.

## Reglas de partida

Heredamos la disciplina de [`marketing/`](../marketing/): ninguna frase entra sin respaldo, y ninguna promesa sobre el producto se publica sin comprobarla.

1. **Cada cita tiene fuente primaria.** Obra, edición, página o carta, y la URL o referencia donde se comprobó. Las citas «atribuidas a» sin fuente no entran: la mayoría de las frases célebres circulan mal atribuidas.
2. **Texto original, no traducción de segunda mano.** La traducción sale del original, se marca como borrador y necesita revisión nativa antes de publicarse (como en [`marketing/idiomas/GUIA-REVISION.md`](../marketing/idiomas/GUIA-REVISION.md)).
3. **Nada de afirmaciones de producto** en las citas ni en su texto de acompañamiento. Una cita sobre color o sobre palabras no demuestra que Contrastocolor haga lo que dice: si una frase promete algo del etiquetador, se verifica contra el producto, como hace `marketing/VERIFICACION.md`.
4. **Cuidado con los colores.** `Listado-colores/` aclara que los significados psicológicos y místicos de los colores son tradición o hipótesis, no hechos. Ninguna cita puede presentar un significado de color como un efecto comprobado.
5. **Derechos.** Las citas cortas con fuente son lo habitual. Una traducción de un texto con derechos vigentes es una obra derivada: o usamos obras en dominio público, o citamos solo fragmentos breves con la fuente, o lo consultamos con quien lleve el tema legal (ver `PREGUNTAS-PARA-ABOGADO.md`, sobre la marca «Contrastocolor»).

## Ideas

- **Una cita, un color.** Cada cita se asocia a un color de `Listado-colores/colores.csv` (los 148 colores CSS, con su nombre en 30 idiomas). Sirve para tarjetas con el nombre del color en el idioma de la cita.
- **Palabras que cambian de lengua.** Citas sobre traducir, nombrar y el límite del lenguaje, en su idioma original. Encaja con un etiquetador que trabaja con muchas lenguas. Candidata obvia: Wittgenstein, *Tractatus* 5.6 («Los límites de mi lenguaje significan los límites de mi mundo»), a verificar en la edición que se cite.
- **Color y percepción.** Citas de quienes escribieron sobre color: Goethe (*Zur Farbenlehre*), Wittgenstein (*Bemerkungen über die Farben*), Josef Albers (*Interacción del color*). Hay que buscar el pasaje exacto en la edición, no fiarse de versiones que circulan en internet.
- **Gramática como juego.** Citas sobre la forma de las palabras (sustantivo, adjetivo, verbo), para piezas de redes que expliquen el etiquetado de forma ligera.
- **Formatos.** Tarjeta cuadrada para redes (1080×1080), cita en una línea para el pie de correo, y una cita rotativa para el sitio, todas con el idioma y la fuente visibles.
- **Versión multi-idioma sin traducir de más.** Publicar primero la cita en su idioma original con la traducción debajo, y solo en los idiomas que ya tengan revisión.

## Plan

| Fase | Qué se entrega | Cuándo está lista |
|---|---|---|
| 0. Decisiones | Respuestas a las preguntas de abajo | Están anotadas aquí |
| 1. Corpus | Lista de 20 a 30 citas candidatas, cada una con autor, obra, edición, página y estado de verificación | Cada cita tiene fuente comprobada o está marcada como «sin verificar» y no se publica |
| 2. Datos | `datos/citas.json`: id, autor, obra, fuente, idioma del original, tema, color asociado (opcional), traducciones con su estado de revisión | El JSON valida contra un esquema y no tiene citas sin fuente |
| 3. Traducciones | Traducción del original a los idiomas elegidos, como borrador | Un revisor nativo firma cada idioma antes de usarlo |
| 4. Render | Script sin dependencias que genera las tarjetas (SVG o PNG) y el texto del correo, con `--comprobar` para la CI | `--comprobar` pasa sin cambios sin commitear |
| 5. CI | `pruebas-citas-pos.yml` con filtro de rutas: valida el JSON, que cada cita tenga fuente y que las tarjetas estén al día | Verde en un PR de una sola carpeta |

Cada fase va en su propio PR, en borrador, y no se fusiona sin que lo decida una persona. La fase 1 no necesita código: es la que más valor tiene y la que más riesgo quita.

## Decisiones abiertas

Necesito tu respuesta antes de la fase 1:

1. **Idiomas.** ¿Empezamos con los 30 de `Listado-colores/` o con un subconjunto? Sugerencia: `es` y `en` primero, y los demás cuando haya revisor nativo.
2. **Tono.** ¿Serio y sobrio, como el resto de Contrastocolor, o cercano y lúdico? Sugerencia: sobrio, con las citas como protagonistas.
3. **Dónde se publica.** ¿Solo redes, o también el sitio de `url-routing/` y el correo? Cambia el formato de salida.
4. **Lenguaje del script.** ¿PHP sin dependencias como `marketing/render.php` y `portadas-svg/`, o Python como `Listado-colores/`? Sugerencia: PHP, porque es el que ya usa el equipo de marketing.
5. **Marca.** ¿Las tarjetas llevan «Contrastocolor» o el nombre de la marca se decide después (ver `PREGUNTAS-PARA-ABOGADO.md`, pregunta 30)? Sugerencia: marcador `[Marca]` como en `marketing/`, para cambiarlo en una sola línea.

## Cómo probar esta carpeta

Por ahora no hay código. Cuando lo haya, este README dirá cómo correr las pruebas, en la misma forma que el resto de carpetas del repositorio.
