# Citas célebres para Contrastocolor

Apoyo de marketing para el etiquetador POS **Contrastocolor** (el proyecto de etiquetado *part-of-speech* de [`Listado-colores/`](../Listado-colores/)): citas de personas célebres, en varios idiomas, en las que **cada palabra va en el color de su categoría gramatical**. La cita en color enseña lo que hace el etiquetador sin tener que explicarlo.

**Estado: documentación, una paleta provisional con su prototipo y el registro de revisiones; todavía no hay citas.** Las decisiones de diseño se tomaron el 24 y el 25 de octubre de 2026. Las citas candidatas de [`CRITERIOS.md`](CRITERIOS.md) son autores a investigar, no citas verificadas, y las frases del prototipo son frases de prueba, no citas.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Audiencia | Lectores y público general |
| Canal | El sitio de Contrastocolor y el correo. Sin redes sociales ni impreso por ahora |
| Tono | Cercano y lúdico, con los límites de abajo |
| Idiomas | Los 30 de `Listado-colores/`, con **códigos de 3 letras** (ISO 639-3), como alcance a largo plazo. Hoy solo se publica lo que una persona que habla el idioma haya revisado, y eso es `spa` |
| Etiquetas | Manuales, revisadas por una persona que hable el idioma |
| Script | PHP sin dependencias |
| Marca | «Contrastocolor» directamente, sin marcador |
| Piloto | **1 idioma (`spa`) × 6 citas = 6 fichas**, revisadas por la persona. No habrá revisores nativos de otros idiomas por ahora; los demás idiomas quedan como borrador interno hasta que los haya |
| Medición | Clics y aperturas, **pospuesta**: todavía no hay audiencia ni herramienta de correo. Se prepara el material y la prueba queda pendiente. **Sin ella no sabemos si la cita coloreada sirve más que la plana** |
| Cobertura de los 30 | Una cita original por idioma primero; una «cita ancla» traducida a los 30 después |
| Registro de revisiones | El formato de `Listado-colores/` ([`REVISIONES.md`](REVISIONES.md)) |

**Límites del tono lúdico.** El juego va en el acompañamiento de la pieza (la pregunta, el reto, la pista), nunca en la cita ni en los hechos. Sin burlas a los autores, a un idioma o a quien se equivoque en el juego, y sin prometer nada del producto.

## Qué hay en esta carpeta

| Archivo | Para qué |
|---|---|
| [`IDEAS.md`](IDEAS.md) | Las ideas de contenido y formato, justificadas, con su utilidad, su esfuerzo y de qué dependen. Marca las dos elegidas. |
| [`CRITERIOS.md`](CRITERIOS.md) | Qué citas usar: largo, idiomas, autores, derechos y cómo se verifica cada una. |
| [`PLAN.md`](PLAN.md) | Las fases de trabajo, las preguntas abiertas, cómo se mide si sirve y los riesgos. |
| [`REVISIONES.md`](REVISIONES.md) y [`revisiones.csv`](revisiones.csv) | Quién ha revisado qué, con el formato del registro de `Listado-colores/`. Empieza vacío. |
| [`datos/paleta.json`](datos/paleta.json) | Un color por categoría UPOS, en modo claro y oscuro, y la marca (estilo de subrayado) de cada grupo. **Provisional.** |
| [`datos/ejemplos.json`](datos/ejemplos.json) | Seis frases de prueba (no son citas) en `spa`, `eng` y `deu`, con sus etiquetas puestas a mano y sin revisión nativa. |
| [`datos/idiomas.json`](datos/idiomas.json) | Los 30 idiomas con su código de 3 letras y el de 2 de `Listado-colores/`. |
| [`prototipo/index.html`](prototipo/index.html) | La paleta en una sola página, sin servidor: leyenda, frases de prueba, tema claro y oscuro, simulación de daltonismo y marcas. Generado: no se edita a mano. |
| [`bin/`](bin/) | `comprobar-paleta.php`, `prototipo.php` y `comprobar-revisiones.php` (PHP sin dependencias). |

## Idea central

Una cita célebre sola es contenido genérico: cualquiera la publica. Lo que la distingue aquí es verla **coloreada por categoría gramatical** (sustantivo, verbo, adjetivo…), con la misma leyenda en todas las piezas y, siempre que se pueda, **primero en su idioma original** y después traducida.

