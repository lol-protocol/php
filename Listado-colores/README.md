# Listado de colores con nombre y su significado

Léxico de colores con nombre para el proyecto de etiquetado *part-of-speech*: las palabras de color aparecen como sustantivos, adjetivos y nombres propios, y este listado da la lista de nombres a reconocer. Tiene tres archivos de datos con alcances distintos:

| Archivo | Qué contiene | Idiomas | Significados |
|---|---|---|---|
| `colores.csv` | 148 colores CSS, anotados: nombre en 30 idiomas, hex y significados en español | 30 (ver lista abajo) | Psicológico y místico, en español |
| `significados-idiomas.csv` | Los significados de esos 148 colores en los otros 29 idiomas (4.292 filas) | 29 | Psicológico y místico, traducidos |
| `nombres-completos.csv` | 31.935 nombres de color con su hex, de la lista de `meodai/color-names`, y el color CSS más cercano a cada uno | 1: inglés | No tiene propios; se pueden heredar del color CSS más cercano (ver «Color CSS más cercano») |

Cada color de los 148 se puede leer en cualquiera de los 30 idiomas (nombre, hex y los dos significados) en `por-idioma/<código>.md`; `colores-por-idioma.md` es el índice de esas páginas.

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

La lista completa solo está en inglés. Traducir 31.935 nombres a 30 idiomas sería casi un millón de cadenas, sin fuente que las respalde, y muchos de esos nombres son marcas, nombres propios o términos sin traducción.

Varias palabras de la lista completa son también palabras corrientes en español, como `coral` o `magenta`, y otras son nombres propios o términos técnicos. Para el etiquetado conviene tener en cuenta que la misma palabra puede ser un color o no, según el contexto.

## Color CSS más cercano

Los 148 colores CSS tienen significados; los 31.935 nombres de la lista completa no. Para poder darles una pista sin inventar nada, `nombres-completos.csv` trae tres columnas calculadas:

- `css_cercano`: el color CSS cuyo hex está más cerca del hex del nombre.
- `delta_e`: esa distancia, con dos decimales.
- `cercania`: una banda según la distancia: `exacto` (mismo hex), `alta` (hasta 3), `media` (hasta 8) y `baja` (más de 8).

Cómo se calcula (`colorimetria.py`, solo biblioteca estándar):

- El hex (sRGB) se pasa a CIELAB con el iluminante D65 y la distancia es CIEDE2000 (ΔE00), que se acerca más que la distancia euclídea a cómo se perciben las diferencias de color.
- La implementación sigue las notas de Sharma, Wu y Dalal (2005), *The CIEDE2000 color-difference formula: Implementation notes, supplementary test data, and mathematical observations* (Color Research & Application 30(1), 21-30). El generador comprueba al empezar 16 pares de su tabla de datos de prueba y se detiene si alguno no coincide.
- Si dos colores CSS están igual de cerca (por ejemplo `gray` y `grey`, que comparten hex, o `aqua` y `cyan`), gana el primero por orden alfabético. Por eso los nueve segundos de cada par (`cyan`, `magenta` y las siete variantes `…grey`) no aparecen nunca como color más cercano.
- Calcularlo para toda la lista tarda unos 25 segundos; `--comprobar` lo recalcula entero.

Reparto en la lista completa: 136 exactos (0,4 %), 3.814 de cercanía alta (11,9 %), 17.103 media (53,6 %) y 10.882 baja (34,1 %).

Límites que conviene tener presentes:

- Los umbrales de las bandas son de este proyecto, no un estándar: se eligieron a la vista de ese reparto.
- Solo hay 148 colores CSS y están repartidos de forma desigual en el espacio de color: muchos nombres (sobre todo los apagados y los grises) terminan en `dimgray`, `tan`, `gray` o `darkslategray`.
- Heredar el significado solo tiene sentido con cercanía `exacto` o `alta`. En `media` y `baja` el color CSS puede ser de otra familia: `Burgundy` (`#900020`) queda en `brown` con distancia 7,15 y `Lilac` (`#CEA2FD`) en `plum` con 8,62. Quien use esto debe filtrar por `cercania` y tratar el resultado como derivado.

## Fuentes y verificación

