# Planillas de revisión nativa del chat

Una planilla CSV por idioma con lista de temas de chat (`<código>.csv`), para
que un hablante nativo confirme o corrija lo que hoy censura `ChatLineReviewer`.
Se abren con cualquier hoja de cálculo. Las generó `bin/review-sheets.php` y son
una foto del momento: después de cambiar un diccionario, vuelve a generarlas
(ver abajo) antes de mandarlas a revisar.

## Qué hay en cada planilla

| `seccion` | Una fila por… | Qué confirmar |
|---|---|---|
| `término` | palabra del diccionario (`origen` = `diccionario`) o de los temas de chat (`temas`) | que sea ofensiva hoy, con esa ortografía, esa `categoria`/`riskType` y esa `severidad` |
| `patrón` | frase con forma de `patterns` (`termino` = etiqueta, `origen` = expresión regular) | que la amenaza esté bien escrita y no atrape frases inocentes |
| `excepción` | palabra de `everyday` o `legit`, que el chat deja pasar a propósito | que de verdad sea una palabra cotidiana |
| `frecuente` | palabra **frecuente** del idioma que el chat censuraría sin ser un término de la lista (`origen` = término que la marcó) | si es un falso positivo: la candidata más probable |

Columnas:

- `marcas`: `nameCollision` (también es nombre o apellido: va a revisión, no se
  bloquea), `ambiguous` (palabra cotidiana: el chat la ignora, los nombres no),
  `forms:noun|adj|verb` (se generan plurales, géneros o conjugación).
- `rango`: posición de la palabra entre las 50.000 más usadas del idioma (1 = la
  más usada). Una palabra ofensiva rara vez está muy arriba; si lo está, mira si
  tiene otro sentido cotidiano.
- `decision_chat`: lo que decide hoy el chat con la palabra sola: `approve`,
  `review` (revisión humana) o `reject` (bloqueada).
- `correcto` y `comentario`: para el revisor. En `correcto` pon `sí`, `no`
  (sobra, o es falso positivo), otra severidad (`low`/`medium`/`high`, o
  `baja`/`media`/`alta`) o una marca (`ambiguous`, `nameCollision`; `sin ambiguous` la quita); en
  `comentario`, lo que haga falta (otra ortografía, otra categoría, una palabra
  que falta).

## Cómo se convierte la revisión en cambios

- Término que sobra → se quita de `config/languages/` o `config/chat-topics/`.
- Término que también es palabra cotidiana → `'ambiguous' => true` en el diccionario
  (el chat lo ignora, `validateName()` no).
- Palabra frecuente marcada por plegar tildes («possède» → «possédé», «katıl» →
  «katil») → `everyday` en `config/chat-topics/<código>.php`; si es una letra
  propia del alfabeto que fusiona muchas palabras, una exclusión en
  `AccentFolding::LANGUAGE_EXCLUSIONS`.
- Cada corrección lleva su línea en `tests/fixtures/chat-lines-detection.php`: la que debe
  marcarse y la cotidiana que no.

## Planillas urgentes (`urgente/`)

Las pocas filas que bloquean una decisión, para no pedirle a nadie que revise
700: `urgente/tha.csv` (`บ้า`, sus apelativos y `แขก`) y `urgente/jpn.csv`
(`魔女`, `おし`). Mismas columnas que las completas, más `pregunta` (qué hay que
decidir) y `ejemplos` (frases reales con la decisión actual del chat). Se
aplican igual: `php bin/apply-review.php tha review/urgente/tha.csv`.

`บ้า` tiene un cambio **provisional** a la espera de esa revisión: el chat lo
ignora suelto (casi siempre es una exclamación: «บ้าจริง», «maldita sea») y
sus apelativos (`ไอ้บ้า`, `คนบ้า`, `ยัยบ้า`, `อีบ้า`, `นังบ้า`) van a revisión.
`validateName()` lo sigue marcando. Si el revisor dice que debe volver a
marcarse: `sin ambiguous` en su fila.

## Aplicar una planilla revisada

```bash
php bin/apply-review.php spa revisada.csv           # muestra el plan, no escribe
php bin/apply-review.php spa revisada.csv --apply   # lo escribe en config/
```

| Sección | Respuesta | Cambio |
|---|---|---|
| `término` | `no` | se quita del diccionario o de la lista de temas |
| `término` | `low`/`medium`/`high` | cambia la severidad |
| `término` (del diccionario) | `ambiguous`, `nameCollision` | añade la marca |
| `término` (del diccionario) | `sin ambiguous`, `-nameCollision` | quita la marca |
| `frecuente` | `no` | la palabra va a `everyday` (el chat no la busca) |
| `excepción` | `no` | sale de `everyday` o `legit` |

Cada cambio toca sólo la línea de esa entrada: comentarios y orden se
conservan. Los patrones, los comentarios y las marcas en una lista de temas se
listan para hacerlos a mano. No se aplica nada si hay una respuesta que no se
entiende o si la planilla es más vieja que `config/` (la entrada ya no está o
cambió de severidad): en ese caso se regenera y se vuelve a revisar sólo lo
que cambió. Después: una línea por corrección en
`tests/fixtures/chat-lines-detection.php`, `./vendor/bin/phpunit` y regenerar
la planilla.

## Regenerarlas

```bash
# listas de frecuencia de https://github.com/hermitdave/FrequencyWords
curl -sO https://raw.githubusercontent.com/hermitdave/FrequencyWords/master/content/2018/da/da_50k.txt
php bin/review-sheets.php dan da_50k.txt > review/dan.csv
```

Sin lista de frecuencia (`php bin/review-sheets.php swa > review/swa.csv`) no hay
`rango` ni sección `frecuente`. Se usaron las listas `<iso639-1>_50k.txt` de
2018 (OpenSubtitles), salvo hin, jpn y tgl (`_full`, que tienen menos de 50.000
palabras), yue (la de chino tradicional, `zh_tw`, como aproximación) y swa (no
hay lista). Los rangos y la sección `frecuente` derivan de esas listas, que se
publican bajo [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)
(Hermit Dave, FrequencyWords).
