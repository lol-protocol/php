# Listado de colores con nombre y su significado

Léxico de colores con nombre para el proyecto de etiquetado *part-of-speech*: las palabras de color aparecen como sustantivos, adjetivos y nombres propios, y este listado da la lista de nombres a reconocer. Tiene ocho archivos de datos con alcances distintos (más los exportes para PLN, ver «Exportes»):

| Archivo | Qué contiene | Idiomas | Significados |
|---|---|---|---|
| `colores.csv` | 148 colores CSS, anotados: forma natural en inglés, nombre en 30 idiomas, hex y significados en español | 30 (ver lista abajo) | Psicológico y místico, en español |
| `significados-idiomas.csv` | Los significados de esos 148 colores en los otros 29 idiomas (4.292 filas) | 29 | Psicológico y místico, traducidos |
| `lexico-basico.csv` | 38 términos de color de uso real (blanco, azul claro, granate…): forma base, flexiones, sinónimos y variantes | 30 | No tiene |
| `evidencia.csv` | En qué se apoya cada significado de los 148 colores (hay estudios, tradición, derivado) y con qué fuentes | — | Los mismos |
| `significados-culturales.csv` | 28 matices por ámbito cultural (China, Japón, India…) de nueve colores, cada uno con su fuente | — (texto en español) | Matices culturales |
| `categorias.csv` | 210 filas: las categorías gramaticales (adjetivo, sustantivo, nombre propio, verbo) con que puede aparecer cada palabra de color de una sola palabra, con una frase de ejemplo (ver «Categorías gramaticales») | 2: inglés y español | No tiene |
| `referencias.csv` | Las 35 fuentes que citan `evidencia.csv` y `significados-culturales.csv`, con cuánto se pudo comprobar de cada una | — | — |
| `nombres-completos.csv` | 31.935 nombres de color con su hex, de la lista de `meodai/color-names`, y el color CSS más cercano a cada uno | 1: inglés (con algunos nombres en otros idiomas) | No tiene propios; se pueden heredar del color CSS más cercano (ver «Color CSS más cercano») |

Cada color de los 148 se puede leer en cualquiera de los 30 idiomas (nombre, hex y los dos significados) en `por-idioma/<código>.md`; `colores-por-idioma.md` es el índice de esas páginas. Del resto se generan los exportes para PLN (`exportes/`, con una forma normalizada de cada nombre), dos informes (`ambiguedad.md` y `auditoria-nombres.md`), `categorias.md` (las categorías gramaticales, por idioma) y la versión de los datos (`CHANGELOG.md` y `exportes/manifiesto.json`): ver «Exportes», «Ambigüedad», «Categorías gramaticales», «Auditoría de los nombres completos» y «Versión de los datos y manifiesto».

## Idiomas

Código y nombre de cada idioma. En `colores.csv` el inglés es la columna `nombre_ingles` y los demás son `nombre_<código>`; en `significados-idiomas.csv` el código va en la columna `idioma` (todos menos `es`, cuyos significados están en `colores.csv`).

| Código | Idioma | Código | Idioma | Código | Idioma |
|---|---|---|---|---|---|
| `en` | Inglés | `pl` | Polaco | `ja` | Japonés |
| `es` | Español | `cs` | Checo | `ko` | Coreano |
| `fr` | Francés | `ro` | Rumano | `vi` | Vietnamita |
| `pt` | Portugués | `hu` | Húngaro | `th` | Tailandés |
| `it` | Italiano | `el` | Griego | `id` | Indonesio |
| `de` | Alemán | `ru` | Ruso | `sw` | Suajili |
| `nl` | Neerlandés | `uk` | Ucraniano | `ar` | Árabe |
| `sv` | Sueco | `tr` | Turco | `he` | Hebreo |
| `da` | Danés | `fa` | Persa | `fi` | Finés |
| `hi` | Hindi | `bn` | Bengalí | `zh` | Chino simplificado |

## Alcance

No existe un conjunto cerrado de "todos los colores que tienen nombre": los catálogos comerciales (Pantone, RAL, Crayola) y las listas colaborativas tienen cada uno su propia cifra. Por eso el listado tiene dos capas:

- **Lista completa** (`nombres-completos.csv`): la colección curada más grande que se pudo verificar y que tiene licencia abierta. Tiene 31.935 nombres únicos. La columna `curada` marca con `x` los 4.966 que sus curadores consideran buenos nombres (por ejemplo, `3AM Latte`); los demás son válidos pero menos típicos. Para cada nombre, el generador calcula cuál de los 148 colores CSS es el más cercano (ver «Color CSS más cercano»).
- **Tabla anotada** (`colores.csv`): los 148 colores con nombre de la especificación CSS, con sus nombres y sus significados en 30 idiomas. Es el conjunto estándar, verificado contra la fuente oficial.

La lista completa no está traducida: sus nombres son ingleses, aunque incluye algunos en otros idiomas (pinyin con tonos, hindi transliterado, español, francés…; ver «Auditoría de los nombres completos»). Traducir 31.935 nombres a 30 idiomas sería casi un millón de cadenas, sin fuente que las respalde, y muchos de esos nombres son marcas, nombres propios o términos sin traducción.

Varias palabras de la lista completa son también palabras corrientes en español, como `coral` o `magenta`, y otras son nombres propios o términos técnicos. Para el etiquetado conviene tener en cuenta que la misma palabra puede ser un color o no, según el contexto.

## Formas naturales y alias

Los nombres CSS son identificadores (`lightgoldenrodyellow`), pero en un texto aparecen como «light goldenrod yellow». Para reconocerlos hay dos ayudas:

- **Forma natural en inglés** (`forma_natural_en` en `colores.csv`): el mismo nombre con espacios entre palabras, en minúsculas (`alice blue`, `dark slate gray`). En los otros 29 idiomas la columna `nombre_<código>` ya está en su forma natural, con espacios donde el idioma los usa. La forma con guion que se usa como adjetivo delante del sustantivo (`light-blue shirt`) se obtiene cambiando los espacios por guiones; los exportes la incluyen.
- **Alias:** colores CSS con exactamente el mismo hex. Se calculan a partir de `colores.csv`, no se escriben a mano, y salen en la columna *Alias* de cada página de `por-idioma/`. Hay nueve grupos de dos: `gray`/`grey`, `darkgray`/`darkgrey`, `dimgray`/`dimgrey`, `lightgray`/`lightgrey`, `slategray`/`slategrey`, `darkslategray`/`darkslategrey` y `lightslategray`/`lightslategrey` (dos grafías del mismo nombre, tipo `ortografia`), y `aqua`/`cyan` y `fuchsia`/`magenta` (nombres distintos del mismo color, tipo `mismo_color`). La especificación CSS define estos nueve pares.

## Léxico básico

Los 148 nombres CSS son identificadores técnicos; muchas de sus traducciones (`peachpuff`, `papayawhip`) son calcos que nadie dice. `lexico-basico.csv` recoge, en cambio, los términos que sí se usan para hablar de color, y las formas que toman en una frase, que es lo que un etiquetador necesita reconocer.

- **38 conceptos.** Los 11 términos básicos de color (`white`, `black`, `red`, `green`, `yellow`, `blue`, `brown`, `purple`, `pink`, `orange`, `gray`), cinco con claro y oscuro (`lightblue`, `darkblue`, `lightgreen`, `darkgreen`, `skyblue`) y 22 más frecuentes (`turquoise`, `beige`, `gold`, `maroon`, `navy`, `lilac`, `burgundy`, `cream`, `scarlet`, `amber`…). El concepto lleva el nombre del color CSS cuando existe; si no, el de un nombre de `nombres-completos.csv`, y de ahí sale su hex de referencia.
- **Cobertura.** Los 11 básicos están en los 30 idiomas (lo exige el generador). Los demás conceptos no: donde no hay un término claro, no hay fila. El suajili tiene 18 conceptos, el bengalí 33 y el griego 35; el resto, entre 36 y 38. Hay 2.400 filas: 1.095 lemas, 1.004 flexiones, 260 sinónimos y 41 variantes.
- **Columnas.** `concepto`, `idioma`, `forma`, `tipo` y `rasgos`.
- **Tipos.** `lema` (la forma de diccionario; uno por concepto e idioma), `flexion` (otra forma del mismo término), `sinonimo` y `variante` (misma palabra con otra grafía o uso regional).
- **Rasgos de las flexiones**, con los nombres de Universal Dependencies: `Gender` (`Masc`, `Fem`, `Neut`), `Number` (`Sing`, `Plur`) y `Animacy=Anim` para el plural animado del polaco y el checo; en alemán y neerlandés, `Terminacion` (`-e`, `-en`, `-er`, `-es`, `-em`). Los sinónimos y variantes pueden llevar una etiqueta `clave=valor` con `region`, `ortografia`, `registro`, `uso` o `forma` (por ejemplo `castanho` con `region=PT`, `зеленый` con `ortografia=sin_dieresis`, o el prefijo tailandés `สี` con `forma=con_prefijo_si`).
- **Qué no incluye.** En los idiomas con casos (alemán, polaco, checo, ruso, ucraniano, griego, finés, húngaro, turco) solo hay nominativo; en alemán, las cinco terminaciones atributivas. Los términos de varias palabras (`azul claro`, `bleu clair`) y los que no cambian de forma solo traen el lema. En japonés, las formas con い (`白い`) y con 色 van como sinónimos del lema. Algunos conceptos comparten forma en un idioma (en francés, `violet` sirve para `purple` y `violet`; `bordeaux` para `maroon` y `burgundy`): es una ambigüedad real, no un error.
- **Hex de referencia.** Es un prototipo del término, no su extensión: «blue» abarca muchos azules. Para los conceptos con color CSS es el hex CSS; para `lilac`, `burgundy`, `cream`, `scarlet` y `amber`, el de `nombres-completos.csv`. Ojo: la lista completa usa otros hex para algunos nombres que también existen en CSS (por ejemplo `turquoise`).
- **Las formas pueden no coincidir con `colores.csv`.** Allí son traducciones del nombre CSS; aquí, el término de uso real (en `colores.csv` el alemán va con mayúscula inicial y el japonés a menudo en katakana; el léxico usa `weiß`).
- **Calidad.** Lo escribió un asistente de IA y no lo ha revisado ningún hablante nativo. Las formas regulares (por ejemplo, los adjetivos rusos, polacos o checos) siguen reglas conocidas; las irregulares, los sinónimos y los términos poco frecuentes son lo más expuesto a error, sobre todo en suajili, bengalí, persa, tailandés y hebreo. Cada página de `por-idioma/` muestra el léxico de su idioma, para facilitar esa revisión.