- **Nombres en inglés y hex de la tabla anotada:** especificación *CSS Color Module Level 4*, sección de colores con nombre (código fuente en `w3c/csswg-drafts`). Los 148 nombres y sus valores hex coinciden con esa fuente y con una tabla JSON de referencia (`bahamas10/css-color-names`). `rebeccapurple` aparece en la especificación como adición de CSS Color Level 4.
- **Lista completa:** `meodai/color-names` (archivo `src/colornames.csv`), con licencia MIT, incluida en `LICENCIA-meodai-color-names.txt`. Los 31.935 hex son válidos y los nombres son únicos sin distinguir mayúsculas. No se ha verificado nombre a nombre contra otra fuente.
- **Traducciones de los nombres a los 29 idiomas que no son inglés:** propuestas, no revisadas por hablantes nativos. Están en `colores.csv` y se ven en `por-idioma/`. La calidad puede variar mucho de un idioma a otro, y las de los menos comunes (por ejemplo, bengalí, persa o suajili) son las menos fiables. Antes de usarlas como referencia, conviene una revisión de un hablante nativo por idioma.
- **Traducciones de los significados a los otros 29 idiomas:** las hizo un asistente de IA a partir del texto en español de `colores.csv`, y nadie con dominio nativo de cada idioma las ha revisado. Valen lo mismo que la nota anterior: son un punto de partida, y las de idiomas menos comunes o de escritura no latina son las menos fiables. Los términos de otras tradiciones (los chakras, el *nigredo* y el *albedo* de la alquimia) están transliterados a la escritura de cada idioma.
- **Nombres en español:** propuestas de este listado, no una norma oficial de la RAE. Cinco de ellos son idénticos al inglés (`aqua`, `chartreuse`, `chocolate`, `coral`, `magenta`).
- **Significados:** escritos para este listado, en español. Lo *psicológico* son asociaciones culturales frecuentes en diseño y marketing; la evidencia científica sobre el efecto real de un color en una persona es limitada y varía según la cultura. Lo *místico* resume simbolismos de tradiciones como la alquimia, los chakras, el simbolismo cristiano, el mediterráneo popular o la cultura china. Ambos describen convenciones y creencias, no propiedades comprobables. Los significados son los mismos en los 30 idiomas: se tradujeron, no se adaptaron a cada cultura, así que el simbolismo de un color en un país concreto puede diferir de lo que dice su página.

## Archivos

- `colores.csv`: tabla anotada. Columnas: `nombre_ingles`, `nombre_es`, `nombre_fr`, `nombre_pt`, `nombre_it`, `nombre_de`, … hasta `nombre_sw` (29 columnas de idioma en total, además del inglés), `hex`, `significado_psicologico`, `significado_mistico`.
- `significados-idiomas.csv`: los significados en los otros 29 idiomas. Columnas: `idioma`, `nombre_ingles`, `significado_psicologico`, `significado_mistico`. Una fila por color e idioma, ordenadas por `nombre_ingles` y, dentro de cada color, por el orden de idiomas de `generar-tabla.py`. Los pares que en `colores.csv` tienen el mismo texto en español (como `gray` y `grey`) tienen también el mismo texto traducido.
- `por-idioma/<código>.md`: una página por idioma con el nombre, el hex y los dos significados de los 148 colores. Generado.
- `colores-por-idioma.md`: índice de las páginas por idioma. Generado.
- `nombres-completos.csv`: lista completa. Columnas de la fuente: `nombre_ingles`, `hex`, `curada`. Columnas calculadas por el generador: `css_cercano`, `delta_e`, `cercania`.
- `colorimetria.py`: sRGB a CIELAB y CIEDE2000, con los vectores de prueba de Sharma et al. Lo importa `generar-tabla.py`.
- `generar-tabla.py`: valida los tres CSV y genera la tabla de este README, `colores-por-idioma.md` y `por-idioma/`.
- `LICENCIA-meodai-color-names.txt`: licencia MIT de la fuente de la lista completa.
- `README.md`: este documento. La tabla va entre dos líneas que son marcadores HTML de inicio y fin (`tabla:inicio` y `tabla:fin`); no la edites a mano.

## Uso

Requiere Python 3. No usa dependencias externas.

```bash
cd Listado-colores
python3 generar-tabla.py                # tras editar un CSV: valida y regenera README, colores-por-idioma.md y por-idioma/
python3 generar-tabla.py --comprobar    # solo comprueba: datos válidos y archivos generados al día
```

Reglas para editar los CSV (el generador las comprueba):

- `colores.csv`: `nombre_ingles` en minúsculas, sin espacios y único; `hex` en `#RRGGBB` y en mayúsculas; ningún campo vacío; ningún campo con `|` ni saltos de línea; filas ordenadas por `nombre_ingles`.
- `significados-idiomas.csv`: un par de significados por cada color de `colores.csv` y cada idioma (todos menos `es`), sin repetidos; ningún campo vacío, con `|` ni con saltos de línea; ningún significado igual al texto en español (sin traducir). Si cambias un significado en `colores.csv`, actualiza también sus 29 traducciones.
- `nombres-completos.csv`: nombre único sin distinguir mayúsculas; `hex` en `#RRGGBB`; `curada` vacía o `x`; filas ordenadas por nombre sin distinguir mayúsculas; ningún nombre con `|`, comas, comillas ni saltos de línea. Las tres primeras columnas se generaron una vez desde la fuente; `css_cercano`, `delta_e` y `cercania` las reescribe el generador y `--comprobar` falla si no coinciden con lo que calcula.

