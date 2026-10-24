# Criterios: qué citas usar

Qué cita entra, en qué idioma, de quién y con qué comprobaciones. **Ninguna cita está verificada todavía**: los autores de abajo son candidatos a investigar, y este documento no contiene el texto de ninguna cita.

## Resumen

| Criterio | Recomendación | Por qué |
|---|---|---|
| **Largo** | De 6 a 20 palabras (unos 40 a 120 caracteres), una sola frase o un pareado | Con menos de 6 palabras no aparecen varias categorías. Con más de 20 no se lee bien en una tarjeta de 1080×1080 con color. |
| **Derechos** | Autores fallecidos hasta 1925 | Con vida+100 (el plazo más largo que ofrece el filtro de `copyright-laws/`), ya son de dominio público desde el 1 de enero de 2026. |
| **Traducción** | Solo del original, y solo si la traducción es nuestra | Una traducción ajena tiene sus propios derechos aunque el original esté libre. |
| **Idioma** | Los 30 de `Listado-colores/`, con códigos de 3 letras; las oleadas de abajo fijan el orden de entrada | Un idioma se publica solo cuando tiene revisor nativo. |
| **Temas** | Palabras, nombres, traducción, color, ver | Encajan con lo que hace el etiquetador. |
| **Evitar** | Frases motivacionales, atribuidas sin fuente, políticas o religiosas, y cualquiera que parezca respaldar el producto | Son las más mal atribuidas, y un famoso «recomendando» la marca es una afirmación que no podemos sostener. |

## Derechos de autor

La heurística «fallecido hasta 1925» cubre incluso los plazos de vida+100. Es un atajo para descartar, **no una autorización**:

- Hay que comprobar el caso en [`copyright-laws/`](../copyright-laws/). Esa base se declara informativa y no es asesoría legal; está compilada sin revisión de abogado.
- Una **edición** moderna de un texto antiguo puede tener derechos propios (notas, ortografía modernizada, texto fijado por un editor). Se cita una edición concreta, y se prefiere una de dominio público.
- Una **traducción** ajena tiene sus propios plazos. Preferimos traducciones nuestras, revisadas por un nativo.
- Un autor fallecido hace mucho puede tener, en algunos lugares, derechos sobre su nombre o imagen. Para citas con el nombre del autor, y no con su imagen, el riesgo es bajo en autores muy antiguos, pero **se consulta** (ver `PREGUNTAS-PARA-ABOGADO.md`) antes de publicar con la marca.

**Descartados por ahora** (siguen protegidos o no son seguros en todas partes):

- **Josef Albers** (*Interacción del color*, murió en 1976) y ***Bemerkungen über die Farben*** de Wittgenstein (publicado en 1977): protegidos.
- **Wittgenstein**, en general (murió en 1951): libre con vida+70, pero no con vida+100.

## Autores candidatos

Todos fallecieron antes de 1926. **Son candidatos a investigar, sin citas verificadas.**

| Autor | Lengua original | Por qué podría servir |
|---|---|---|
| Goethe | alemán | Escribió una teoría del color (*Zur Farbenlehre*). |
| Cervantes | español | Reflexión sobre el lenguaje; el texto depende de la edición (ortografía). |
| Sor Juana Inés de la Cruz | español | Mujer y voz no europea del canon hispano. |
| Montaigne | francés | Ensayos sobre las palabras. |
| Emily Dickinson | inglés | Poemas muy breves; varios hablan de las palabras. |
| Oscar Wilde | inglés | Frases cortas, buena para una serie «solo los adjetivos». |
| Machado de Assis | portugués (Brasil) | Portugués de Brasil, un mercado editorial amplio según `Listado-paises/`. |
| Tolstói y Pushkin | ruso | Ruso, con muchas palabras anotadas. |
| Dante | italiano | Italiano. |
| Bashō | japonés | Haiku: cortos y ricos en imágenes de color. Entra en la oleada de escrituras sin espacios. |
| Poetas de la dinastía Tang (Wang Wei, Li Bai, Du Fu) | chino | Versos de cinco o siete caracteres, ricos en color. Misma oleada. |

**Estos candidatos cubren 9 de los 30 idiomas** (`eng`, `spa`, `fra`, `por`, `ita`, `deu`, `rus`, `jpn`, `zho`). Faltan por investigar candidatos para los otros 21: `nld`, `swe`, `dan`, `hin`, `pol`, `ces`, `ron`, `hun`, `ell`, `ukr`, `tur`, `fas`, `ben`, `kor`, `vie`, `tha`, `ind`, `swa`, `ara`, `heb` y `fin`.

Hay que buscar para cada uno: qué obra, qué edición, y si la edición está en dominio público. Se prefieren ediciones consultables en línea (repositorios de textos de dominio público) para que cualquiera pueda comprobar la fuente.

## Idiomas

El alcance son los 30 idiomas de [`Listado-colores/`](../Listado-colores/), con **códigos de 3 letras** (ISO 639-3), como los que ya usa `copyright-laws/` para sus idiomas (`eng`, `spa`, `fra`, `deu`, `por`). `Listado-colores/` usa los de 2 letras, así que esta es la equivalencia:

| 3 letras · 2 letras | Idioma | 3 letras · 2 letras | Idioma | 3 letras · 2 letras | Idioma |
|---|---|---|---|---|---|
| `eng` · `en` | Inglés | `pol` · `pl` | Polaco | `jpn` · `ja` | Japonés |
| `spa` · `es` | Español | `ces` · `cs` | Checo | `kor` · `ko` | Coreano |
| `fra` · `fr` | Francés | `ron` · `ro` | Rumano | `vie` · `vi` | Vietnamita |
| `por` · `pt` | Portugués | `hun` · `hu` | Húngaro | `tha` · `th` | Tailandés |
| `ita` · `it` | Italiano | `ell` · `el` | Griego | `ind` · `id` | Indonesio |
| `deu` · `de` | Alemán | `rus` · `ru` | Ruso | `swa` · `sw` | Suajili |
| `nld` · `nl` | Neerlandés | `ukr` · `uk` | Ucraniano | `ara` · `ar` | Árabe |
| `swe` · `sv` | Sueco | `tur` · `tr` | Turco | `heb` · `he` | Hebreo |
| `dan` · `da` | Danés | `fas` · `fa` | Persa | `fin` · `fi` | Finés |
| `hin` · `hi` | Hindi | `ben` · `bn` | Bengalí | `zho` · `zh` | Chino simplificado |

`ara`, `fas`, `swa` y `zho` son los códigos ISO 639-3 de la macrolengua, que es lo que corresponde a los códigos de 2 letras de `Listado-colores/`. El **chino simplificado** se distingue por su escritura, no por el código: va en el campo `escritura` de la ficha (`Hans`).

### Orden de entrada

Con 30 idiomas, las oleadas ya no dicen *qué* se hace sino *en qué orden* entra cada idioma al corpus y a la publicación. El criterio mezcla tres cosas: las palabras anotadas de cada lengua (de [`Listado-paises/tabla_pos.md`](../Listado-paises/tabla_pos.md); esta tabla da, en las filas que revisé, checo 4,16 M, alemán 3,81 M, ruso ~3,46 M, japonés 2,65 M, portugués 1,55 M, inglés 1,13 M, español 1,02 M, italiano 1,02 M, francés 0,71 M), el tamaño de la audiencia, y si hay un revisor nativo.

| Oleada | Idiomas | Notas |
|---|---|---|
| 1 | `es`, `en`, `de` | Espacios entre palabras, muchos datos anotados, audiencia amplia. |
| 2 | `pt`, `it`, `fr`, `ru`, `cs` | Mismo tipo de escritura. El checo y el ruso aportan morfología rica para las piezas de «el mismo pensamiento en cuatro lenguas». |
| 3 | `ja`, `ko`, `zh`, `ar`, `he` | Otra escritura o escritura sin espacios (segmentación) o de derecha a izquierda. Cada una necesita herramientas y revisión específicas. |

Un idioma sin revisor nativo no se publica, pero puede quedar en borrador. Con etiquetas manuales, cada idioma necesita además a alguien que lo hable para etiquetar y revisar: ese es el cuello de botella real de los 30 idiomas (ver «Riesgos» en `PLAN.md`).

## Etiquetado: categorías

Se usa UPOS (Universal Dependencies): 17 categorías. Los colores de cada una los fija la fase 2 de [`PLAN.md`](PLAN.md); aquí solo el catálogo.

| Código | Categoría | Código | Categoría | Código | Categoría |
|---|---|---|---|---|---|
| `ADJ` | adjetivo | `INTJ` | interjección | `SCONJ` | conjunción subordinante |
| `ADP` | adposición (preposición) | `NOUN` | sustantivo | `SYM` | símbolo |
| `ADV` | adverbio | `NUM` | numeral | `VERB` | verbo |
| `AUX` | auxiliar | `PART` | partícula | `X` | otro |
| `CCONJ` | conjunción coordinante | `PRON` | pronombre | | |
| `DET` | determinante | `PROPN` | nombre propio | | |
| | | `PUNCT` | puntuación | | |

Reglas de etiquetado para las citas:

- **Las etiquetas son manuales** y las revisa una persona que hable el idioma. La ficha de cada cita anota quién etiquetó y quién revisó. Si un día se usa un etiquetador externo o el propio Contrastocolor, la ficha dice cuál y su versión; hasta que el etiquetador exista en este repo, nunca se escribe «etiquetado por Contrastocolor».
- **Los nombres de color** pueden partir de `Listado-colores/exportes/lexico-ud.tsv`, que ya trae su categoría UPOS, pero se revisan en contexto como cualquier otra palabra.
- **Las citas con contracciones, clíticos (*dímelo*) o compuestos largos** (alemán) van en una serie aparte, porque complican la etiqueta; no en la primera oleada.
- **Una cita con una palabra ambigua** (dos categorías posibles) anota cuál se eligió y por qué.

## Ficha de cada cita

Cuando exista el corpus (fase 1 de `PLAN.md`), cada cita tendrá, como mínimo:

| Campo | Contenido |
|---|---|
| `id` | Identificador estable |
| `idioma` | Código de 3 letras del original (ISO 639-3) |
| `escritura` | Solo si hace falta distinguirla, por ejemplo `Hans` en chino simplificado |
| `texto` | El texto exacto, con la ortografía de la edición citada |
| `autor`, `fallecimiento` | Autor y año de su muerte |
| `obra`, `edicion`, `ubicacion` | Obra, edición, página o verso |
| `fuente` | Dónde se comprobó (enlace o referencia) |
| `dominio_publico` | Cómo se comprobó (ver «Derechos de autor») |
| `tema` | Palabras, nombres, traducción, color… |
| `etiquetas` | Categoría de cada palabra, y de dónde salen |
| `estado` | `sin-verificar`, `verificada` o `descartada` (con motivo) |

Una cita en estado `sin-verificar` nunca se publica.