## Evidencia de los significados

Los significados de `colores.csv` están escritos para este listado; `evidencia.csv` dice, color por color, en qué se apoyan. Hay dos columnas de base, cada una con sus referencias (`referencias_psicologico` y `referencias_mistico`, ids de `referencias.csv`):

- **Base psicológica.** `hay_estudios` (13 colores: `black`, `white`, `gray`, `grey`, `brown`, `red`, `orange`, `yellow`, `green`, `turquoise`, `blue`, `purple` y `pink`) significa que esos colores están entre los 12 términos de color de estudios transnacionales sobre color y emoción (Jonauskaite et al., 2020: 4.598 personas de 30 países), y algunos en otros trabajos (Adams y Osgood, 1973; Hupka et al., 1997). **No** significa que el texto resuma los resultados de esos estudios. `derivado` (los otros 135) quiere decir que lo extrapoló el autor a partir del color básico más parecido y de su claridad y saturación; no tiene fuente específica.
- **Base mística.** `tradicion` (13 colores) cita una tradición o un referente histórico concreto: la alquimia (`black`, `white`, `gold`, `silver`), el amuleto contra el mal de ojo (`blue`, `coral`), la piedra de los marineros o del viajero (`aquamarine`, `turquoise`), el melocotón de la inmortalidad china (`peachpuff`), la concha de Venus (`seashell`), el olivo de la paz (`olive`), el lino de los templos (`linen`) y el cardo protector (`thistle`). `tradicion_moderna` (7: `red`, `orange`, `yellow`, `green`, `indigo`, `violet`, `purple`) es el esquema de siete chakras en colores del arcoíris: aunque los textos dicen «en la tradición de los chakras», ese esquema es occidental y moderno (Blavatsky en la década de 1880, Leadbeater en 1927 y, según Leland, la combinación de colores y cualidades no aparece junta en letra impresa hasta 1977), no el de los textos tántricos clásicos. `derivado` (los otros 128) es simbolismo general extrapolado.

Límites: de los 13 colores con `tradicion`, cuatro (`linen`, `olive`, `seashell`, `thistle`) no tienen fuente, y para la alquimia, Kunz (aguamarina y turquesa) y Elworthy (coral) solo se comprobó que la obra existe y trata el tema, no el pasaje concreto. Que una obra figure en `referencias.csv` indica dónde buscar, no que respalde cada frase. El generador exige que los 13 colores con estudios sean exactamente esos, que cada referencia exista y que ninguna quede sin usar.

## Matices culturales

Los significados son los mismos en los 30 idiomas porque se tradujeron. `significados-culturales.csv` recoge, aparte, lo que cambia según la cultura, para los colores y ámbitos donde se pudo respaldar con una fuente: 28 matices de nueve colores (`white`, `red`, `yellow`, `orange`, `green`, `blue`, `black`, `purple`, `pink`) en 14 ámbitos.

- **Columnas.** `color` (nombre CSS), `ambito`, `matiz` (texto en español), `solidez` y `referencias` (al menos una).
- **Ámbito.** Una cultura o región (`china`, `japon`, `india`, `tailandia`, `occidente`…), no un idioma. El generador decide en qué páginas de `por-idioma/` sale cada ámbito con una relación aproximada entre idioma y ámbito (`AMBITOS` en `generar-tabla.py`; por ejemplo, `zh` con `china` y `hi` y `bn` con `india`). Los idiomas sin ámbito asociado (ucraniano, checo, rumano, húngaro, vietnamita y suajili) lo dicen en su página.
- **Solidez.** `alta` (2): lo respalda una obra de historia o ciencias sociales cuyo resumen lo dice. `media` (24): lo recogen páginas web divulgativas, enciclopédicas o una fuente secundaria. `baja` (2): fuentes devocionales o divulgativas sin base escritural o académica que se haya podido comprobar. No es una valoración de la fuente, sino de cuánto se pudo comprobar.
- **Ejemplos.** El blanco como luto en China y Corea y como pureza en Japón y Occidente; el rojo como color de las novias en India y de la suerte en China; el negro como luto nacional en Tailandia en 2016; los estudios que asocian la envidia con colores distintos en Alemania, Polonia, Rusia y Estados Unidos.
- **Cobertura parcial.** Solo entra lo que se pudo respaldar. Hay culturas, colores y matices que faltan, y lo que figura puede variar por región, época y generación. Un matiz que no está aquí no significa que no exista.

## Color CSS más cercano

Los 148 colores CSS tienen significados; los 31.935 nombres de la lista completa no. Para poder darles una pista sin inventar nada, `nombres-completos.csv` trae tres columnas calculadas:

- `css_cercano`: el color CSS cuyo hex está más cerca del hex del nombre.
- `delta_e`: esa distancia, con dos decimales.
- `cercania`: una banda según la distancia: `exacto` (mismo hex), `alta` (hasta 3), `media` (hasta 8) y `baja` (más de 8).

Cómo se calcula (`colorimetria.py`, solo biblioteca estándar):

- El hex (sRGB) se pasa a CIELAB con el iluminante D65 y la distancia es CIEDE2000 (ΔE00), que se acerca más que la distancia euclídea a cómo se perciben las diferencias de color.
- La implementación sigue las notas de Sharma, Wu y Dalal (2005), *The CIEDE2000 color-difference formula: Implementation notes, supplementary test data, and mathematical observations* (Color Research & Application 30(1), 21-30). El generador comprueba al empezar 16 pares de su tabla de datos de prueba y se detiene si alguno no coincide.
- Si dos colores CSS están igual de cerca (por ejemplo `gray` y `grey`, que comparten hex, o `aqua` y `cyan`), gana el primero por orden alfabético. Por eso los nueve segundos de cada par (`cyan`, `magenta` y las siete variantes `…grey`) no aparecen nunca como color más cercano.
- Calcularlo para toda la lista tarda entre 13 y 20 segundos según la máquina; `--comprobar` lo recalcula entero.

Reparto en la lista completa: 136 exactos (0,4 %), 3.814 de cercanía alta (11,9 %), 17.103 media (53,6 %) y 10.882 baja (34,1 %).

Límites que conviene tener presentes:

- Los umbrales de las bandas son de este proyecto, no un estándar: se eligieron a la vista de ese reparto.
- Solo hay 148 colores CSS y están repartidos de forma desigual en el espacio de color: muchos nombres (sobre todo los apagados y los grises) terminan en `dimgray`, `tan`, `gray` o `darkslategray`.
- Heredar el significado solo tiene sentido con cercanía `exacto` o `alta`. En `media` y `baja` el color CSS puede ser de otra familia: `Burgundy` (`#900020`) queda en `brown` con distancia 7,15 y `Lilac` (`#CEA2FD`) en `plum` con 8,62. Quien use esto debe filtrar por `cercania` y tratar el resultado como derivado.

## Exportes

Para usar los datos desde un programa sin leer ocho CSV, el generador produce dos archivos en `exportes/` y puede construir una base SQLite. Todo sale de los CSV; no se edita a mano.