## Tabla

<!-- tabla:inicio -->
| Inglés | Español | Hex | Significado psicológico | Significado místico |
|---|---|---|---|---|
| `aliceblue` | azul alicia | `#F0F8FF` | Calma, frescura y limpieza; sensación de ligereza y amplitud. | Pureza celestial y serenidad; asociado al cielo despejado y a la comunicación clara. |
| `antiquewhite` | blanco antiguo | `#FAEBD7` | Nostalgia, tradición y calidez suave; evoca lo vintage. | Memoria y sabiduría de lo pasado; pureza envejecida y ciclos antiguos. |
| `aqua` | aqua | `#00FFFF` | Frescura, claridad, energía y sensación de agua limpia. | Agua viva y purificación; flujo de la intuición y de la comunicación con lo sutil. |
| `aquamarine` | aguamarina | `#7FFFD4` | Frescura relajante, calma y optimismo ligero. | Piedra de los marineros: protección en los viajes, valor y claridad emocional. |
| `azure` | azur | `#F0FFFF` | Cielo despejado, apertura, libertad y serenidad. | Altura espiritual y pureza del cielo; lo celestial y la mente abierta. |
| `beige` | beis | `#F5F5DC` | Neutralidad, sencillez, estabilidad y discreción. | Tierra y humildad; equilibrio sin extremos, la vida cotidiana y el arraigo. |
| `bisque` | bizcocho | `#FFE4C4` | Calidez suave, cercanía y sensación doméstica. | Abundancia y nutrición; hospitalidad y placeres sencillos. |
| `black` | negro | `#000000` | Elegancia, poder, formalidad y misterio; en muchas culturas, luto. | Vacío primordial, noche y lo oculto; protección y transformación (la nigredo alquímica). |
| `blanchedalmond` | almendra pálido | `#FFEBCD` | Suavidad, dulzura y calidez delicada. | Dulzura pura y lo sencillo; dones modestos y cuidados. |
| `blue` | azul | `#0000FF` | Confianza, calma, estabilidad, racionalidad y profesionalismo. | Verdad y lo espiritual; cielo y mar. En varias tradiciones protege del mal de ojo. |
| `blueviolet` | violeta azulado | `#8A2BE2` | Creatividad, ambición e individualidad; carácter intenso. | Intuición y conexión con lo psíquico; vínculo entre la mente terrenal y lo espiritual. |
| `brown` | marrón | `#A52A2A` | Estabilidad, seguridad, rusticidad y confiabilidad. | Tierra, arraigo y paciencia; conexión con el hogar y con lo material. |
| `burlywood` | madera clara | `#DEB887` | Calidez terrosa, confort y naturalidad. | Madera y naturaleza trabajada; el ciclo del crecimiento y la paciencia de la tierra. |
| `cadetblue` | azul cadete | `#5F9EA0` | Calma, serenidad, equilibrio y sobriedad. | Mar y serenidad; disciplina tranquila y pensamiento claro. |
| `chartreuse` | chartreuse | `#7FFF00` | Energía, vitalidad, novedad y un toque llamativo; también inquietud. | Renovación y primavera; juventud, crecimiento y energía vital. |
| `chocolate` | chocolate | `#D2691E` | Calidez, indulgencia, confort y sensación de hogar. | Abundancia y placer terrenal; tierra fértil y sensualidad moderada. |
| `coral` | coral | `#FF7F50` | Cordialidad, alegría, calidez social y optimismo. | Vida marina y protección; en la tradición mediterránea, amuleto contra el mal de ojo. |
| `cornflowerblue` | azul aciano | `#6495ED` | Amabilidad, confianza, serenidad y apertura. | Fidelidad y comunicación sincera; espiritualidad tranquila. |
| `cornsilk` | seda de maíz | `#FFF8DC` | Suavidad, calidez, inocencia y sencillez. | Cosecha y ternura del campo; abundancia humilde y pureza. |
| `crimson` | carmesí | `#DC143C` | Pasión, intensidad, valor, urgencia y poder. | Sangre, vida y sacrificio; fuerza vital y amor apasionado, también advertencia. |
| `cyan` | cian | `#00FFFF` | Frescura, claridad, calma y comunicación; asociado a lo digital y moderno. | Agua y purificación; claridad mental y flujo de la comunicación. |
| `darkblue` | azul oscuro | `#00008B` | Profundidad, seriedad, fiabilidad y autoridad. | Noche profunda y sabiduría; lo inconsciente y la introspección. |
| `darkcyan` | cian oscuro | `#008B8B` | Calma profunda, equilibrio y sobriedad. | Agua profunda y sanación; equilibrio entre mente y emociones. |
| `darkgoldenrod` | dorado oscuro | `#B8860B` | Riqueza antigua, madurez y prestigio sobrio. | Oro envejecido: sabiduría adquirida, cosecha y poder terrenal. |
| `darkgray` | gris oscuro | `#A9A9A9` | Seriedad, neutralidad, distancia emocional y conservadurismo. | Neutralidad y transición; lo indefinido entre la luz y la sombra. |
| `darkgreen` | verde oscuro | `#006400` | Estabilidad, seguridad, naturaleza profunda y tradición. | Vida vegetal y renovación oculta; crecimiento silencioso y fertilidad. |
| `darkgrey` | gris oscuro | `#A9A9A9` | Seriedad, neutralidad, distancia emocional y conservadurismo. | Neutralidad y transición; lo indefinido entre la luz y la sombra. |
| `darkkhaki` | caqui oscuro | `#BDB76B` | Sobriedad, prudencia y modestia; a veces apatía o pasividad. | Tierra seca y paciencia; humildad ante los ciclos. |
| `darkmagenta` | magenta oscuro | `#8B008B` | Misterio, sofisticación, creatividad intensa y pasión contenida. | Transmutación y magia; vínculo entre el deseo y el espíritu. |
| `darkolivegreen` | verde oliva oscuro | `#556B2F` | Calma, naturaleza, estabilidad y discreción. | Paz de los bosques y longevidad; sabiduría de la tierra y tranquilidad. |
| `darkorange` | naranja oscuro | `#FF8C00` | Energía cálida, entusiasmo y creatividad con vigor. | Fuego controlado y cosecha; fortaleza vital y estímulo a la acción. |
| `darkorchid` | orquídea oscuro | `#9932CC` | Misterio, espiritualidad, creatividad y ambición refinada. | Sabiduría oculta y transformación; vínculo con lo sagrado. |
| `darkred` | rojo oscuro | `#8B0000` | Intensidad, seriedad y poder contenido; también peligro o tensión. | Sangre y fuerza vital; sacrificio, pasión y autoridad. |
| `darksalmon` | salmón oscuro | `#E9967A` | Calidez suave, sensibilidad y acogida moderada. | Sol que se pone: transición y dulzura de la despedida. |
| `darkseagreen` | verde mar oscuro | `#8FBC8F` | Calma natural, equilibrio, sencillez y bienestar. | Mar y bosque; crecimiento tranquilo, equilibrio y paciencia. |
| `darkslateblue` | azul pizarra oscuro | `#483D8B` | Introspección, sabiduría, seriedad y reserva. | Pensamiento profundo, intuición y visiones; espiritualidad contemplativa. |
| `darkslategray` | gris pizarra oscuro | `#2F4F4F` | Sobriedad, reserva, seguridad y precaución. | Piedra y montaña; lo oculto y la protección de lo que está dentro. |
| `darkslategrey` | gris pizarra oscuro | `#2F4F4F` | Sobriedad, reserva, seguridad y precaución. | Piedra y montaña; lo oculto y la protección de lo que está dentro. |
| `darkturquoise` | turquesa oscuro | `#00CED1` | Equilibrio entre calma y energía; comunicación clara. | Agua y cielo; sanación, protección y pureza de la mente. |
| `darkviolet` | violeta oscuro | `#9400D3` | Misterio, lujo, creatividad y ambición intensa. | Espiritualidad, magia y lo trascendente; transformación interior. |
| `deeppink` | rosa profundo | `#FF1493` | Pasión intensa, afecto, energía juvenil y audacia. | Amor y deseo vital; ternura apasionada. |
| `deepskyblue` | azul cielo profundo | `#00BFFF` | Vitalidad, libertad, confianza y energía serena. | Cielo y agua profunda; apertura, inspiración y comunicación con lo alto. |
| `dimgray` | gris tenue | `#696969` | Melancolía, neutralidad, reserva y sobriedad. | Niebla y transición; lo no revelado y el paso del tiempo. |
| `dimgrey` | gris tenue | `#696969` | Melancolía, neutralidad, reserva y sobriedad. | Niebla y transición; lo no revelado y el paso del tiempo. |
| `dodgerblue` | azul Dodger | `#1E90FF` | Confianza, dinamismo, modernidad y energía. | Cielo despejado y comunicación; mensajero, claridad y acción. |
| `firebrick` | ladrillo | `#B22222` | Fuerza, determinación, calidez intensa y tensión. | Fuego de la forja y del hogar; esfuerzo, sacrificio y trabajo. |
| `floralwhite` | blanco floral | `#FFFAF0` | Suavidad, delicadeza, pureza y ligereza. | Flor blanca: inocencia, comienzos y bendición de la primavera. |
| `forestgreen` | verde bosque | `#228B22` | Naturaleza, equilibrio, salud, calma y crecimiento. | Bosque sagrado: vida, renovación y conexión con la tierra. |
| `fuchsia` | fucsia | `#FF00FF` | Audacia, creatividad, excentricidad y energía lúdica. | Transformación y magia; vínculo entre lo material y lo espiritual. |
| `gainsboro` | gris Gainsboro | `#DCDCDC` | Neutralidad elegante, limpieza y calma. | Estado intermedio y de equilibrio, sin carga. |
| `ghostwhite` | blanco fantasma | `#F8F8FF` | Ligereza, misterio sutil y frialdad luminosa. | Lo etéreo y lo espectral; presencia sutil entre dos mundos. |
| `gold` | oro | `#FFD700` | Éxito, riqueza, prestigio, optimismo y calidez. | Sol y perfección; iluminación, poder, sabiduría y lo divino en muchas tradiciones. |
| `goldenrod` | vara de oro | `#DAA520` | Riqueza cálida, confianza y prestigio amable. | Cosecha y abundancia; sabiduría alcanzada y fruto del trabajo. |
| `gray` | gris | `#808080` | Neutralidad, equilibrio, seriedad y ambigüedad; también melancolía. | Transición entre luz y sombra; mediación, reflexión y lo intermedio. |
| `green` | verde | `#008000` | Naturaleza, equilibrio, crecimiento, salud y calma; también esperanza. | Vida, renovación y sanación; en la tradición de los chakras, asociado al corazón (anahata). |
| `greenyellow` | verde amarillento | `#ADFF2F` | Energía fresca, juventud, vitalidad y novedad. | Primavera y brote; comienzo, vitalidad y crecimiento. |
| `grey` | gris | `#808080` | Neutralidad, equilibrio, seriedad y ambigüedad; también melancolía. | Transición entre luz y sombra; mediación, reflexión y lo intermedio. |
| `honeydew` | rocío de miel | `#F0FFF0` | Frescura suave, ligereza, pureza y calma. | Rocío sagrado de la mañana; bendición, renovación y pureza. |
| `hotpink` | rosa fuerte | `#FF69B4` | Alegría, juego, ternura y energía afectuosa. | Amor del corazón; ternura, afecto y alegría de vivir. |
| `indianred` | rojo indio | `#CD5C5C` | Tensión contenida, calidez intensa, apego emocional y protección. | Sangre y tierra ocre; fuerza ancestral y protección del hogar. |
| `indigo` | índigo | `#4B0082` | Introspección, misterio, sabiduría y ambición tranquila. | En la tradición de los chakras, asociado al tercer ojo (ajna): intuición y visión interior. |
| `ivory` | marfil | `#FFFFF0` | Pureza, elegancia, calma y suavidad. | Pureza y tiempo: lo noble, lo perdurable y lo sagrado. |
| `khaki` | caqui | `#F0E68C` | Sencillez, prudencia, neutralidad y practicidad. | Tierra seca y paciencia; la vida sencilla y el equilibrio. |
| `lavender` | lavanda | `#E6E6FA` | Calma, suavidad, nostalgia, delicadeza y bienestar. | Purificación y protección; sueño, paz y lo espiritual sutil. |
| `lavenderblush` | rubor de lavanda | `#FFF0F5` | Delicadeza, ternura, romanticismo y suavidad. | Pureza amorosa; dulzura de los comienzos. |
| `lawngreen` | verde césped | `#7CFC00` | Frescura, vitalidad, optimismo y juventud. | Primavera y campo verde; vida que brota y renovación. |
| `lemonchiffon` | gasa de limón | `#FFFACD` | Alegría suave, frescura y optimismo gentil. | Luz del sol matinal; claridad y comienzo amable. |
| `lightblue` | azul claro | `#ADD8E6` | Calma, serenidad, confianza y ligereza. | Cielo y agua en calma; paz, sueños y claridad. |
| `lightcoral` | coral claro | `#F08080` | Calidez suave, afecto, cercanía y sensibilidad. | Amor y protección; vitalidad suave del mar. |
| `lightcyan` | cian claro | `#E0FFFF` | Frescura ligera, claridad y calma. | Agua clara y aire puro; pureza de pensamiento. |
| `lightgoldenrodyellow` | amarillo dorado claro | `#FAFAD2` | Suavidad luminosa, optimismo tranquilo y calidez. | Luz dorada suave; iluminación gentil y bendiciones. |
| `lightgray` | gris claro | `#D3D3D3` | Neutralidad, calma, limpieza y espacio para respirar. | Transición entre lo visible y lo que no se ve; lo intermedio. |
| `lightgreen` | verde claro | `#90EE90` | Calma, frescura, renovación y bienestar. | Brote y esperanza; crecimiento tierno y sanación suave. |
| `lightgrey` | gris claro | `#D3D3D3` | Neutralidad, calma, limpieza y espacio para respirar. | Transición entre lo visible y lo que no se ve; lo intermedio. |
| `lightpink` | rosa claro | `#FFB6C1` | Ternura, dulzura, inocencia y afecto suave. | Amor delicado y pureza; comienzos románticos. |
| `lightsalmon` | salmón claro | `#FFA07A` | Calidez alegre, cercanía y energía amable. | Sol de la mañana; amabilidad y generosidad. |
| `lightseagreen` | verde mar claro | `#20B2AA` | Calma, equilibrio, frescura y comunicación. | Mar y sanación; equilibrio entre emoción y mente. |
| `lightskyblue` | azul cielo claro | `#87CEFA` | Ligereza, libertad, serenidad y optimismo. | Cielo abierto; inspiración, paz y aspiraciones elevadas. |
| `lightslategray` | gris pizarra claro | `#778899` | Neutralidad, calma reflexiva y sobriedad. | Transición entre luz y noche; reflexión y paciencia. |
| `lightslategrey` | gris pizarra claro | `#778899` | Neutralidad, calma reflexiva y sobriedad. | Transición entre luz y noche; reflexión y paciencia. |
| `lightsteelblue` | azul acero claro | `#B0C4DE` | Calma, sobriedad, confianza y elegancia. | Metal y cielo; disciplina serena y claridad. |
| `lightyellow` | amarillo claro | `#FFFFE0` | Luz suave, optimismo, claridad y ligereza. | Luz del alba; comienzo, iluminación y conocimiento suave. |
| `lime` | lima | `#00FF00` | Energía, frescura, vitalidad y actividad; a veces, señal de alerta. | Brote y crecimiento; renovación y fuerza vital. |
| `limegreen` | verde lima | `#32CD32` | Energía, juventud, optimismo y dinamismo. | Vida que brota; renovación, salud y primavera. |
| `linen` | lino | `#FAF0E6` | Sencillez, calma, naturalidad y pureza suave. | Lino de los templos y de las vestiduras sagradas; pureza y humildad. |
| `magenta` | magenta | `#FF00FF` | Creatividad, audacia, intensidad y energía vibrante. | Transformación y espiritualidad; conexión entre lo material y lo sutil. |
| `maroon` | granate | `#800000` | Seriedad, poder, tradición, elegancia y pasión contenida. | Sangre y tierra; vitalidad, sacrificio y arraigo. |
| `mediumaquamarine` | aguamarina medio | `#66CDAA` | Calma, frescura, armonía y bienestar. | Agua de sanación; equilibrio y fluidez emocional. |
| `mediumblue` | azul medio | `#0000CD` | Confianza, serenidad, profundidad y seriedad. | Noche estrellada y verdad; introspección y guía. |
| `mediumorchid` | orquídea medio | `#BA55D3` | Creatividad, sensibilidad, elegancia y misterio. | Espiritualidad y transformación; intuición y sabiduría oculta. |
| `mediumpurple` | púrpura medio | `#9370DB` | Creatividad, sensibilidad, sofisticación y calma. | Realeza espiritual y visión; intuición y conexión con lo sutil. |
| `mediumseagreen` | verde mar medio | `#3CB371` | Calma, equilibrio, bienestar y crecimiento. | Mar y renovación; armonía, fertilidad y sanación. |
| `mediumslateblue` | azul pizarra medio | `#7B68EE` | Creatividad, imaginación, libertad y ligereza mental. | Inspiración y visión; conexión con lo celestial. |
| `mediumspringgreen` | verde primavera medio | `#00FA9A` | Frescura, vitalidad, optimismo y renovación. | Primavera y crecimiento; nueva vida y esperanza. |
| `mediumturquoise` | turquesa medio | `#48D1CC` | Calma, claridad, frescura y comunicación. | Agua y cielo; protección, sanación y pureza. |
| `mediumvioletred` | rojo violeta medio | `#C71585` | Pasión, intensidad, ternura apasionada y ambición. | Deseo y vitalidad; amor intenso y fuerza emocional. |
| `midnightblue` | azul medianoche | `#191970` | Profundidad, seriedad, misterio y confianza. | Noche, sueño y conocimiento oculto; introspección y lo desconocido. |
| `mintcream` | crema menta | `#F5FFFA` | Frescura suave, pureza, calma y limpieza. | Menta: purificación, renovación y claridad mental. |
| `mistyrose` | rosa brumoso | `#FFE4E1` | Delicadeza, nostalgia, suavidad y romanticismo. | Amanecer brumoso; sueño, recuerdo y lo que se desvanece. |
| `moccasin` | mocasín | `#FFE4B5` | Calidez suave, sencillez, confort y amabilidad. | Tierra y camino; viaje, humildad y hogar. |
| `navajowhite` | blanco navajo | `#FFDEAD` | Calidez terrosa, sencillez y confort. | Arena y cosecha; sencillez y abundancia humilde. |
| `navy` | azul marino | `#000080` | Autoridad, confianza, seriedad y profesionalismo. | Mar profundo y noche; disciplina, sabiduría y lealtad. |
| `oldlace` | encaje antiguo | `#FDF5E6` | Nostalgia, delicadeza, calidez y tradición. | Memoria y ternura de lo antiguo; pureza envejecida y cuidado. |
| `olive` | oliva | `#808000` | Paz, naturaleza, moderación y estabilidad; también cansancio. | Olivo: paz, sabiduría, victoria tranquila y fertilidad. |
| `olivedrab` | verde oliva | `#6B8E23` | Naturaleza, practicidad, estabilidad y sobriedad. | Campo y vida silvestre; perseverancia y arraigo. |
| `orange` | naranja | `#FFA500` | Entusiasmo, creatividad, calidez, energía y sociabilidad. | En la tradición de los chakras, asociado al chakra sacro: vitalidad, creatividad y placer. |
| `orangered` | naranja rojizo | `#FF4500` | Energía intensa, impulso, entusiasmo y urgencia. | Fuego y ardor; acción, valor y transformación. |
| `orchid` | orquídea | `#DA70D6` | Elegancia, sensibilidad, romanticismo y creatividad. | Belleza y transformación; espiritualidad refinada. |
| `palegoldenrod` | vara de oro pálido | `#EEE8AA` | Suavidad, serenidad, calidez y optimismo tranquilo. | Luz de cosecha; abundancia gentil y bendición. |
| `palegreen` | verde pálido | `#98FB98` | Calma, frescura, renovación y bienestar. | Brote tierno; esperanza, sanación y crecimiento suave. |
| `paleturquoise` | turquesa pálido | `#AFEEEE` | Calma, ligereza, frescura y claridad. | Agua tranquila y cielo; pureza, sanación y paz. |
| `palevioletred` | rojo violeta pálido | `#DB7093` | Ternura, afecto, sensibilidad y nostalgia. | Amor delicado; sensibilidad y vínculos afectivos. |
| `papayawhip` | crema de papaya | `#FFEFD5` | Calidez suave, dulzura, confort y alegría tropical. | Fruta del sol y abundancia; hospitalidad y placer sencillo. |
| `peachpuff` | melocotón | `#FFDAB9` | Calidez, ternura, cercanía y bienestar. | En China, el melocotón simboliza longevidad e inmortalidad; dulzura y buena fortuna. |
| `peru` | perú | `#CD853F` | Calidez terrosa, confianza, arraigo y estabilidad. | Tierra y cosecha; arraigo, trabajo y abundancia. |
| `pink` | rosa | `#FFC0CB` | Ternura, dulzura, romanticismo, calma y afecto. | Amor, compasión y pureza; corazón abierto. |
| `plum` | ciruela | `#DDA0DD` | Sofisticación, delicadeza, creatividad y nostalgia. | Fruta de otoño y abundancia; dulzura madura, sabiduría y amor refinado. |
| `powderblue` | azul polvo | `#B0E0E6` | Calma, delicadeza, limpieza y serenidad. | Cielo suave y agua clara; paz, pureza y sueños. |
| `purple` | púrpura | `#800080` | Realeza, lujo, espiritualidad, creatividad y misterio. | Realeza y lo espiritual; en algunas tradiciones, asociado al chakra corona: conexión con lo divino. |
| `rebeccapurple` | púrpura Rebecca | `#663399` | Creatividad, profundidad, misterio y elegancia contenida. | Espiritualidad profunda; sabiduría, meditación y el umbral de lo invisible. |
| `red` | rojo | `#FF0000` | Pasión, energía, urgencia, peligro y acción. | En la tradición de los chakras, el primero (raíz) es rojo: arraigo, supervivencia y vitalidad. |
| `rosybrown` | marrón rosado | `#BC8F8F` | Calidez suave, nostalgia, sensibilidad y sencillez. | Tierra y amor sereno; ternura de lo cotidiano. |
| `royalblue` | azul real | `#4169E1` | Autoridad, confianza, lealtad, prestigio y serenidad. | Realeza y lealtad; justicia, sabiduría y dignidad. |
| `saddlebrown` | marrón silla | `#8B4513` | Robustez, tradición, confort y arraigo. | Tierra fértil y trabajo; hogar, estabilidad y resistencia. |
| `salmon` | salmón | `#FA8072` | Calidez, cercanía, alegría y sensibilidad. | Sol y mar; vitalidad, afecto y transición. |
| `sandybrown` | marrón arena | `#F4A460` | Calidez, cordialidad, naturalidad y calma. | Desierto y arena; paciencia, tiempo y transformación. |
| `seagreen` | verde mar | `#2E8B57` | Calma, equilibrio, naturaleza y estabilidad. | Mar y vida; sanación, abundancia y renovación. |
| `seashell` | concha marina | `#FFF5EE` | Suavidad, pureza, delicadeza y calma. | Concha: símbolo de nacimiento y fertilidad (la Venus que emerge del mar) y de protección. |
| `sienna` | siena | `#A0522D` | Tierra, arraigo, calidez robusta y confiabilidad. | Tierra roja y fuerza ancestral; arraigo, fertilidad y raíces. |
| `silver` | plata | `#C0C0C0` | Modernidad, sofisticación, neutralidad elegante y prestigio. | Luna y reflejo; en la alquimia, la plata corresponde a la Luna: intuición y cambio. |
| `skyblue` | azul cielo | `#87CEEB` | Calma, apertura, libertad y frescura. | Cielo y aire; inspiración, paz y elevación. |
| `slateblue` | azul pizarra | `#6A5ACD` | Creatividad, imaginación, misterio y serenidad. | Visión y espiritualidad; intuición y conexión con lo elevado. |
| `slategray` | gris pizarra | `#708090` | Neutralidad, seriedad, sobriedad y calma. | Piedra y tiempo; firmeza, paciencia y lo estable. |
| `slategrey` | gris pizarra | `#708090` | Neutralidad, seriedad, sobriedad y calma. | Piedra y tiempo; firmeza, paciencia y lo estable. |
| `snow` | nieve | `#FFFAFA` | Pureza, limpieza, calma y frialdad. | Pureza absoluta, silencio y renovación del invierno. |
| `springgreen` | verde primavera | `#00FF7F` | Frescura, vitalidad, optimismo y renovación. | Primavera: brote, comienzo y esperanza. |
| `steelblue` | azul acero | `#4682B4` | Confianza, disciplina, fuerza y estabilidad. | Metal y cielo; fortaleza, determinación y claridad. |
| `tan` | bronceado | `#D2B48C` | Calidez terrosa, sencillez, naturalidad y confort. | Tierra y sol; arraigo, cuidado y lo cotidiano. |
| `teal` | verde azulado | `#008080` | Equilibrio, calma, profesionalismo y creatividad. | Agua y cielo; equilibrio entre emoción y razón, sanación y paz. |
| `thistle` | cardo | `#D8BFD8` | Suavidad, nostalgia, delicadeza y calma. | Planta de protección; defensa y dulzura. |
| `tomato` | tomate | `#FF6347` | Energía, calidez, pasión, apetito y alerta. | Fruto del sol y de la vida; vitalidad, amor y generosidad. |
| `turquoise` | turquesa | `#40E0D0` | Calma, claridad, frescura y comunicación. | Piedra de protección y sanación; amuleto para el viajero y guía contra el mal. |
| `violet` | violeta | `#EE82EE` | Creatividad, espiritualidad, misterio y sensibilidad. | En la tradición de los chakras, asociado al chakra corona: conexión con lo trascendente. |
| `wheat` | trigo | `#F5DEB3` | Sencillez, calidez, abundancia tranquila y confort. | Cosecha y pan; fertilidad, abundancia y gratitud. |
| `white` | blanco | `#FFFFFF` | Pureza, limpieza, paz, inocencia y comienzos; en otras culturas, luto. | Pureza, luz y lo sagrado; renacimiento y unión con lo divino (la albedo alquímica). |
| `whitesmoke` | humo blanco | `#F5F5F5` | Neutralidad suave, limpieza, calma y sencillez. | Humo sutil: lo intangible, la transición y la plegaria que se eleva. |
| `yellow` | amarillo | `#FFFF00` | Alegría, optimismo, energía, claridad mental y atención; también precaución. | En la tradición de los chakras, asociado al plexo solar: iluminación, voluntad y conocimiento. |
| `yellowgreen` | verde amarillo | `#9ACD32` | Frescura, vitalidad, juventud y calma. | Primavera y crecimiento; vida nueva y renovación. |
<!-- tabla:fin -->
