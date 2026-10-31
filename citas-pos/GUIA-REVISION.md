# Guía de revisión en español

Para quien revisa las citas en `spa`: hoy, la persona que encarga el trabajo. Dice qué se revisa, cómo se decide cada etiqueta gramatical y cómo dejar la revisión en [`revisiones.csv`](revisiones.csv) (el formato y las reglas del registro están en [`REVISIONES.md`](REVISIONES.md)).

**Por qué importa:** [`bin/render.php`](bin/render.php) solo genera la pieza de una cita si su texto **y** sus etiquetas tienen una revisión `correcto` o `corregido` tuya. Tu revisión es la puerta de la publicación.

**Estado hoy:** ninguna cita tiene texto todavía (la verificación está bloqueada; ver `PLAN.md`), así que lo único revisable son las **etiquetas de las dos frases de prueba en español**, `spa-1` y `spa-2`. Sirven para practicar el criterio antes de que lleguen las citas.

## Qué se revisa

| Alcance | Qué miras | Aplica a `spa` |
|---|---|---|
| `cita` | Que el texto de la ficha sea **exactamente** el de la edición citada | Sí |
| `etiquetas` | Que la categoría de cada palabra sea la correcta en esa frase | Sí |
| `traduccion` | Que la traducción sea fiel | No: las citas en español son originales, no traducciones |

## Revisar el texto de una cita (`cita`)

El validador no comprueba que el texto coincida con la fuente: lo haces tú.

1. Abre la edición que dice la ficha (`edicion`, `ubicacion`) y busca el pasaje.
2. Compara **carácter por carácter**: tildes (*Éste* o *Este*), signos de puntuación y de exclamación o interrogación, mayúsculas, y la ortografía de la edición. **No modernices** la ortografía ni «corrijas» lo que te parezca un error.
3. Mira que los saltos de verso de la ficha sean los de la edición.
4. Si dos fuentes de la ficha difieren, di en la nota cuál debe prevalecer y por qué.

## Revisar las etiquetas (`etiquetas`)

### Principios

- **Se etiqueta el uso en esa frase**, no la palabra suelta: *mucho* es adverbio en «lee mucho» y determinante en «mucha suerte».
- **Solo la categoría** (las 17 de UPOS, ver [`CRITERIOS.md`](CRITERIOS.md)): sin género, número ni tiempo.
- **Cada palabra y cada signo de puntuación es una pieza**, también `¡`, `!`, `,` y `.`.
- **Contracciones y clíticos pegados** (*al*, *del*, *dímelo*) necesitan partir la palabra en dos y van en una serie aparte: en el piloto se evitan. Si aparece uno, marca la cita `dudoso`.
- **Palabras de color:** sigue lo que dice [`Listado-colores/categorias.csv`](../Listado-colores/categorias.csv) (en «el libro rojo» es adjetivo, en «el rojo es mi favorito» es sustantivo).

### Convenciones para el español

Son las de este proyecto. Siguen en general las de los corpus de Universal Dependencies en español, pero **no las pude contrastar con ellos desde el entorno donde se escribió esta guía** (no llega a esos sitios): si alguna te parece mal, no la apliques a ciegas y marca `dudoso` con tu razón. Las definiciones de las 17 categorías están en https://universaldependencies.org/u/pos/.