- **`exportes/colores.jsonl`**: una línea JSON por cada color CSS (148), con todo lo que se sabe de él: `id` (nombre CSS), `hex`, `rgb`, `forma_natural_en`, `forma_con_guion_en` (la que se usa como adjetivo delante del sustantivo), `alias` (`id` y `tipo`), `nombres` y `significados` en los 30 idiomas (en `en`, el nombre es la forma natural), `evidencia` (base y referencias de cada significado), `culturales` (los matices del color), `lexico` (las formas del léxico básico de los conceptos que coinciden con el color, por idioma) y `categorias` (las categorías gramaticales de las palabras de ese color, por idioma, cada una con `forma`, `categoria`, `ocurrencia` y `ejemplo`; `{}` si no hay).
- **`exportes/gazetteer.tsv`**: lista plana de formas de superficie, para buscar una palabra y saber a qué color o concepto remite. 7.080 filas separadas por tabuladores, ordenadas por forma sin distinguir mayúsculas. Columnas: `forma`, `idioma`, `fuente` (`css` o `lexico`), `tipo`, `concepto`, `hex`, `rasgos`, `forma_normalizada` (ver «Forma normalizada») y `categorias` (las categorías gramaticales de la forma separadas por comas, `ADJ,NOUN,VERB`; vacía si no hay; ver «Categorías gramaticales»). Las dos últimas van al final para no mover las demás columnas. Con `fuente=css`, `tipo` es `identificador` (`aliceblue`), `forma_natural` (`alice blue`), `forma_con_guion` (`alice-blue`) o `nombre` (la traducción en otro idioma). Con `fuente=lexico`, es `lema`, `flexion`, `sinonimo` o `variante` y `rasgos` trae lo que dice `lexico-basico.csv`. Una misma forma puede salir varias veces (por ejemplo, `abu-abu` en indonesio para `gray`, `grey` y como lema). La lista completa de 31.935 nombres no se repite aquí: está en `nombres-completos.csv`.
- **Base SQLite** (no se guarda en el repositorio): `python3 generar-tabla.py --sqlite ruta.db`. Tablas: `colores`, `nombres`, `significados`, `alias`, `evidencia`, `culturales`, `referencias`, `conceptos`, `lexico`, `categorias` y `nombres_completos`, con índices por nombre, forma, forma normalizada y hex. `nombres` y `lexico` tienen una columna normalizada (`nombre_normalizado`, `forma_normalizada`). Ejemplo: `SELECT n.nombre, s.psicologico FROM nombres n JOIN significados s USING (color_id, idioma) WHERE n.color_id = 'crimson' AND n.idioma = 'ja';`.

`--comprobar` compara los archivos de `exportes/` (los dos exportes y el manifiesto) con lo que generan los CSV y construye la base en memoria para verificar que cada tabla tenga tantas filas como su CSV y que un cruce de tablas funcione. Las funciones están en `exportes.py`. Los tres archivos de `exportes/` pesan en total unos 1,6 MB.

### Forma normalizada

Para buscar una palabra en texto escrito sin cuidado (mayúsculas, sin tildes, con guiones o espacios distintos), el gazetteer y la base SQLite traen cada forma también normalizada (`normalizacion.py`, solo biblioteca estándar):

- NFKC y minúsculas (`casefold`: «ß» pasa a «ss» y la sigma final del griego a «σ»).
- Los apóstrofos (`’`, `ʼ`, la geresh del hebreo…) pasan a `'` y se conservan: en ucraniano son parte de la palabra.
- Espacios, guiones, rayas, el signo menos y el guion bajo se unifican en un solo espacio: `alice-blue`, `alice blue` y `Alice   Blue` dan `alice blue`. `aliceblue` (sin espacio) es otra forma y tiene su propia fila.
- Se quitan el guion blando, el espacio de ancho cero y el BOM. Se conservan el ZWNJ y el ZWJ, que en persa son parte de la ortografía.
- **Solo en escritura latina y griega** se quitan las marcas combinantes (`marrón` → `marron`, `Grün` → `grun`, `κόκκινο` → `κοκκινο`) y se pliegan a ASCII las letras que no se descomponen: `ł`, `đ`, `ı`, `ø`, `æ`, `œ` (`đỏ` → `do`, `grøn` → `gron`). En las demás escrituras no se quita nada: allí las marcas son parte de la letra (la dakuten del japonés, la «й» del ruso y del ucraniano, las vocales del tailandés, el hindi y el bengalí), y quitarlas daría otra palabra.

Es una aproximación para casar texto, no una equivalencia lingüística: dos palabras distintas pueden acabar iguales (en turco, `ı` e `i`; en danés, `ø` y `o`) y no se intenta deshacer. Los plegados siguen la convención habitual de pasar a ASCII; no es una norma de ningún idioma. No hay separación de palabras para las escrituras que no usan espacios (chino, japonés, tailandés).

## Ambigüedad

`ambiguedad.md` (generado) lista las formas del gazetteer que designan más de un color, para saber qué formas no bastan por sí solas para decidir de qué color se habla. Una forma es ambigua si remite a más de un hex; los alias CSS (`gray` y `grey`) comparten hex y no cuentan. Cuatro listas:

1. **Dentro de un idioma:** una misma forma con varios colores (en francés, `violet` es `purple` y `violet`; en español, `celeste` es `skyblue` y `lightblue`).
2. **Entre idiomas:** la misma forma con colores distintos en cada uno (`lila` es `lilac` en español, neerlandés, finés, rumano y turco, pero `purple` en alemán, sueco y húngaro; `青色` es cian en chino y azul en japonés).
3. **Dentro de un idioma, solo al normalizar:** formas distintas que coinciden al pasarlas por la forma normalizada y que no estaban ya en la lista 1.
4. **Entre idiomas, solo al normalizar:** lo mismo entre idiomas, sin repetir la lista 2 (por ejemplo, `grau` en alemán, gris, y `grâu` en rumano, trigo).

Cada caso trae la distancia máxima CIEDE2000 (ΔE) entre sus colores y una banda: *casi iguales* (hasta 3), *parecidos* (hasta 8) y *distintos* (más de 8). Son los umbrales de cercanía del proyecto, no un estándar. El resumen de `ambiguedad.md` tiene las cifras al día.

Límites que conviene tener presentes:

- Solo cubre las formas del gazetteer. No detecta palabras que, además de un color, son otra cosa (una flor, una fruta) ni palabras que no están en el listado: para eso hace falta un corpus. Para inglés y español, `categorias.md` dice con qué categorías gramaticales puede aparecer cada palabra (ver «Categorías gramaticales»), pero no cuál usa un texto concreto.
- Parte de las ambigüedades viene de la traducción, que es una propuesta sin revisión de hablantes nativos. Una forma que remite a colores muy distintos en un idioma (en francés, `violet` a `purple` y `violet`, a 39,9 de distancia) puede ser un idioma que no distingue los dos colores o una traducción que conviene revisar; el informe no lo decide.
- Las formas flexionadas cuentan una por una: si el lema de un idioma sirve para dos colores, cada flexión sale como un caso.

## Categorías gramaticales

Muchas palabras de color son también sustantivos, nombres propios o verbos (`coral`, `lime`, `tan`, `peru`, `violet`, `lima`). Para el etiquetado *part-of-speech*, `categorias.csv` dice con qué categoría puede aparecer cada palabra de color de una sola palabra y da una frase de ejemplo por categoría. `categorias.md` (generado) lo muestra por idioma, con un resumen; el exporte JSONL y el gazetteer lo llevan al lado de cada color o forma, y la base SQLite tiene la tabla `categorias`.