Un color distinto por palabra no diría nada. El color es **por categoría**: todos los sustantivos comparten color, y así se ve la estructura de la frase. El estándar de categorías es UPOS (Universal Dependencies, 17 categorías; ver `CRITERIOS.md`), el mismo que usa `Listado-paises/` para medir las palabras anotadas de cada lengua.

## Reglas de partida

Heredamos la disciplina de [`marketing/`](../marketing/): ninguna frase entra sin respaldo, y ninguna promesa sobre el producto se publica sin comprobarla.

1. **Cada cita tiene fuente primaria**: obra, edición, página o verso, y dónde se comprobó. Las citas «atribuidas a» sin fuente no entran; las citas falsas de expertos son justo lo que la guía ética del repo ([`psychology-and-marketing/02-ethical-neuromarketing.md`](../psychology-and-marketing/02-ethical-neuromarketing.md)) pide evitar.
2. **Texto original, no traducción de segunda mano.** La traducción sale del original y se marca como borrador hasta que la revise un hablante nativo (como en [`marketing/idiomas/GUIA-REVISION.md`](../marketing/idiomas/GUIA-REVISION.md)).
3. **Ninguna afirmación sobre el producto.** Una cita coloreada no demuestra que el etiquetador acierte. **No encontré código del etiquetador en este repositorio** (solo la evaluación de su viabilidad en [`Listado-paises/`](../Listado-paises/)): las etiquetas son manuales y las revisa una persona que hable el idioma, y cada pieza lo dice. Si un día se usa un etiquetador externo o el propio Contrastocolor, la pieza lo dice con su versión. Si algo promete una capacidad de Contrastocolor, se verifica antes, como en `marketing/VERIFICACION.md`.
4. **Los colores no son hechos.** `Listado-colores/` aclara que los significados psicológicos y místicos de los colores son tradición o hipótesis. Ninguna pieza presenta un significado de color como un efecto comprobado.
5. **Derechos.** Solo autores de dominio público, y traducciones propias. Los criterios y el límite de fechas están en `CRITERIOS.md`; el caso de la marca «Contrastocolor» está en [`PREGUNTAS-PARA-ABOGADO.md`](../PREGUNTAS-PARA-ABOGADO.md), pregunta 30. Como las piezas llevan el nombre directamente, si esa pregunta acaba en un cambio de nombre hay que reescribirlas.

## Relación con otros proyectos

- [`marketing/`](../marketing/): es el apoyo de marketing de **conce.com** (genealogía) y su README dice que Contrastocolor no usa esos textos. Esta carpeta es el equivalente para Contrastocolor y toma de allí el patrón (traducciones como borrador, guía de revisión nativa y un documento de verificación).
- [`Listado-colores/`](../Listado-colores/): los 30 idiomas y los nombres de color, para las leyendas y para la idea «Una cita, un color». Sus exportes usan códigos de 2 letras (`es`); esta carpeta usa los de 3 (`spa`), y la equivalencia está en `CRITERIOS.md`. Su `exportes/lexico-ud.tsv` ya trae la categoría UPOS de los nombres de color, un punto de partida para etiquetar.
- [`Listado-paises/`](../Listado-paises/): las palabras anotadas por lengua, que deciden en qué idiomas empezar.
- [`copyright-laws/`](../copyright-laws/): los plazos de protección por jurisdicción, para comprobar si un autor es de dominio público. Es informativa y no es asesoría legal.

No depende de ningún proyecto en tiempo de ejecución.

## Cómo probar esta carpeta

Desde la raíz del repositorio, con PHP 8.3 y sin instalar nada:

```bash
php citas-pos/bin/comprobar-paleta.php        # las 17 categorías, contraste de 4,5:1 en claro y oscuro, colores confundibles (--lista para verlos)
php citas-pos/bin/prototipo.php --comprobar   # prototipo/index.html al día con datos/ (sin --comprobar, lo regenera)
php citas-pos/bin/comprobar-revisiones.php    # revisiones.csv válido
```

El workflow [`pruebas-citas-pos.yml`](../.github/workflows/pruebas-citas-pos.yml) corre las tres, con filtro de rutas, en cada PR que toque esta carpeta.

Para ver el prototipo, abre `citas-pos/prototipo/index.html` en un navegador: no necesita servidor ni conexión.

**Lo que estas comprobaciones no cubren:** no hay pruebas de las citas ni de sus traducciones (aún no existen), la paleta no se ha probado con personas con daltonismo ni en un correo real, y la simulación de daltonismo es una aproximación.