| Caso | Etiqueta | Ejemplo |
|---|---|---|
| Artículo y posesivo antepuesto | `DET` | **el** perro, **mi** casa |
| Cuantificador antes del sustantivo | `DET` | **mucha** suerte, **toda** la vida |
| Sustantivo común | `NOUN` | perro, libro |
| Nombre propio | `PROPN` | **María** |
| Adjetivo | `ADJ` | pequeño, rojo |
| Participio usado como adjetivo | `ADJ` | un vaso **roto** |
| Verbo pleno | `VERB` | lee, van |
| Participio de un tiempo compuesto | `VERB` | hemos **visto** |
| Auxiliar de un tiempo compuesto | `AUX` | **hemos** visto |
| *Ser* o *estar* que une sujeto y atributo, o en pasiva | `AUX` | la casa **es** blanca; fue **vista** |
| Adverbio | `ADV` | despacio, ayer, **no** |
| Negación *no* | `ADV` | **no** se van (el español de UD casi no usa `PART`) |
| Cuantificador que modifica a un verbo | `ADV` | lee **mucho** |
| Pronombre personal, incluidos los pegados delante del verbo | `PRON` | **yo**, **ellos**, **se** van |
| *Que* conjunción | `SCONJ` | dice **que** viene |
| *Que* relativo | `PRON` | el libro **que** leí |
| Conjunción que subordina | `SCONJ` | **porque**, **aunque**, **si**, **cuando** |
| Conjunción que une | `CCONJ` | **y**, **o**, **pero** |
| Preposición | `ADP` | **de**, **en**, **con**, **para** |
| Numeral | `NUM` | **tres**, **seis** |
| Interjección | `INTJ` | **ay** |
| Puntuación | `PUNCT` | `¡ ! ¿ ? , ; . « »` |
| Símbolo | `SYM` | `%`, `§` |
| Extranjerismo o fragmento que no es español | `X` | (raro; ante la duda, `dudoso`) |

## Las frases de prueba, para practicar

Con la opción «Categoría junto a cada palabra» del [prototipo](prototipo/index.html) ves las etiquetas. Los casos que más merecen tu ojo:

| Frase | Palabra | Etiqueta puesta | Por qué mirarla |
|---|---|---|---|
| `spa-1` | **despacio** | `ADV` | Adverbio formado sin *-mente* |
| `spa-1` | **rojo** | `ADJ` | Palabra de color: adjetivo detrás del sustantivo |
| `spa-1` | **un** | `DET` | Artículo, no numeral |
| `spa-2` | **Ay** | `INTJ` | Interjección entre signos de exclamación |
| `spa-2` | **hemos**, **visto** | `AUX`, `VERB` | Tiempo compuesto |
| `spa-2` | **tres** | `NUM` | Numeral |
| `spa-2` | **no** | `ADV` | Negación: la convención del proyecto es `ADV`, no `PART` |
| `spa-2` | **se** | `PRON` | Pronombre pegado delante del verbo |
| `spa-2` | **porque** | `SCONJ` | Conjunción que subordina |

Estas etiquetas las puso a mano quien armó el prototipo, sin revisión de nadie: por eso están aquí.

## Cómo dejar tu revisión

1. Añade tus filas al final de `revisiones.csv`. Una por cada frase o cita y cada alcance. Estas filas **solo muestran el formato**, no son correcciones reales (`palabra`, `ETIQUETA` y la razón los pones tú):

   ```csv
   spa,etiquetas,spa-1,correcto,TuAlias,2026-10-27,
   spa,etiquetas,spa-2,corregir,TuAlias,2026-10-27,"palabra → ETIQUETA, porque ..."
   spa,cita,spa-c1,dudoso,TuAlias,2026-10-27,"palabra: de qué dudas"
   ```

2. **`correcto`** si no cambiarías nada. **`corregir`** si hay un cambio: escribe la palabra, la etiqueta que propones y por qué. **`dudoso`** si no estás seguro: di de qué dudas. Con `corregir` y `dudoso` la nota es obligatoria.
3. Comprueba el registro:

   ```bash
   php citas-pos/bin/comprobar-revisiones.php
   ```

4. Quien mantiene el proyecto aplica tus correcciones y cambia esas filas de `corregir` a `corregido`; una cita con la etiqueta `corregido` ya cuenta como aprobada para el generador.

Si vuelves a revisar lo mismo, **edita tu fila** en lugar de añadir otra (el historial queda en git).

## Qué significa tu «correcto»

Es **tu juicio como persona que lee español**, no una garantía de que la etiqueta sea la única posible: el registro no comprueba quién eres ni tu relación con el idioma. Si quieres dejar constancia (lengua materna, nivel), ponla en `revisor` o en la nota.

Mientras no haya revisores de otros idiomas, esta guía solo vale para `spa`; los demás idiomas siguen como borrador interno y no se publican.