- **Columnas.** `idioma`, `concepto` (el id CSS o el concepto del léxico), `forma` (la palabra), `categoria`, `ocurrencia` (la palabra tal como sale en la frase: `blacked`, `Peru`, `khakis`) y `ejemplo`.
- **Categorías.** Las de [Universal Dependencies](https://universaldependencies.org/u/pos/): `ADJ` adjetivo, `NOUN` sustantivo, `PROPN` nombre propio y `VERB` verbo. Toda palabra tiene `ADJ` (es lo que la hace palabra de color); las demás son lecturas extra.
- **Alcance.** Dos idiomas: inglés (los 56 nombres CSS de una sola palabra, más `lilac`, `burgundy`, `cream`, `scarlet` y `amber` del léxico básico) y español (los 32 lemas de una sola palabra del léxico básico). 93 palabras y 210 filas. Las palabras compuestas (`azul marino`, `light blue`) no entran. Un idioma que se agregue debe cubrir todas sus palabras de una sola palabra: el generador falla si falta alguna.
- **Forma.** En inglés es la forma natural del color CSS (`aqua`, no `aliceblue`) o el lema del léxico; en español, el lema del léxico. No se anotan las flexiones; en el gazetteer solo la forma base trae categorías.

Límites:

- Son lecturas **posibles**, no cuántas veces ocurre cada una, y cada frase es un ejemplo. No se distingue el sentido: `coral` sustantivo es el animal y también el color.
- Hay casos discutibles. Un sustantivo que modifica a otro (`a linen shirt`) puede etiquetarse `NOUN` o `ADJ` según el criterio de cada corpus; los ejemplos de `ADJ` en inglés usan la palabra como color en una posición clara (`painted the walls linen`).
- Lo escribió un asistente de IA y ningún hablante nativo lo ha revisado: úsalo como punto de partida, no como corpus anotado.

## Auditoría de los nombres completos

Los 31.935 nombres de `nombres-completos.csv` no se han verificado uno a uno contra otra fuente. `auditoria-nombres.md` (generado) no lo arregla, pero revisa automáticamente lo que sí se puede comprobar sin una fuente, y **marca casos sospechosos sin corregir nada**: el generador sigue aceptando el archivo.

- **Ocho comprobaciones de defectos**, en las que lo normal es que no haya ningún caso: caracteres de control, caracteres invisibles o de formato, espacios que no son el espacio normal, espacios dobles o en los extremos, forma Unicode distinta de NFC, posible mojibake (UTF-8 leído como otra codificación), nombres que empiezan por minúscula y apóstrofo ASCII junto al tipográfico. Hoy ninguna encuentra nada.
- **Seis que describen la lista**: nombres en mayúsculas, que empiezan por un dígito, de cuatro palabras o más (364), con letras que no son ASCII (295), con apóstrofo tipográfico (441) y con guion (220). Cada una trae cuántos nombres son «curados» (los de `curada` = `x`) y cinco ejemplos.
- **Duplicados al normalizar**: nombres distintos que coinciden tras la forma normalizada (hoy `Rose`/`Rosé` y `Sable`/`Sablé`).
- **Hex repetidos**: hoy no hay ninguno: los 31.935 nombres tienen un hex distinto.
- **Signos que no son letras**, con cuántos nombres los llevan, y los cinco nombres más largos.

Límites que conviene tener presentes:

- No compara con otra fuente ni distingue el idioma de cada nombre. La lista no es solo inglés: hay nombres en pinyin con tonos (`Bái Sè White`), en hindi transliterado (`Gulābī Pink`), en español o en francés, y un etiquetador debe esperarlos.
- Un hallazgo es un sitio donde mirar, no un error. «Cuatro palabras o más» incluye nombres que son frases (`A Brand New Day`), que pueden ser válidos como nombre de color pero no se comportan como un adjetivo en un texto.
- Las cifras de este apartado son las de la fecha del cambio; el propio informe trae las actuales.

## Versión de los datos y manifiesto

Para usar los datos desde otro programa con una versión fija, `exportes/manifiesto.json` (generado) trae:

- `version`: la de la primera entrada de `CHANGELOG.md` (versionado semántico de los **datos**, no del generador: mayor si se quita o renombra una columna, menor si se añade algo, parche si se corrige un dato).
- `idiomas`: los 30 códigos, en orden.
- `entradas` y `exportes`: de cada CSV de datos y de cada exporte, `archivo`, `filas` (sin la cabecera) y `sha256` (más `columnas` en el TSV).

Para comprobar que tienes lo mismo que describe el manifiesto, desde esta carpeta:

```python
import hashlib, json
from pathlib import Path
m = json.loads(Path("exportes/manifiesto.json").read_text(encoding="utf-8"))
for e in m["entradas"] + m["exportes"]:
    suma = hashlib.sha256(Path(e["archivo"]).read_text(encoding="utf-8").encode("utf-8")).hexdigest()
    print(e["archivo"], "igual" if suma == e["sha256"] else "DIFERENTE")
```

Las sumas se calculan sobre el texto con los finales de línea unificados, así que un checkout con CRLF (Windows) da las mismas; `sha256sum` sobre un archivo con CRLF daría otra.

Quién sube la versión: el generador no puede saber si un cambio de datos merece una versión nueva. Quien cambia los datos apunta el cambio en `CHANGELOG.md` (con la versión que corresponda) y regenera. `--comprobar` falla si el manifiesto no coincide con los archivos, así que un cambio de datos nunca pasa sin regenerarlo, y el manifiesto cambia con él (alguna suma), pero **nada obliga a subir la versión**: si se olvida, la versión de dos conjuntos de datos distintos coincide y solo se ve por las sumas. `generar-tabla.py` comprueba el formato de `CHANGELOG.md` (encabezados `## X.Y.Z - AAAA-MM-DD`, versiones que bajan de arriba abajo, fechas que no suben, texto en cada entrada).

## Fuentes y verificación

- **Nombres en inglés y hex de la tabla anotada:** especificación *CSS Color Module Level 4*, sección de colores con nombre (código fuente en `w3c/csswg-drafts`). Los 148 nombres y sus valores hex coinciden con esa fuente y con una tabla JSON de referencia (`bahamas10/css-color-names`). `rebeccapurple` aparece en la especificación como adición de CSS Color Level 4.
- **Lista completa:** `meodai/color-names` (archivo `src/colornames.csv`), con licencia MIT, incluida en `LICENCIA-meodai-color-names.txt`. Los 31.935 hex son válidos y los nombres son únicos sin distinguir mayúsculas. No se ha verificado nombre a nombre contra otra fuente.
- **Traducciones de los nombres a los 29 idiomas que no son inglés:** propuestas, no revisadas por hablantes nativos. Están en `colores.csv` y se ven en `por-idioma/`. La calidad puede variar mucho de un idioma a otro, y las de los menos comunes (por ejemplo, bengalí, persa o suajili) son las menos fiables. Antes de usarlas como referencia, conviene una revisión de un hablante nativo por idioma.
- **Traducciones de los significados a los otros 29 idiomas:** las hizo un asistente de IA a partir del texto en español de `colores.csv`, y nadie con dominio nativo de cada idioma las ha revisado. Valen lo mismo que la nota anterior: son un punto de partida, y las de idiomas menos comunes o de escritura no latina son las menos fiables. Los términos de otras tradiciones (los chakras, el *nigredo* y el *albedo* de la alquimia) están transliterados a la escritura de cada idioma.
- **Nombres en español:** propuestas de este listado, no una norma oficial de la RAE. Cinco de ellos son idénticos al inglés (`aqua`, `chartreuse`, `chocolate`, `coral`, `magenta`).
- **Significados:** escritos para este listado, en español. Lo *psicológico* son asociaciones culturales frecuentes en diseño y marketing; la evidencia científica sobre el efecto real de un color en una persona es limitada y varía según la cultura. Lo *místico* resume simbolismos de tradiciones como la alquimia, el esquema occidental moderno de los chakras, el simbolismo cristiano, el mediterráneo popular o la cultura china. Ambos describen convenciones y creencias, no propiedades comprobables. Los significados son los mismos en los 30 idiomas: se tradujeron, no se adaptaron a cada cultura, así que el simbolismo de un color en un país concreto puede diferir de lo que dice su página; algunos matices culturales con fuente están aparte, en `significados-culturales.csv`. En qué se apoya cada significado está en `evidencia.csv`.

## Archivos

- `colores.csv`: tabla anotada. Columnas: `nombre_ingles`, `forma_natural_en`, `nombre_es`, `nombre_fr`, `nombre_pt`, `nombre_it`, `nombre_de`, … hasta `nombre_sw` (29 columnas de idioma en total, además del inglés), `hex`, `significado_psicologico`, `significado_mistico`.
- `lexico-basico.csv`: léxico básico. Columnas: `concepto`, `idioma`, `forma`, `tipo`, `rasgos`. Filas ordenadas por concepto (en el orden de `CONCEPTOS` de `generar-tabla.py`), idioma y tipo.
- `evidencia.csv`: una fila por color. Columnas: `nombre_ingles`, `base_psicologico`, `referencias_psicologico`, `base_mistico`, `referencias_mistico`. Las referencias son ids de `referencias.csv` separados por `;`.
- `significados-culturales.csv`: matices culturales. Columnas: `color`, `ambito`, `matiz`, `solidez`, `referencias`.
- `categorias.csv`: categorías gramaticales de las palabras de color (ver «Categorías gramaticales»). Columnas: `idioma`, `concepto`, `forma`, `categoria`, `ocurrencia`, `ejemplo`. Filas ordenadas por idioma, concepto (los colores CSS primero, luego los del léxico) y categoría (`ADJ`, `NOUN`, `PROPN`, `VERB`).
- `categorias.md`: las mismas categorías por idioma, con un resumen. Generado.
- `categorias.py`: valida `categorias.csv` y genera ese informe. Lo importa `generar-tabla.py`.
- `referencias.csv`: fuentes. Columnas: `id`, `tipo` (`articulo`, `libro`, `web`), `cita`, `url`, `verificacion` (`resumen`, `pagina`, `existencia`). Con él se genera `referencias.md`, donde cada fuente tiene un ancla.
- `significados-idiomas.csv`: los significados en los otros 29 idiomas. Columnas: `idioma`, `nombre_ingles`, `significado_psicologico`, `significado_mistico`. Una fila por color e idioma, ordenadas por `nombre_ingles` y, dentro de cada color, por el orden de idiomas de `generar-tabla.py`. Los pares que en `colores.csv` tienen el mismo texto en español (como `gray` y `grey`) tienen también el mismo texto traducido.
- `por-idioma/<código>.md`: una página por idioma con el nombre, el hex, los alias, los dos significados y su base para los 148 colores, los matices culturales asociados al idioma y la tabla de su léxico básico. Generado.
- `colores-por-idioma.md`: índice de las páginas por idioma. Generado.
- `nombres-completos.csv`: lista completa. Columnas de la fuente: `nombre_ingles`, `hex`, `curada`. Columnas calculadas por el generador: `css_cercano`, `delta_e`, `cercania`.
- `colorimetria.py`: sRGB a CIELAB y CIEDE2000, con los vectores de prueba de Sharma et al. Lo importa `generar-tabla.py`.
- `exportes/colores.jsonl` y `exportes/gazetteer.tsv`: exportes para PLN. Generados.
- `exportes.py`: genera los exportes y la base SQLite. Lo importa `generar-tabla.py`.
- `normalizacion.py`: la forma normalizada de un nombre (ver «Forma normalizada»). Lo importan `exportes.py` y `ambiguedad.py`.
- `ambiguedad.md`: informe de las formas que designan más de un color (ver «Ambigüedad»). Generado.
- `ambiguedad.py`: calcula ese informe. Lo importa `generar-tabla.py`.
- `auditoria-nombres.md`: informe de los nombres sospechosos de `nombres-completos.csv` (ver «Auditoría de los nombres completos»). Generado.
- `auditoria.py`: calcula ese informe. Lo importa `generar-tabla.py`.
- `CHANGELOG.md`: registro de cambios de los datos, con su versión (ver «Versión de los datos y manifiesto»). Se edita a mano.
- `exportes/manifiesto.json`: versión, filas y sumas SHA-256 de los CSV y de los exportes. Generado.
- `manifiesto.py`: valida `CHANGELOG.md` y arma el manifiesto. Lo importa `generar-tabla.py`.
- `generar-tabla.py`: valida todos los CSV y genera la tabla de este README, `colores-por-idioma.md`, `por-idioma/`, `referencias.md`, `categorias.md` y `exportes/`.
- `pruebas.py`: pruebas del generador (`unittest`, solo biblioteca estándar). Ver «Pruebas» en «Uso».
- `LICENCIA-meodai-color-names.txt`: licencia MIT de la fuente de la lista completa.
- `README.md`: este documento. La tabla va entre dos líneas que son marcadores HTML de inicio y fin (`tabla:inicio` y `tabla:fin`); no la edites a mano.

## Uso

`README.md` (esta tabla), `colores-por-idioma.md`, `por-idioma/*.md`, `referencias.md` y lo demás que no sea un CSV de datos es salida generada — no se edita a mano. Se regenera con `python3 generar-tabla.py` (`--comprobar` solo valida que esté al día).

Requiere Python 3.10 o posterior y no usa dependencias externas. Se probó con Python 3.10, 3.11, 3.12, 3.13 y 3.14 en Linux x86_64: el generador (con las 31.935 distancias, que salen idénticas al archivo guardado) y las pruebas dan el mismo resultado en todas. La CI corre además una matriz con Python 3.10 y 3.14 en Ubuntu 24.04 y 3.13 en macOS 15 (arm64). No se ha probado con versiones anteriores a la 3.10 ni en Windows.

```bash
cd Listado-colores
python3 generar-tabla.py                # tras editar un CSV: valida y regenera todo lo generado (tarda entre 13 y 20 s)
python3 generar-tabla.py --comprobar    # solo comprueba: datos válidos, archivos generados al día y base SQLite coherente
python3 generar-tabla.py --sqlite ruta.db  # construye una base SQLite con los datos
```

Pruebas del generador:

```bash
cd Listado-colores
python3 -m unittest -v pruebas          # entre 8 y 16 s; no escribe en la carpeta del proyecto
```

`pruebas.py` rompe a propósito, una por una, las reglas de los validadores sobre una copia de los CSV reales (fila borrada, significado sin traducir, forma con letras latinas en un idioma de otra escritura, referencia inexistente o sin usar, orden roto, hex inválido…) y comprueba que cada una da su error. También ejecuta `main()` de punta a punta en una carpeta temporal con `nombres-completos.csv` reducido (para no recalcular las 31.935 distancias): comprueba que un archivo generado editado a mano, borrado o sobrante hace fallar `--comprobar`, que la base SQLite se construye y que todos los archivos generados salen idénticos a los del repositorio salvo el propio `nombres-completos.csv`. Además comprueba la distancia de color con los 16 vectores de Sharma et al. y con propiedades (simetría, negro y blanco a 100). La CI las corre junto a `--comprobar`, que sigue siendo la comprobación de los archivos reales, y repite las dos cosas en la matriz de Python y de sistema de la sección «Uso».

Reglas para editar los CSV (el generador las comprueba):

- Todos los CSV: cada fila con tantos campos como su cabecera (un texto con coma va entre comillas). Una fila con campos de más o de menos hace salir al generador con un error, en lugar de aceptarla en silencio o romper con un traceback.
- Si cambias datos, apunta el cambio en `CHANGELOG.md` (con la versión que corresponda) y regenera con `python3 generar-tabla.py`: el manifiesto cambia con cualquier dato.
- `colores.csv`: `nombre_ingles` en minúsculas, sin espacios y único; `forma_natural_en` igual que `nombre_ingles` pero con espacios entre palabras; `hex` en `#RRGGBB` y en mayúsculas; ningún campo vacío; ningún campo con `|` ni saltos de línea; filas ordenadas por `nombre_ingles`.
- `lexico-basico.csv`: cada fila con concepto, idioma y tipo de la lista; un solo lema por concepto e idioma; los 11 conceptos básicos con lema en los 30 idiomas; formas con lema; ninguna forma vacía, con `|`, comillas o saltos de línea; sin letras latinas en los idiomas de otra escritura (el, ru, uk, ar, he, fa, hi, bn, zh, ja, ko, th); rasgos válidos según el tipo; sin filas repetidas; filas ordenadas.
- `evidencia.csv`: una fila por color de `colores.csv`, en su orden; bases válidas; referencias ordenadas y existentes; los colores con estudios, exactamente los 13 indicados; los significados derivados no llevan referencias; la tradición moderna cita `leland2016`.
- `significados-culturales.csv`: color de `colores.csv` y ámbito conocido; matiz sin `|` ni saltos de línea; solidez `alta`, `media` o `baja`; al menos una referencia existente; ordenado por color y ámbito.
- `categorias.csv`: idioma y concepto conocidos; `forma` igual a la del color CSS (inglés) o a la del lema del léxico; una sola palabra; `categoria` una de `ADJ`, `NOUN`, `PROPN`, `VERB`; `ocurrencia` igual a la forma con a lo sumo cuatro letras más (`PROPN` con mayúscula inicial); `ejemplo` sin `|` ni saltos, terminado en `.`, `!` o `?`, y con la `ocurrencia` como palabra entera; una fila por idioma, concepto y categoría; todas las palabras de una sola palabra de cada idioma presente; ordenadas.
- `referencias.csv`: id único y ordenado; tipo y verificación conocidos; una página web lleva url `https://`; ninguna referencia sin usar.
- `significados-idiomas.csv`: un par de significados por cada color de `colores.csv` y cada idioma (todos menos `es`), sin repetidos; ningún campo vacío, con `|` ni con saltos de línea; ningún significado igual al texto en español (sin traducir). Si cambias un significado en `colores.csv`, actualiza también sus 29 traducciones.
- `nombres-completos.csv`: nombre único sin distinguir mayúsculas; `hex` en `#RRGGBB`; `curada` vacía o `x`; filas ordenadas por nombre sin distinguir mayúsculas; ningún nombre con `|`, comas, comillas ni saltos de línea. Las tres primeras columnas se generaron una vez desde la fuente; `css_cercano`, `delta_e` y `cercania` las reescribe el generador y `--comprobar` falla si no coinciden con lo que calcula.

## Tabla

<!-- tabla:inicio -->
| Inglés | Español | Hex | Significado psicológico | Significado místico | Base psicológica | Base mística |
|---|---|---|---|---|---|---|
| `aliceblue` | azul alicia | `#F0F8FF` | Calma, frescura y limpieza; sensación de ligereza y amplitud. | Pureza celestial y serenidad; asociado al cielo despejado y a la comunicación clara. | derivado | derivado |
| `antiquewhite` | blanco antiguo | `#FAEBD7` | Nostalgia, tradición y calidez suave; evoca lo vintage. | Memoria y sabiduría de lo pasado; pureza envejecida y ciclos antiguos. | derivado | derivado |
| `aqua` | aqua | `#00FFFF` | Frescura, claridad, energía y sensación de agua limpia. | Agua viva y purificación; flujo de la intuición y de la comunicación con lo sutil. | derivado | derivado |
| `aquamarine` | aguamarina | `#7FFFD4` | Frescura relajante, calma y optimismo ligero. | Piedra de los marineros: protección en los viajes, valor y claridad emocional. | derivado | tradición ([kunz1913](referencias.md#kunz1913)) |
| `azure` | azur | `#F0FFFF` | Cielo despejado, apertura, libertad y serenidad. | Altura espiritual y pureza del cielo; lo celestial y la mente abierta. | derivado | derivado |
| `beige` | beis | `#F5F5DC` | Neutralidad, sencillez, estabilidad y discreción. | Tierra y humildad; equilibrio sin extremos, la vida cotidiana y el arraigo. | derivado | derivado |
| `bisque` | bizcocho | `#FFE4C4` | Calidez suave, cercanía y sensación doméstica. | Abundancia y nutrición; hospitalidad y placeres sencillos. | derivado | derivado |
| `black` | negro | `#000000` | Elegancia, poder, formalidad y misterio; en muchas culturas, luto. | Vacío primordial, noche y lo oculto; protección y transformación (la nigredo alquímica). | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [hupka1997](referencias.md#hupka1997), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición ([abraham1998](referencias.md#abraham1998)) |
| `blanchedalmond` | almendra pálido | `#FFEBCD` | Suavidad, dulzura y calidez delicada. | Dulzura pura y lo sencillo; dones modestos y cuidados. | derivado | derivado |
| `blue` | azul | `#0000FF` | Confianza, calma, estabilidad, racionalidad y profesionalismo. | Verdad y lo espiritual; cielo y mar. En varias tradiciones protege del mal de ojo. | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición ([wiki-nazar](referencias.md#wiki-nazar)) |
| `blueviolet` | violeta azulado | `#8A2BE2` | Creatividad, ambición e individualidad; carácter intenso. | Intuición y conexión con lo psíquico; vínculo entre la mente terrenal y lo espiritual. | derivado | derivado |
| `brown` | marrón | `#A52A2A` | Estabilidad, seguridad, rusticidad y confiabilidad. | Tierra, arraigo y paciencia; conexión con el hogar y con lo material. | hay estudios ([berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | derivado |
| `burlywood` | madera clara | `#DEB887` | Calidez terrosa, confort y naturalidad. | Madera y naturaleza trabajada; el ciclo del crecimiento y la paciencia de la tierra. | derivado | derivado |
| `cadetblue` | azul cadete | `#5F9EA0` | Calma, serenidad, equilibrio y sobriedad. | Mar y serenidad; disciplina tranquila y pensamiento claro. | derivado | derivado |
| `chartreuse` | chartreuse | `#7FFF00` | Energía, vitalidad, novedad y un toque llamativo; también inquietud. | Renovación y primavera; juventud, crecimiento y energía vital. | derivado | derivado |
| `chocolate` | chocolate | `#D2691E` | Calidez, indulgencia, confort y sensación de hogar. | Abundancia y placer terrenal; tierra fértil y sensualidad moderada. | derivado | derivado |
| `coral` | coral | `#FF7F50` | Cordialidad, alegría, calidez social y optimismo. | Vida marina y protección; en la tradición mediterránea, amuleto contra el mal de ojo. | derivado | tradición ([elworthy1895](referencias.md#elworthy1895)) |
| `cornflowerblue` | azul aciano | `#6495ED` | Amabilidad, confianza, serenidad y apertura. | Fidelidad y comunicación sincera; espiritualidad tranquila. | derivado | derivado |
| `cornsilk` | seda de maíz | `#FFF8DC` | Suavidad, calidez, inocencia y sencillez. | Cosecha y ternura del campo; abundancia humilde y pureza. | derivado | derivado |
| `crimson` | carmesí | `#DC143C` | Pasión, intensidad, valor, urgencia y poder. | Sangre, vida y sacrificio; fuerza vital y amor apasionado, también advertencia. | derivado | derivado |
| `cyan` | cian | `#00FFFF` | Frescura, claridad, calma y comunicación; asociado a lo digital y moderno. | Agua y purificación; claridad mental y flujo de la comunicación. | derivado | derivado |
| `darkblue` | azul oscuro | `#00008B` | Profundidad, seriedad, fiabilidad y autoridad. | Noche profunda y sabiduría; lo inconsciente y la introspección. | derivado | derivado |
| `darkcyan` | cian oscuro | `#008B8B` | Calma profunda, equilibrio y sobriedad. | Agua profunda y sanación; equilibrio entre mente y emociones. | derivado | derivado |
| `darkgoldenrod` | dorado oscuro | `#B8860B` | Riqueza antigua, madurez y prestigio sobrio. | Oro envejecido: sabiduría adquirida, cosecha y poder terrenal. | derivado | derivado |
| `darkgray` | gris oscuro | `#A9A9A9` | Seriedad, neutralidad, distancia emocional y conservadurismo. | Neutralidad y transición; lo indefinido entre la luz y la sombra. | derivado | derivado |
| `darkgreen` | verde oscuro | `#006400` | Estabilidad, seguridad, naturaleza profunda y tradición. | Vida vegetal y renovación oculta; crecimiento silencioso y fertilidad. | derivado | derivado |
| `darkgrey` | gris oscuro | `#A9A9A9` | Seriedad, neutralidad, distancia emocional y conservadurismo. | Neutralidad y transición; lo indefinido entre la luz y la sombra. | derivado | derivado |
| `darkkhaki` | caqui oscuro | `#BDB76B` | Sobriedad, prudencia y modestia; a veces apatía o pasividad. | Tierra seca y paciencia; humildad ante los ciclos. | derivado | derivado |
| `darkmagenta` | magenta oscuro | `#8B008B` | Misterio, sofisticación, creatividad intensa y pasión contenida. | Transmutación y magia; vínculo entre el deseo y el espíritu. | derivado | derivado |
| `darkolivegreen` | verde oliva oscuro | `#556B2F` | Calma, naturaleza, estabilidad y discreción. | Paz de los bosques y longevidad; sabiduría de la tierra y tranquilidad. | derivado | derivado |
| `darkorange` | naranja oscuro | `#FF8C00` | Energía cálida, entusiasmo y creatividad con vigor. | Fuego controlado y cosecha; fortaleza vital y estímulo a la acción. | derivado | derivado |
| `darkorchid` | orquídea oscuro | `#9932CC` | Misterio, espiritualidad, creatividad y ambición refinada. | Sabiduría oculta y transformación; vínculo con lo sagrado. | derivado | derivado |
| `darkred` | rojo oscuro | `#8B0000` | Intensidad, seriedad y poder contenido; también peligro o tensión. | Sangre y fuerza vital; sacrificio, pasión y autoridad. | derivado | derivado |
| `darksalmon` | salmón oscuro | `#E9967A` | Calidez suave, sensibilidad y acogida moderada. | Sol que se pone: transición y dulzura de la despedida. | derivado | derivado |
| `darkseagreen` | verde mar oscuro | `#8FBC8F` | Calma natural, equilibrio, sencillez y bienestar. | Mar y bosque; crecimiento tranquilo, equilibrio y paciencia. | derivado | derivado |
| `darkslateblue` | azul pizarra oscuro | `#483D8B` | Introspección, sabiduría, seriedad y reserva. | Pensamiento profundo, intuición y visiones; espiritualidad contemplativa. | derivado | derivado |
| `darkslategray` | gris pizarra oscuro | `#2F4F4F` | Sobriedad, reserva, seguridad y precaución. | Piedra y montaña; lo oculto y la protección de lo que está dentro. | derivado | derivado |
| `darkslategrey` | gris pizarra oscuro | `#2F4F4F` | Sobriedad, reserva, seguridad y precaución. | Piedra y montaña; lo oculto y la protección de lo que está dentro. | derivado | derivado |
| `darkturquoise` | turquesa oscuro | `#00CED1` | Equilibrio entre calma y energía; comunicación clara. | Agua y cielo; sanación, protección y pureza de la mente. | derivado | derivado |
| `darkviolet` | violeta oscuro | `#9400D3` | Misterio, lujo, creatividad y ambición intensa. | Espiritualidad, magia y lo trascendente; transformación interior. | derivado | derivado |
| `deeppink` | rosa profundo | `#FF1493` | Pasión intensa, afecto, energía juvenil y audacia. | Amor y deseo vital; ternura apasionada. | derivado | derivado |
| `deepskyblue` | azul cielo profundo | `#00BFFF` | Vitalidad, libertad, confianza y energía serena. | Cielo y agua profunda; apertura, inspiración y comunicación con lo alto. | derivado | derivado |
| `dimgray` | gris tenue | `#696969` | Melancolía, neutralidad, reserva y sobriedad. | Niebla y transición; lo no revelado y el paso del tiempo. | derivado | derivado |
| `dimgrey` | gris tenue | `#696969` | Melancolía, neutralidad, reserva y sobriedad. | Niebla y transición; lo no revelado y el paso del tiempo. | derivado | derivado |
| `dodgerblue` | azul Dodger | `#1E90FF` | Confianza, dinamismo, modernidad y energía. | Cielo despejado y comunicación; mensajero, claridad y acción. | derivado | derivado |
| `firebrick` | ladrillo | `#B22222` | Fuerza, determinación, calidez intensa y tensión. | Fuego de la forja y del hogar; esfuerzo, sacrificio y trabajo. | derivado | derivado |
| `floralwhite` | blanco floral | `#FFFAF0` | Suavidad, delicadeza, pureza y ligereza. | Flor blanca: inocencia, comienzos y bendición de la primavera. | derivado | derivado |
| `forestgreen` | verde bosque | `#228B22` | Naturaleza, equilibrio, salud, calma y crecimiento. | Bosque sagrado: vida, renovación y conexión con la tierra. | derivado | derivado |
| `fuchsia` | fucsia | `#FF00FF` | Audacia, creatividad, excentricidad y energía lúdica. | Transformación y magia; vínculo entre lo material y lo espiritual. | derivado | derivado |
| `gainsboro` | gris Gainsboro | `#DCDCDC` | Neutralidad elegante, limpieza y calma. | Estado intermedio y de equilibrio, sin carga. | derivado | derivado |
| `ghostwhite` | blanco fantasma | `#F8F8FF` | Ligereza, misterio sutil y frialdad luminosa. | Lo etéreo y lo espectral; presencia sutil entre dos mundos. | derivado | derivado |
| `gold` | oro | `#FFD700` | Éxito, riqueza, prestigio, optimismo y calidez. | Sol y perfección; iluminación, poder, sabiduría y lo divino en muchas tradiciones. | derivado | tradición ([abraham1998](referencias.md#abraham1998)) |
| `goldenrod` | vara de oro | `#DAA520` | Riqueza cálida, confianza y prestigio amable. | Cosecha y abundancia; sabiduría alcanzada y fruto del trabajo. | derivado | derivado |
| `gray` | gris | `#808080` | Neutralidad, equilibrio, seriedad y ambigüedad; también melancolía. | Transición entre luz y sombra; mediación, reflexión y lo intermedio. | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | derivado |
| `green` | verde | `#008000` | Naturaleza, equilibrio, crecimiento, salud y calma; también esperanza. | Vida, renovación y sanación; en la tradición de los chakras, asociado al corazón (anahata). | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `greenyellow` | verde amarillento | `#ADFF2F` | Energía fresca, juventud, vitalidad y novedad. | Primavera y brote; comienzo, vitalidad y crecimiento. | derivado | derivado |
| `grey` | gris | `#808080` | Neutralidad, equilibrio, seriedad y ambigüedad; también melancolía. | Transición entre luz y sombra; mediación, reflexión y lo intermedio. | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | derivado |
| `honeydew` | rocío de miel | `#F0FFF0` | Frescura suave, ligereza, pureza y calma. | Rocío sagrado de la mañana; bendición, renovación y pureza. | derivado | derivado |
| `hotpink` | rosa fuerte | `#FF69B4` | Alegría, juego, ternura y energía afectuosa. | Amor del corazón; ternura, afecto y alegría de vivir. | derivado | derivado |
| `indianred` | rojo indio | `#CD5C5C` | Tensión contenida, calidez intensa, apego emocional y protección. | Sangre y tierra ocre; fuerza ancestral y protección del hogar. | derivado | derivado |
| `indigo` | índigo | `#4B0082` | Introspección, misterio, sabiduría y ambición tranquila. | En la tradición de los chakras, asociado al tercer ojo (ajna): intuición y visión interior. | derivado | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `ivory` | marfil | `#FFFFF0` | Pureza, elegancia, calma y suavidad. | Pureza y tiempo: lo noble, lo perdurable y lo sagrado. | derivado | derivado |
| `khaki` | caqui | `#F0E68C` | Sencillez, prudencia, neutralidad y practicidad. | Tierra seca y paciencia; la vida sencilla y el equilibrio. | derivado | derivado |
| `lavender` | lavanda | `#E6E6FA` | Calma, suavidad, nostalgia, delicadeza y bienestar. | Purificación y protección; sueño, paz y lo espiritual sutil. | derivado | derivado |
| `lavenderblush` | rubor de lavanda | `#FFF0F5` | Delicadeza, ternura, romanticismo y suavidad. | Pureza amorosa; dulzura de los comienzos. | derivado | derivado |
| `lawngreen` | verde césped | `#7CFC00` | Frescura, vitalidad, optimismo y juventud. | Primavera y campo verde; vida que brota y renovación. | derivado | derivado |
| `lemonchiffon` | gasa de limón | `#FFFACD` | Alegría suave, frescura y optimismo gentil. | Luz del sol matinal; claridad y comienzo amable. | derivado | derivado |
| `lightblue` | azul claro | `#ADD8E6` | Calma, serenidad, confianza y ligereza. | Cielo y agua en calma; paz, sueños y claridad. | derivado | derivado |
| `lightcoral` | coral claro | `#F08080` | Calidez suave, afecto, cercanía y sensibilidad. | Amor y protección; vitalidad suave del mar. | derivado | derivado |
| `lightcyan` | cian claro | `#E0FFFF` | Frescura ligera, claridad y calma. | Agua clara y aire puro; pureza de pensamiento. | derivado | derivado |
| `lightgoldenrodyellow` | amarillo dorado claro | `#FAFAD2` | Suavidad luminosa, optimismo tranquilo y calidez. | Luz dorada suave; iluminación gentil y bendiciones. | derivado | derivado |
| `lightgray` | gris claro | `#D3D3D3` | Neutralidad, calma, limpieza y espacio para respirar. | Transición entre lo visible y lo que no se ve; lo intermedio. | derivado | derivado |
| `lightgreen` | verde claro | `#90EE90` | Calma, frescura, renovación y bienestar. | Brote y esperanza; crecimiento tierno y sanación suave. | derivado | derivado |
| `lightgrey` | gris claro | `#D3D3D3` | Neutralidad, calma, limpieza y espacio para respirar. | Transición entre lo visible y lo que no se ve; lo intermedio. | derivado | derivado |
| `lightpink` | rosa claro | `#FFB6C1` | Ternura, dulzura, inocencia y afecto suave. | Amor delicado y pureza; comienzos románticos. | derivado | derivado |
| `lightsalmon` | salmón claro | `#FFA07A` | Calidez alegre, cercanía y energía amable. | Sol de la mañana; amabilidad y generosidad. | derivado | derivado |
| `lightseagreen` | verde mar claro | `#20B2AA` | Calma, equilibrio, frescura y comunicación. | Mar y sanación; equilibrio entre emoción y mente. | derivado | derivado |
| `lightskyblue` | azul cielo claro | `#87CEFA` | Ligereza, libertad, serenidad y optimismo. | Cielo abierto; inspiración, paz y aspiraciones elevadas. | derivado | derivado |
| `lightslategray` | gris pizarra claro | `#778899` | Neutralidad, calma reflexiva y sobriedad. | Transición entre luz y noche; reflexión y paciencia. | derivado | derivado |
| `lightslategrey` | gris pizarra claro | `#778899` | Neutralidad, calma reflexiva y sobriedad. | Transición entre luz y noche; reflexión y paciencia. | derivado | derivado |
| `lightsteelblue` | azul acero claro | `#B0C4DE` | Calma, sobriedad, confianza y elegancia. | Metal y cielo; disciplina serena y claridad. | derivado | derivado |
| `lightyellow` | amarillo claro | `#FFFFE0` | Luz suave, optimismo, claridad y ligereza. | Luz del alba; comienzo, iluminación y conocimiento suave. | derivado | derivado |
| `lime` | lima | `#00FF00` | Energía, frescura, vitalidad y actividad; a veces, señal de alerta. | Brote y crecimiento; renovación y fuerza vital. | derivado | derivado |
| `limegreen` | verde lima | `#32CD32` | Energía, juventud, optimismo y dinamismo. | Vida que brota; renovación, salud y primavera. | derivado | derivado |
| `linen` | lino | `#FAF0E6` | Sencillez, calma, naturalidad y pureza suave. | Lino de los templos y de las vestiduras sagradas; pureza y humildad. | derivado | tradición |
| `magenta` | magenta | `#FF00FF` | Creatividad, audacia, intensidad y energía vibrante. | Transformación y espiritualidad; conexión entre lo material y lo sutil. | derivado | derivado |
| `maroon` | granate | `#800000` | Seriedad, poder, tradición, elegancia y pasión contenida. | Sangre y tierra; vitalidad, sacrificio y arraigo. | derivado | derivado |
| `mediumaquamarine` | aguamarina medio | `#66CDAA` | Calma, frescura, armonía y bienestar. | Agua de sanación; equilibrio y fluidez emocional. | derivado | derivado |
| `mediumblue` | azul medio | `#0000CD` | Confianza, serenidad, profundidad y seriedad. | Noche estrellada y verdad; introspección y guía. | derivado | derivado |
| `mediumorchid` | orquídea medio | `#BA55D3` | Creatividad, sensibilidad, elegancia y misterio. | Espiritualidad y transformación; intuición y sabiduría oculta. | derivado | derivado |
| `mediumpurple` | púrpura medio | `#9370DB` | Creatividad, sensibilidad, sofisticación y calma. | Realeza espiritual y visión; intuición y conexión con lo sutil. | derivado | derivado |
| `mediumseagreen` | verde mar medio | `#3CB371` | Calma, equilibrio, bienestar y crecimiento. | Mar y renovación; armonía, fertilidad y sanación. | derivado | derivado |
| `mediumslateblue` | azul pizarra medio | `#7B68EE` | Creatividad, imaginación, libertad y ligereza mental. | Inspiración y visión; conexión con lo celestial. | derivado | derivado |
| `mediumspringgreen` | verde primavera medio | `#00FA9A` | Frescura, vitalidad, optimismo y renovación. | Primavera y crecimiento; nueva vida y esperanza. | derivado | derivado |
| `mediumturquoise` | turquesa medio | `#48D1CC` | Calma, claridad, frescura y comunicación. | Agua y cielo; protección, sanación y pureza. | derivado | derivado |
| `mediumvioletred` | rojo violeta medio | `#C71585` | Pasión, intensidad, ternura apasionada y ambición. | Deseo y vitalidad; amor intenso y fuerza emocional. | derivado | derivado |
| `midnightblue` | azul medianoche | `#191970` | Profundidad, seriedad, misterio y confianza. | Noche, sueño y conocimiento oculto; introspección y lo desconocido. | derivado | derivado |
| `mintcream` | crema menta | `#F5FFFA` | Frescura suave, pureza, calma y limpieza. | Menta: purificación, renovación y claridad mental. | derivado | derivado |
| `mistyrose` | rosa brumoso | `#FFE4E1` | Delicadeza, nostalgia, suavidad y romanticismo. | Amanecer brumoso; sueño, recuerdo y lo que se desvanece. | derivado | derivado |
| `moccasin` | mocasín | `#FFE4B5` | Calidez suave, sencillez, confort y amabilidad. | Tierra y camino; viaje, humildad y hogar. | derivado | derivado |
| `navajowhite` | blanco navajo | `#FFDEAD` | Calidez terrosa, sencillez y confort. | Arena y cosecha; sencillez y abundancia humilde. | derivado | derivado |
| `navy` | azul marino | `#000080` | Autoridad, confianza, seriedad y profesionalismo. | Mar profundo y noche; disciplina, sabiduría y lealtad. | derivado | derivado |
| `oldlace` | encaje antiguo | `#FDF5E6` | Nostalgia, delicadeza, calidez y tradición. | Memoria y ternura de lo antiguo; pureza envejecida y cuidado. | derivado | derivado |
| `olive` | oliva | `#808000` | Paz, naturaleza, moderación y estabilidad; también cansancio. | Olivo: paz, sabiduría, victoria tranquila y fertilidad. | derivado | tradición |
| `olivedrab` | verde oliva | `#6B8E23` | Naturaleza, practicidad, estabilidad y sobriedad. | Campo y vida silvestre; perseverancia y arraigo. | derivado | derivado |
| `orange` | naranja | `#FFA500` | Entusiasmo, creatividad, calidez, energía y sociabilidad. | En la tradición de los chakras, asociado al chakra sacro: vitalidad, creatividad y placer. | hay estudios ([berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `orangered` | naranja rojizo | `#FF4500` | Energía intensa, impulso, entusiasmo y urgencia. | Fuego y ardor; acción, valor y transformación. | derivado | derivado |
| `orchid` | orquídea | `#DA70D6` | Elegancia, sensibilidad, romanticismo y creatividad. | Belleza y transformación; espiritualidad refinada. | derivado | derivado |
| `palegoldenrod` | vara de oro pálido | `#EEE8AA` | Suavidad, serenidad, calidez y optimismo tranquilo. | Luz de cosecha; abundancia gentil y bendición. | derivado | derivado |
| `palegreen` | verde pálido | `#98FB98` | Calma, frescura, renovación y bienestar. | Brote tierno; esperanza, sanación y crecimiento suave. | derivado | derivado |
| `paleturquoise` | turquesa pálido | `#AFEEEE` | Calma, ligereza, frescura y claridad. | Agua tranquila y cielo; pureza, sanación y paz. | derivado | derivado |
| `palevioletred` | rojo violeta pálido | `#DB7093` | Ternura, afecto, sensibilidad y nostalgia. | Amor delicado; sensibilidad y vínculos afectivos. | derivado | derivado |
| `papayawhip` | crema de papaya | `#FFEFD5` | Calidez suave, dulzura, confort y alegría tropical. | Fruta del sol y abundancia; hospitalidad y placer sencillo. | derivado | derivado |
| `peachpuff` | melocotón | `#FFDAB9` | Calidez, ternura, cercanía y bienestar. | En China, el melocotón simboliza longevidad e inmortalidad; dulzura y buena fortuna. | derivado | tradición ([wiki-peaches](referencias.md#wiki-peaches)) |
| `peru` | perú | `#CD853F` | Calidez terrosa, confianza, arraigo y estabilidad. | Tierra y cosecha; arraigo, trabajo y abundancia. | derivado | derivado |
| `pink` | rosa | `#FFC0CB` | Ternura, dulzura, romanticismo, calma y afecto. | Amor, compasión y pureza; corazón abierto. | hay estudios ([berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | derivado |
| `plum` | ciruela | `#DDA0DD` | Sofisticación, delicadeza, creatividad y nostalgia. | Fruta de otoño y abundancia; dulzura madura, sabiduría y amor refinado. | derivado | derivado |
| `powderblue` | azul polvo | `#B0E0E6` | Calma, delicadeza, limpieza y serenidad. | Cielo suave y agua clara; paz, pureza y sueños. | derivado | derivado |
| `purple` | púrpura | `#800080` | Realeza, lujo, espiritualidad, creatividad y misterio. | Realeza y lo espiritual; en algunas tradiciones, asociado al chakra corona: conexión con lo divino. | hay estudios ([berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `rebeccapurple` | púrpura Rebecca | `#663399` | Creatividad, profundidad, misterio y elegancia contenida. | Espiritualidad profunda; sabiduría, meditación y el umbral de lo invisible. | derivado | derivado |
| `red` | rojo | `#FF0000` | Pasión, energía, urgencia, peligro y acción. | En la tradición de los chakras, el primero (raíz) es rojo: arraigo, supervivencia y vitalidad. | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [hupka1997](referencias.md#hupka1997), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `rosybrown` | marrón rosado | `#BC8F8F` | Calidez suave, nostalgia, sensibilidad y sencillez. | Tierra y amor sereno; ternura de lo cotidiano. | derivado | derivado |
| `royalblue` | azul real | `#4169E1` | Autoridad, confianza, lealtad, prestigio y serenidad. | Realeza y lealtad; justicia, sabiduría y dignidad. | derivado | derivado |
| `saddlebrown` | marrón silla | `#8B4513` | Robustez, tradición, confort y arraigo. | Tierra fértil y trabajo; hogar, estabilidad y resistencia. | derivado | derivado |
| `salmon` | salmón | `#FA8072` | Calidez, cercanía, alegría y sensibilidad. | Sol y mar; vitalidad, afecto y transición. | derivado | derivado |
| `sandybrown` | marrón arena | `#F4A460` | Calidez, cordialidad, naturalidad y calma. | Desierto y arena; paciencia, tiempo y transformación. | derivado | derivado |
| `seagreen` | verde mar | `#2E8B57` | Calma, equilibrio, naturaleza y estabilidad. | Mar y vida; sanación, abundancia y renovación. | derivado | derivado |
| `seashell` | concha marina | `#FFF5EE` | Suavidad, pureza, delicadeza y calma. | Concha: símbolo de nacimiento y fertilidad (la Venus que emerge del mar) y de protección. | derivado | tradición |
| `sienna` | siena | `#A0522D` | Tierra, arraigo, calidez robusta y confiabilidad. | Tierra roja y fuerza ancestral; arraigo, fertilidad y raíces. | derivado | derivado |
| `silver` | plata | `#C0C0C0` | Modernidad, sofisticación, neutralidad elegante y prestigio. | Luna y reflejo; en la alquimia, la plata corresponde a la Luna: intuición y cambio. | derivado | tradición ([abraham1998](referencias.md#abraham1998)) |
| `skyblue` | azul cielo | `#87CEEB` | Calma, apertura, libertad y frescura. | Cielo y aire; inspiración, paz y elevación. | derivado | derivado |
| `slateblue` | azul pizarra | `#6A5ACD` | Creatividad, imaginación, misterio y serenidad. | Visión y espiritualidad; intuición y conexión con lo elevado. | derivado | derivado |
| `slategray` | gris pizarra | `#708090` | Neutralidad, seriedad, sobriedad y calma. | Piedra y tiempo; firmeza, paciencia y lo estable. | derivado | derivado |
| `slategrey` | gris pizarra | `#708090` | Neutralidad, seriedad, sobriedad y calma. | Piedra y tiempo; firmeza, paciencia y lo estable. | derivado | derivado |
| `snow` | nieve | `#FFFAFA` | Pureza, limpieza, calma y frialdad. | Pureza absoluta, silencio y renovación del invierno. | derivado | derivado |
| `springgreen` | verde primavera | `#00FF7F` | Frescura, vitalidad, optimismo y renovación. | Primavera: brote, comienzo y esperanza. | derivado | derivado |
| `steelblue` | azul acero | `#4682B4` | Confianza, disciplina, fuerza y estabilidad. | Metal y cielo; fortaleza, determinación y claridad. | derivado | derivado |
| `tan` | bronceado | `#D2B48C` | Calidez terrosa, sencillez, naturalidad y confort. | Tierra y sol; arraigo, cuidado y lo cotidiano. | derivado | derivado |
| `teal` | verde azulado | `#008080` | Equilibrio, calma, profesionalismo y creatividad. | Agua y cielo; equilibrio entre emoción y razón, sanación y paz. | derivado | derivado |
| `thistle` | cardo | `#D8BFD8` | Suavidad, nostalgia, delicadeza y calma. | Planta de protección; defensa y dulzura. | derivado | tradición |
| `tomato` | tomate | `#FF6347` | Energía, calidez, pasión, apetito y alerta. | Fruto del sol y de la vida; vitalidad, amor y generosidad. | derivado | derivado |
| `turquoise` | turquesa | `#40E0D0` | Calma, claridad, frescura y comunicación. | Piedra de protección y sanación; amuleto para el viajero y guía contra el mal. | hay estudios ([jonauskaite2020](referencias.md#jonauskaite2020)) | tradición ([kunz1913](referencias.md#kunz1913)) |
| `violet` | violeta | `#EE82EE` | Creatividad, espiritualidad, misterio y sensibilidad. | En la tradición de los chakras, asociado al chakra corona: conexión con lo trascendente. | derivado | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `wheat` | trigo | `#F5DEB3` | Sencillez, calidez, abundancia tranquila y confort. | Cosecha y pan; fertilidad, abundancia y gratitud. | derivado | derivado |
| `white` | blanco | `#FFFFFF` | Pureza, limpieza, paz, inocencia y comienzos; en otras culturas, luto. | Pureza, luz y lo sagrado; renacimiento y unión con lo divino (la albedo alquímica). | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición ([abraham1998](referencias.md#abraham1998)) |
| `whitesmoke` | humo blanco | `#F5F5F5` | Neutralidad suave, limpieza, calma y sencillez. | Humo sutil: lo intangible, la transición y la plegaria que se eleva. | derivado | derivado |
| `yellow` | amarillo | `#FFFF00` | Alegría, optimismo, energía, claridad mental y atención; también precaución. | En la tradición de los chakras, asociado al plexo solar: iluminación, voluntad y conocimiento. | hay estudios ([adams1973](referencias.md#adams1973), [berlinkay1969](referencias.md#berlinkay1969), [jonauskaite2020](referencias.md#jonauskaite2020)) | tradición moderna ([leland-quest](referencias.md#leland-quest), [leland2016](referencias.md#leland2016)) |
| `yellowgreen` | verde amarillo | `#9ACD32` | Frescura, vitalidad, juventud y calma. | Primavera y crecimiento; vida nueva y renovación. | derivado | derivado |
<!-- tabla:fin -->
