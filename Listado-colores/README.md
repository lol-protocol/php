# Listado de colores con nombre y su significado

Léxico de colores con nombre para el proyecto de etiquetado *part-of-speech*: las palabras de color aparecen como sustantivos, adjetivos y nombres propios, y este listado da la lista de nombres a reconocer. Tiene dos archivos con alcances distintos:

| Archivo | Qué contiene | Idiomas de cada nombre | Significados |
|---|---|---|---|
| `colores.csv` | 148 colores CSS, anotados (tabla de abajo) | 5: inglés, español, francés, portugués, italiano | Psicológico y místico |
| `nombres-completos.csv` | 31.935 nombres de color con su hex, de la lista de `meodai/color-names` | 1: inglés | No tiene |

## Alcance

No existe un conjunto cerrado de "todos los colores que tienen nombre": los catálogos comerciales (Pantone, RAL, Crayola) y las listas colaborativas tienen cada uno su propia cifra. Por eso el listado tiene dos capas:

- **Lista completa** (`nombres-completos.csv`): la colección curada más grande que se pudo verificar y que tiene licencia abierta. Tiene 31.935 nombres únicos. La columna `curada` marca con `x` los 4.966 que sus curadores consideran buenos nombres (por ejemplo, `3AM Latte`); los demás son válidos pero menos típicos.
- **Tabla anotada** (`colores.csv`): los 148 colores con nombre de la especificación CSS, con sus equivalentes en cinco idiomas y sus significados. Es el conjunto estándar, verificado contra la fuente oficial.

Escribir significados para los 31.935 nombres no es viable con calidad. Si se quiere ampliar la tabla anotada, el criterio sería el subconjunto `curada` (4.966 nombres), pero requiere una decisión sobre cuáles se anotan.

Varias palabras de la lista completa son también palabras corrientes en español, como `coral` o `magenta`, y otras son nombres propios o términos técnicos. Para el etiquetado conviene tener en cuenta que la misma palabra puede ser un color o no, según el contexto.

## Fuentes y verificación

- **Nombres y hex de la tabla anotada:** especificación *CSS Color Module Level 4*, sección de colores con nombre (código fuente en `w3c/csswg-drafts`). Los 148 nombres y sus valores hex coinciden con esa fuente y con una tabla JSON de referencia (`bahamas10/css-color-names`). `rebeccapurple` aparece en la especificación como adición de CSS Color Level 4.
- **Lista completa:** `meodai/color-names` (archivo `src/colornames.csv`), con licencia MIT. Está incluida en `LICENCIA-meodai-color-names.txt`. Los 31.935 hex son válidos y los nombres son únicos sin distinguir mayúsculas. No se ha verificado nombre a nombre contra otra fuente.
- **Traducciones a francés, portugués e italiano:** propuestas, no revisadas por hablantes nativos.
- **Nombres en español:** propuestas de este listado, no una norma oficial de la RAE. Cinco de ellos son idénticos al inglés (`aqua`, `chartreuse`, `chocolate`, `coral`, `magenta`).
- **Significados:** escritos para este listado. Lo *psicológico* son asociaciones culturales frecuentes en diseño y marketing; la evidencia científica sobre el efecto real de un color en una persona es limitada y varía según la cultura. Lo *místico* resume simbolismos de tradiciones como la alquimia, los chakras, el simbolismo cristiano, el mediterráneo popular o la cultura china. Ambos describen convenciones y creencias, no propiedades comprobables.

## Archivos

- `colores.csv`: tabla anotada. Columnas: `nombre_ingles`, `nombre_espanol`, `nombre_frances`, `nombre_portugues`, `nombre_italiano`, `hex`, `significado_psicologico`, `significado_mistico`.
- `nombres-completos.csv`: lista completa. Columnas: `nombre_ingles`, `hex`, `curada`.
- `generar-tabla.py`: valida los dos CSV y regenera la tabla de este README.
- `LICENCIA-meodai-color-names.txt`: licencia MIT de la fuente de la lista completa.
- `README.md`: este documento. La tabla va entre dos líneas que son marcadores HTML de inicio y fin (`tabla:inicio` y `tabla:fin`); no la edites a mano.

## Uso

Requiere Python 3. No usa dependencias externas.

```bash
cd Listado-colores
python3 generar-tabla.py                # tras editar colores.csv: valida y regenera la tabla
python3 generar-tabla.py --comprobar    # solo comprueba: datos válidos y README al día
```

Reglas para editar los CSV (el generador las comprueba):

- `colores.csv`: `nombre_ingles` en minúsculas, sin espacios y único; `hex` en `#RRGGBB` y en mayúsculas; ningún campo vacío; ningún campo con `|` ni saltos de línea; filas ordenadas por `nombre_ingles`.
- `nombres-completos.csv`: nombre único sin distinguir mayúsculas; `hex` en `#RRGGBB`; `curada` vacía o `x`; filas ordenadas por nombre sin distinguir mayúsculas. Se generó una vez desde la fuente; el generador solo lo valida.

## Tabla

<!-- tabla:inicio -->
| Inglés | Español | Francés | Portugués | Italiano | Hex | Significado psicológico | Significado místico |
|---|---|---|---|---|---|---|---|
| `aliceblue` | azul alicia | bleu alice | azul alice | blu alice | `#F0F8FF` | Calma, frescura y limpieza; sensación de ligereza y amplitud. | Pureza celestial y serenidad; asociado al cielo despejado y a la comunicación clara. |
| `antiquewhite` | blanco antiguo | blanc antique | branco antigo | bianco antico | `#FAEBD7` | Nostalgia, tradición y calidez suave; evoca lo vintage. | Memoria y sabiduría de lo pasado; pureza envejecida y ciclos antiguos. |
| `aqua` | aqua | aqua | aqua | acqua | `#00FFFF` | Frescura, claridad, energía y sensación de agua limpia. | Agua viva y purificación; flujo de la intuición y de la comunicación con lo sutil. |
| `aquamarine` | aguamarina | aigue-marine | aquamarina | acquamarina | `#7FFFD4` | Frescura relajante, calma y optimismo ligero. | Piedra de los marineros: protección en los viajes, valor y claridad emocional. |
| `azure` | azur | azur | azul-celeste | azzurro | `#F0FFFF` | Cielo despejado, apertura, libertad y serenidad. | Altura espiritual y pureza del cielo; lo celestial y la mente abierta. |
| `beige` | beis | beige | bege | beige | `#F5F5DC` | Neutralidad, sencillez, estabilidad y discreción. | Tierra y humildad; equilibrio sin extremos, la vida cotidiana y el arraigo. |
| `bisque` | bizcocho | bisque | bisque | bisque | `#FFE4C4` | Calidez suave, cercanía y sensación doméstica. | Abundancia y nutrición; hospitalidad y placeres sencillos. |
| `black` | negro | noir | preto | nero | `#000000` | Elegancia, poder, formalidad y misterio; en muchas culturas, luto. | Vacío primordial, noche y lo oculto; protección y transformación (la nigredo alquímica). |
| `blanchedalmond` | almendra pálido | amande blanchie | amêndoa pálida | mandorla chiara | `#FFEBCD` | Suavidad, dulzura y calidez delicada. | Dulzura pura y lo sencillo; dones modestos y cuidados. |
| `blue` | azul | bleu | azul | blu | `#0000FF` | Confianza, calma, estabilidad, racionalidad y profesionalismo. | Verdad y lo espiritual; cielo y mar. En varias tradiciones protege del mal de ojo. |
| `blueviolet` | violeta azulado | bleu-violet | azul-violeta | viola blu | `#8A2BE2` | Creatividad, ambición e individualidad; carácter intenso. | Intuición y conexión con lo psíquico; vínculo entre la mente terrenal y lo espiritual. |
| `brown` | marrón | brun | marrom | marrone | `#A52A2A` | Estabilidad, seguridad, rusticidad y confiabilidad. | Tierra, arraigo y paciencia; conexión con el hogar y con lo material. |
| `burlywood` | madera clara | bois clair | madeira clara | legno chiaro | `#DEB887` | Calidez terrosa, confort y naturalidad. | Madera y naturaleza trabajada; el ciclo del crecimiento y la paciencia de la tierra. |
| `cadetblue` | azul cadete | bleu cadet | azul-cadete | blu cadetto | `#5F9EA0` | Calma, serenidad, equilibrio y sobriedad. | Mar y serenidad; disciplina tranquila y pensamiento claro. |
| `chartreuse` | chartreuse | chartreuse | chartreuse | chartreuse | `#7FFF00` | Energía, vitalidad, novedad y un toque llamativo; también inquietud. | Renovación y primavera; juventud, crecimiento y energía vital. |
| `chocolate` | chocolate | chocolat | chocolate | cioccolato | `#D2691E` | Calidez, indulgencia, confort y sensación de hogar. | Abundancia y placer terrenal; tierra fértil y sensualidad moderada. |
| `coral` | coral | corail | coral | corallo | `#FF7F50` | Cordialidad, alegría, calidez social y optimismo. | Vida marina y protección; en la tradición mediterránea, amuleto contra el mal de ojo. |
| `cornflowerblue` | azul aciano | bleu bleuet | azul-centáurea | blu fiordaliso | `#6495ED` | Amabilidad, confianza, serenidad y apertura. | Fidelidad y comunicación sincera; espiritualidad tranquila. |
| `cornsilk` | seda de maíz | soie de maïs | seda de milho | seta di mais | `#FFF8DC` | Suavidad, calidez, inocencia y sencillez. | Cosecha y ternura del campo; abundancia humilde y pureza. |
| `crimson` | carmesí | cramoisi | carmesim | cremisi | `#DC143C` | Pasión, intensidad, valor, urgencia y poder. | Sangre, vida y sacrificio; fuerza vital y amor apasionado, también advertencia. |
| `cyan` | cian | cyan | ciano | ciano | `#00FFFF` | Frescura, claridad, calma y comunicación; asociado a lo digital y moderno. | Agua y purificación; claridad mental y flujo de la comunicación. |
| `darkblue` | azul oscuro | bleu foncé | azul-escuro | blu scuro | `#00008B` | Profundidad, seriedad, fiabilidad y autoridad. | Noche profunda y sabiduría; lo inconsciente y la introspección. |
| `darkcyan` | cian oscuro | cyan foncé | ciano escuro | ciano scuro | `#008B8B` | Calma profunda, equilibrio y sobriedad. | Agua profunda y sanación; equilibrio entre mente y emociones. |
| `darkgoldenrod` | dorado oscuro | or foncé | dourado escuro | oro scuro | `#B8860B` | Riqueza antigua, madurez y prestigio sobrio. | Oro envejecido: sabiduría adquirida, cosecha y poder terrenal. |
| `darkgray` | gris oscuro | gris foncé | cinza-escuro | grigio scuro | `#A9A9A9` | Seriedad, neutralidad, distancia emocional y conservadurismo. | Neutralidad y transición; lo indefinido entre la luz y la sombra. |
| `darkgreen` | verde oscuro | vert foncé | verde-escuro | verde scuro | `#006400` | Estabilidad, seguridad, naturaleza profunda y tradición. | Vida vegetal y renovación oculta; crecimiento silencioso y fertilidad. |
| `darkgrey` | gris oscuro | gris foncé | cinza-escuro | grigio scuro | `#A9A9A9` | Seriedad, neutralidad, distancia emocional y conservadurismo. | Neutralidad y transición; lo indefinido entre la luz y la sombra. |
| `darkkhaki` | caqui oscuro | kaki foncé | cáqui escuro | cachi scuro | `#BDB76B` | Sobriedad, prudencia y modestia; a veces apatía o pasividad. | Tierra seca y paciencia; humildad ante los ciclos. |
| `darkmagenta` | magenta oscuro | magenta foncé | magenta escuro | magenta scuro | `#8B008B` | Misterio, sofisticación, creatividad intensa y pasión contenida. | Transmutación y magia; vínculo entre el deseo y el espíritu. |
| `darkolivegreen` | verde oliva oscuro | vert olive foncé | verde-oliva escuro | verde oliva scuro | `#556B2F` | Calma, naturaleza, estabilidad y discreción. | Paz de los bosques y longevidad; sabiduría de la tierra y tranquilidad. |
| `darkorange` | naranja oscuro | orange foncé | laranja escuro | arancione scuro | `#FF8C00` | Energía cálida, entusiasmo y creatividad con vigor. | Fuego controlado y cosecha; fortaleza vital y estímulo a la acción. |
| `darkorchid` | orquídea oscuro | orchidée foncée | orquídea escura | orchidea scura | `#9932CC` | Misterio, espiritualidad, creatividad y ambición refinada. | Sabiduría oculta y transformación; vínculo con lo sagrado. |
| `darkred` | rojo oscuro | rouge foncé | vermelho escuro | rosso scuro | `#8B0000` | Intensidad, seriedad y poder contenido; también peligro o tensión. | Sangre y fuerza vital; sacrificio, pasión y autoridad. |
| `darksalmon` | salmón oscuro | saumon foncé | salmão escuro | salmone scuro | `#E9967A` | Calidez suave, sensibilidad y acogida moderada. | Sol que se pone: transición y dulzura de la despedida. |
| `darkseagreen` | verde mar oscuro | vert de mer foncé | verde-mar escuro | verde mare scuro | `#8FBC8F` | Calma natural, equilibrio, sencillez y bienestar. | Mar y bosque; crecimiento tranquilo, equilibrio y paciencia. |
| `darkslateblue` | azul pizarra oscuro | bleu ardoise foncé | azul-ardósia escuro | blu ardesia scuro | `#483D8B` | Introspección, sabiduría, seriedad y reserva. | Pensamiento profundo, intuición y visiones; espiritualidad contemplativa. |
| `darkslategray` | gris pizarra oscuro | gris ardoise foncé | cinza-ardósia escuro | grigio ardesia scuro | `#2F4F4F` | Sobriedad, reserva, seguridad y precaución. | Piedra y montaña; lo oculto y la protección de lo que está dentro. |
| `darkslategrey` | gris pizarra oscuro | gris ardoise foncé | cinza-ardósia escuro | grigio ardesia scuro | `#2F4F4F` | Sobriedad, reserva, seguridad y precaución. | Piedra y montaña; lo oculto y la protección de lo que está dentro. |
| `darkturquoise` | turquesa oscuro | turquoise foncé | turquesa escuro | turchese scuro | `#00CED1` | Equilibrio entre calma y energía; comunicación clara. | Agua y cielo; sanación, protección y pureza de la mente. |
| `darkviolet` | violeta oscuro | violet foncé | violeta escuro | viola scuro | `#9400D3` | Misterio, lujo, creatividad y ambición intensa. | Espiritualidad, magia y lo trascendente; transformación interior. |
| `deeppink` | rosa profundo | rose profond | rosa-profundo | rosa intenso | `#FF1493` | Pasión intensa, afecto, energía juvenil y audacia. | Amor y deseo vital; ternura apasionada. |
| `deepskyblue` | azul cielo profundo | bleu ciel profond | azul-celeste profundo | azzurro cielo intenso | `#00BFFF` | Vitalidad, libertad, confianza y energía serena. | Cielo y agua profunda; apertura, inspiración y comunicación con lo alto. |
| `dimgray` | gris tenue | gris sombre | cinza-fosco | grigio spento | `#696969` | Melancolía, neutralidad, reserva y sobriedad. | Niebla y transición; lo no revelado y el paso del tiempo. |
| `dimgrey` | gris tenue | gris sombre | cinza-fosco | grigio spento | `#696969` | Melancolía, neutralidad, reserva y sobriedad. | Niebla y transición; lo no revelado y el paso del tiempo. |
| `dodgerblue` | azul Dodger | bleu dodger | azul-dodger | blu dodger | `#1E90FF` | Confianza, dinamismo, modernidad y energía. | Cielo despejado y comunicación; mensajero, claridad y acción. |
| `firebrick` | ladrillo | rouge brique | vermelho-tijolo | rosso mattone | `#B22222` | Fuerza, determinación, calidez intensa y tensión. | Fuego de la forja y del hogar; esfuerzo, sacrificio y trabajo. |
| `floralwhite` | blanco floral | blanc floral | branco-floral | bianco floreale | `#FFFAF0` | Suavidad, delicadeza, pureza y ligereza. | Flor blanca: inocencia, comienzos y bendición de la primavera. |
| `forestgreen` | verde bosque | vert forêt | verde-floresta | verde foresta | `#228B22` | Naturaleza, equilibrio, salud, calma y crecimiento. | Bosque sagrado: vida, renovación y conexión con la tierra. |
| `fuchsia` | fucsia | fuchsia | fúcsia | fucsia | `#FF00FF` | Audacia, creatividad, excentricidad y energía lúdica. | Transformación y magia; vínculo entre lo material y lo espiritual. |
| `gainsboro` | gris Gainsboro | gris Gainsborough | cinza Gainsborough | grigio Gainsborough | `#DCDCDC` | Neutralidad elegante, limpieza y calma. | Estado intermedio y de equilibrio, sin carga. |
| `ghostwhite` | blanco fantasma | blanc fantôme | branco-fantasma | bianco fantasma | `#F8F8FF` | Ligereza, misterio sutil y frialdad luminosa. | Lo etéreo y lo espectral; presencia sutil entre dos mundos. |
| `gold` | oro | or | ouro | oro | `#FFD700` | Éxito, riqueza, prestigio, optimismo y calidez. | Sol y perfección; iluminación, poder, sabiduría y lo divino en muchas tradiciones. |
| `goldenrod` | vara de oro | verge d'or | vara-de-ouro | verga d'oro | `#DAA520` | Riqueza cálida, confianza y prestigio amable. | Cosecha y abundancia; sabiduría alcanzada y fruto del trabajo. |
| `gray` | gris | gris | cinza | grigio | `#808080` | Neutralidad, equilibrio, seriedad y ambigüedad; también melancolía. | Transición entre luz y sombra; mediación, reflexión y lo intermedio. |
| `green` | verde | vert | verde | verde | `#008000` | Naturaleza, equilibrio, crecimiento, salud y calma; también esperanza. | Vida, renovación y sanación; en la tradición de los chakras, asociado al corazón (anahata). |
| `greenyellow` | verde amarillento | vert-jaune | verde-amarelado | verde giallo | `#ADFF2F` | Energía fresca, juventud, vitalidad y novedad. | Primavera y brote; comienzo, vitalidad y crecimiento. |
| `grey` | gris | gris | cinza | grigio | `#808080` | Neutralidad, equilibrio, seriedad y ambigüedad; también melancolía. | Transición entre luz y sombra; mediación, reflexión y lo intermedio. |
| `honeydew` | rocío de miel | rosée de miel | orvalho de mel | rugiada di miele | `#F0FFF0` | Frescura suave, ligereza, pureza y calma. | Rocío sagrado de la mañana; bendición, renovación y pureza. |
| `hotpink` | rosa fuerte | rose vif | rosa-choque | rosa acceso | `#FF69B4` | Alegría, juego, ternura y energía afectuosa. | Amor del corazón; ternura, afecto y alegría de vivir. |
| `indianred` | rojo indio | rouge indien | vermelho-indiano | rosso indiano | `#CD5C5C` | Tensión contenida, calidez intensa, apego emocional y protección. | Sangre y tierra ocre; fuerza ancestral y protección del hogar. |
| `indigo` | índigo | indigo | índigo | indaco | `#4B0082` | Introspección, misterio, sabiduría y ambición tranquila. | En la tradición de los chakras, asociado al tercer ojo (ajna): intuición y visión interior. |
| `ivory` | marfil | ivoire | marfim | avorio | `#FFFFF0` | Pureza, elegancia, calma y suavidad. | Pureza y tiempo: lo noble, lo perdurable y lo sagrado. |
| `khaki` | caqui | kaki | cáqui | cachi | `#F0E68C` | Sencillez, prudencia, neutralidad y practicidad. | Tierra seca y paciencia; la vida sencilla y el equilibrio. |
| `lavender` | lavanda | lavande | lavanda | lavanda | `#E6E6FA` | Calma, suavidad, nostalgia, delicadeza y bienestar. | Purificación y protección; sueño, paz y lo espiritual sutil. |
| `lavenderblush` | rubor de lavanda | rose lavande | rubor-de-lavanda | rosa lavanda | `#FFF0F5` | Delicadeza, ternura, romanticismo y suavidad. | Pureza amorosa; dulzura de los comienzos. |
| `lawngreen` | verde césped | vert pelouse | verde-gramado | verde prato | `#7CFC00` | Frescura, vitalidad, optimismo y juventud. | Primavera y campo verde; vida que brota y renovación. |
| `lemonchiffon` | gasa de limón | mousseline citron | chiffon-limão | chiffon di limone | `#FFFACD` | Alegría suave, frescura y optimismo gentil. | Luz del sol matinal; claridad y comienzo amable. |
| `lightblue` | azul claro | bleu clair | azul-claro | azzurro chiaro | `#ADD8E6` | Calma, serenidad, confianza y ligereza. | Cielo y agua en calma; paz, sueños y claridad. |
| `lightcoral` | coral claro | corail clair | coral claro | corallo chiaro | `#F08080` | Calidez suave, afecto, cercanía y sensibilidad. | Amor y protección; vitalidad suave del mar. |
| `lightcyan` | cian claro | cyan clair | ciano claro | ciano chiaro | `#E0FFFF` | Frescura ligera, claridad y calma. | Agua clara y aire puro; pureza de pensamiento. |
| `lightgoldenrodyellow` | amarillo dorado claro | jaune d'or clair | amarelo-dourado claro | giallo oro chiaro | `#FAFAD2` | Suavidad luminosa, optimismo tranquilo y calidez. | Luz dorada suave; iluminación gentil y bendiciones. |
| `lightgray` | gris claro | gris clair | cinza-claro | grigio chiaro | `#D3D3D3` | Neutralidad, calma, limpieza y espacio para respirar. | Transición entre lo visible y lo que no se ve; lo intermedio. |
| `lightgreen` | verde claro | vert clair | verde-claro | verde chiaro | `#90EE90` | Calma, frescura, renovación y bienestar. | Brote y esperanza; crecimiento tierno y sanación suave. |
| `lightgrey` | gris claro | gris clair | cinza-claro | grigio chiaro | `#D3D3D3` | Neutralidad, calma, limpieza y espacio para respirar. | Transición entre lo visible y lo que no se ve; lo intermedio. |
| `lightpink` | rosa claro | rose clair | rosa-claro | rosa chiaro | `#FFB6C1` | Ternura, dulzura, inocencia y afecto suave. | Amor delicado y pureza; comienzos románticos. |
| `lightsalmon` | salmón claro | saumon clair | salmão claro | salmone chiaro | `#FFA07A` | Calidez alegre, cercanía y energía amable. | Sol de la mañana; amabilidad y generosidad. |
| `lightseagreen` | verde mar claro | vert d'eau clair | verde-mar claro | verde mare chiaro | `#20B2AA` | Calma, equilibrio, frescura y comunicación. | Mar y sanación; equilibrio entre emoción y mente. |
| `lightskyblue` | azul cielo claro | bleu ciel clair | azul-celeste claro | azzurro cielo chiaro | `#87CEFA` | Ligereza, libertad, serenidad y optimismo. | Cielo abierto; inspiración, paz y aspiraciones elevadas. |
| `lightslategray` | gris pizarra claro | gris ardoise clair | cinza-ardósia claro | grigio ardesia chiaro | `#778899` | Neutralidad, calma reflexiva y sobriedad. | Transición entre luz y noche; reflexión y paciencia. |
| `lightslategrey` | gris pizarra claro | gris ardoise clair | cinza-ardósia claro | grigio ardesia chiaro | `#778899` | Neutralidad, calma reflexiva y sobriedad. | Transición entre luz y noche; reflexión y paciencia. |
| `lightsteelblue` | azul acero claro | bleu acier clair | azul-aço claro | blu acciaio chiaro | `#B0C4DE` | Calma, sobriedad, confianza y elegancia. | Metal y cielo; disciplina serena y claridad. |
| `lightyellow` | amarillo claro | jaune clair | amarelo-claro | giallo chiaro | `#FFFFE0` | Luz suave, optimismo, claridad y ligereza. | Luz del alba; comienzo, iluminación y conocimiento suave. |
| `lime` | lima | citron vert | lima | lime | `#00FF00` | Energía, frescura, vitalidad y actividad; a veces, señal de alerta. | Brote y crecimiento; renovación y fuerza vital. |
| `limegreen` | verde lima | vert citron | verde-lima | verde lime | `#32CD32` | Energía, juventud, optimismo y dinamismo. | Vida que brota; renovación, salud y primavera. |
| `linen` | lino | lin | linho | lino | `#FAF0E6` | Sencillez, calma, naturalidad y pureza suave. | Lino de los templos y de las vestiduras sagradas; pureza y humildad. |
| `magenta` | magenta | magenta | magenta | magenta | `#FF00FF` | Creatividad, audacia, intensidad y energía vibrante. | Transformación y espiritualidad; conexión entre lo material y lo sutil. |
| `maroon` | granate | bordeaux | bordô | bordeaux | `#800000` | Seriedad, poder, tradición, elegancia y pasión contenida. | Sangre y tierra; vitalidad, sacrificio y arraigo. |
| `mediumaquamarine` | aguamarina medio | aigue-marine moyenne | aquamarina média | acquamarina media | `#66CDAA` | Calma, frescura, armonía y bienestar. | Agua de sanación; equilibrio y fluidez emocional. |
| `mediumblue` | azul medio | bleu moyen | azul médio | blu medio | `#0000CD` | Confianza, serenidad, profundidad y seriedad. | Noche estrellada y verdad; introspección y guía. |
| `mediumorchid` | orquídea medio | orchidée moyenne | orquídea média | orchidea media | `#BA55D3` | Creatividad, sensibilidad, elegancia y misterio. | Espiritualidad y transformación; intuición y sabiduría oculta. |
| `mediumpurple` | púrpura medio | pourpre moyen | roxo médio | viola medio | `#9370DB` | Creatividad, sensibilidad, sofisticación y calma. | Realeza espiritual y visión; intuición y conexión con lo sutil. |
| `mediumseagreen` | verde mar medio | vert de mer moyen | verde-mar médio | verde mare medio | `#3CB371` | Calma, equilibrio, bienestar y crecimiento. | Mar y renovación; armonía, fertilidad y sanación. |
| `mediumslateblue` | azul pizarra medio | bleu ardoise moyen | azul-ardósia médio | blu ardesia medio | `#7B68EE` | Creatividad, imaginación, libertad y ligereza mental. | Inspiración y visión; conexión con lo celestial. |
| `mediumspringgreen` | verde primavera medio | vert printemps moyen | verde-primavera médio | verde primavera medio | `#00FA9A` | Frescura, vitalidad, optimismo y renovación. | Primavera y crecimiento; nueva vida y esperanza. |
| `mediumturquoise` | turquesa medio | turquoise moyen | turquesa média | turchese medio | `#48D1CC` | Calma, claridad, frescura y comunicación. | Agua y cielo; protección, sanación y pureza. |
| `mediumvioletred` | rojo violeta medio | rouge violet moyen | vermelho-violeta médio | rosso violetto medio | `#C71585` | Pasión, intensidad, ternura apasionada y ambición. | Deseo y vitalidad; amor intenso y fuerza emocional. |
| `midnightblue` | azul medianoche | bleu minuit | azul-meia-noite | blu mezzanotte | `#191970` | Profundidad, seriedad, misterio y confianza. | Noche, sueño y conocimiento oculto; introspección y lo desconocido. |
| `mintcream` | crema menta | crème menthe | creme de menta | crema di menta | `#F5FFFA` | Frescura suave, pureza, calma y limpieza. | Menta: purificación, renovación y claridad mental. |
| `mistyrose` | rosa brumoso | rose brumeuse | rosa-nebuloso | rosa nebbia | `#FFE4E1` | Delicadeza, nostalgia, suavidad y romanticismo. | Amanecer brumoso; sueño, recuerdo y lo que se desvanece. |
| `moccasin` | mocasín | mocassin | mocassim | mocassino | `#FFE4B5` | Calidez suave, sencillez, confort y amabilidad. | Tierra y camino; viaje, humildad y hogar. |
| `navajowhite` | blanco navajo | blanc navajo | branco navajo | bianco navajo | `#FFDEAD` | Calidez terrosa, sencillez y confort. | Arena y cosecha; sencillez y abundancia humilde. |
| `navy` | azul marino | bleu marine | azul-marinho | blu navy | `#000080` | Autoridad, confianza, seriedad y profesionalismo. | Mar profundo y noche; disciplina, sabiduría y lealtad. |
| `oldlace` | encaje antiguo | dentelle ancienne | renda antiga | pizzo antico | `#FDF5E6` | Nostalgia, delicadeza, calidez y tradición. | Memoria y ternura de lo antiguo; pureza envejecida y cuidado. |
| `olive` | oliva | olive | oliva | oliva | `#808000` | Paz, naturaleza, moderación y estabilidad; también cansancio. | Olivo: paz, sabiduría, victoria tranquila y fertilidad. |
| `olivedrab` | verde oliva | vert olive | verde-oliva | verde oliva | `#6B8E23` | Naturaleza, practicidad, estabilidad y sobriedad. | Campo y vida silvestre; perseverancia y arraigo. |
| `orange` | naranja | orange | laranja | arancione | `#FFA500` | Entusiasmo, creatividad, calidez, energía y sociabilidad. | En la tradición de los chakras, asociado al chakra sacro: vitalidad, creatividad y placer. |
| `orangered` | naranja rojizo | rouge orangé | vermelho-alaranjado | rosso arancio | `#FF4500` | Energía intensa, impulso, entusiasmo y urgencia. | Fuego y ardor; acción, valor y transformación. |
| `orchid` | orquídea | orchidée | orquídea | orchidea | `#DA70D6` | Elegancia, sensibilidad, romanticismo y creatividad. | Belleza y transformación; espiritualidad refinada. |
| `palegoldenrod` | vara de oro pálido | jonquille pâle | vara-de-ouro pálido | verga d'oro pallido | `#EEE8AA` | Suavidad, serenidad, calidez y optimismo tranquilo. | Luz de cosecha; abundancia gentil y bendición. |
| `palegreen` | verde pálido | vert pâle | verde-pálido | verde pallido | `#98FB98` | Calma, frescura, renovación y bienestar. | Brote tierno; esperanza, sanación y crecimiento suave. |
| `paleturquoise` | turquesa pálido | turquoise pâle | turquesa pálido | turchese pallido | `#AFEEEE` | Calma, ligereza, frescura y claridad. | Agua tranquila y cielo; pureza, sanación y paz. |
| `palevioletred` | rojo violeta pálido | rouge violet pâle | vermelho-violeta pálido | rosso violetto pallido | `#DB7093` | Ternura, afecto, sensibilidad y nostalgia. | Amor delicado; sensibilidad y vínculos afectivos. |
| `papayawhip` | crema de papaya | crème de papaye | creme de papaia | crema di papaya | `#FFEFD5` | Calidez suave, dulzura, confort y alegría tropical. | Fruta del sol y abundancia; hospitalidad y placer sencillo. |
| `peachpuff` | melocotón | pêche | pêssego | pesca | `#FFDAB9` | Calidez, ternura, cercanía y bienestar. | En China, el melocotón simboliza longevidad e inmortalidad; dulzura y buena fortuna. |
| `peru` | perú | pérou | peru | perù | `#CD853F` | Calidez terrosa, confianza, arraigo y estabilidad. | Tierra y cosecha; arraigo, trabajo y abundancia. |
| `pink` | rosa | rose | rosa | rosa | `#FFC0CB` | Ternura, dulzura, romanticismo, calma y afecto. | Amor, compasión y pureza; corazón abierto. |
| `plum` | ciruela | prune | ameixa | prugna | `#DDA0DD` | Sofisticación, delicadeza, creatividad y nostalgia. | Fruta de otoño y abundancia; dulzura madura, sabiduría y amor refinado. |
| `powderblue` | azul polvo | bleu poudre | azul-pó | blu polvere | `#B0E0E6` | Calma, delicadeza, limpieza y serenidad. | Cielo suave y agua clara; paz, pureza y sueños. |
| `purple` | púrpura | pourpre | roxo | porpora | `#800080` | Realeza, lujo, espiritualidad, creatividad y misterio. | Realeza y lo espiritual; en algunas tradiciones, asociado al chakra corona: conexión con lo divino. |
| `rebeccapurple` | púrpura Rebecca | pourpre Rebecca | roxo Rebecca | viola Rebecca | `#663399` | Creatividad, profundidad, misterio y elegancia contenida. | Espiritualidad profunda; sabiduría, meditación y el umbral de lo invisible. |
| `red` | rojo | rouge | vermelho | rosso | `#FF0000` | Pasión, energía, urgencia, peligro y acción. | En la tradición de los chakras, el primero (raíz) es rojo: arraigo, supervivencia y vitalidad. |
| `rosybrown` | marrón rosado | brun rosé | marrom-rosado | marrone rosato | `#BC8F8F` | Calidez suave, nostalgia, sensibilidad y sencillez. | Tierra y amor sereno; ternura de lo cotidiano. |
| `royalblue` | azul real | bleu royal | azul-real | blu reale | `#4169E1` | Autoridad, confianza, lealtad, prestigio y serenidad. | Realeza y lealtad; justicia, sabiduría y dignidad. |
| `saddlebrown` | marrón silla | brun selle | castanho-sela | marrone sella | `#8B4513` | Robustez, tradición, confort y arraigo. | Tierra fértil y trabajo; hogar, estabilidad y resistencia. |
| `salmon` | salmón | saumon | salmão | salmone | `#FA8072` | Calidez, cercanía, alegría y sensibilidad. | Sol y mar; vitalidad, afecto y transición. |
| `sandybrown` | marrón arena | brun sable | marrom-areia | marrone sabbia | `#F4A460` | Calidez, cordialidad, naturalidad y calma. | Desierto y arena; paciencia, tiempo y transformación. |
| `seagreen` | verde mar | vert marin | verde-mar | verde mare | `#2E8B57` | Calma, equilibrio, naturaleza y estabilidad. | Mar y vida; sanación, abundancia y renovación. |
| `seashell` | concha marina | coquillage | concha | conchiglia | `#FFF5EE` | Suavidad, pureza, delicadeza y calma. | Concha: símbolo de nacimiento y fertilidad (la Venus que emerge del mar) y de protección. |
| `sienna` | siena | terre de Sienne | siena | terra di Siena | `#A0522D` | Tierra, arraigo, calidez robusta y confiabilidad. | Tierra roja y fuerza ancestral; arraigo, fertilidad y raíces. |
| `silver` | plata | argent | prata | argento | `#C0C0C0` | Modernidad, sofisticación, neutralidad elegante y prestigio. | Luna y reflejo; en la alquimia, la plata corresponde a la Luna: intuición y cambio. |
| `skyblue` | azul cielo | bleu ciel | azul-celeste | azzurro cielo | `#87CEEB` | Calma, apertura, libertad y frescura. | Cielo y aire; inspiración, paz y elevación. |
| `slateblue` | azul pizarra | bleu ardoise | azul-ardósia | blu ardesia | `#6A5ACD` | Creatividad, imaginación, misterio y serenidad. | Visión y espiritualidad; intuición y conexión con lo elevado. |
| `slategray` | gris pizarra | gris ardoise | cinza-ardósia | grigio ardesia | `#708090` | Neutralidad, seriedad, sobriedad y calma. | Piedra y tiempo; firmeza, paciencia y lo estable. |
| `slategrey` | gris pizarra | gris ardoise | cinza-ardósia | grigio ardesia | `#708090` | Neutralidad, seriedad, sobriedad y calma. | Piedra y tiempo; firmeza, paciencia y lo estable. |
| `snow` | nieve | neige | neve | neve | `#FFFAFA` | Pureza, limpieza, calma y frialdad. | Pureza absoluta, silencio y renovación del invierno. |
| `springgreen` | verde primavera | vert printemps | verde-primavera | verde primavera | `#00FF7F` | Frescura, vitalidad, optimismo y renovación. | Primavera: brote, comienzo y esperanza. |
| `steelblue` | azul acero | bleu acier | azul-aço | blu acciaio | `#4682B4` | Confianza, disciplina, fuerza y estabilidad. | Metal y cielo; fortaleza, determinación y claridad. |
| `tan` | bronceado | hâle | bronzeado | abbronzato | `#D2B48C` | Calidez terrosa, sencillez, naturalidad y confort. | Tierra y sol; arraigo, cuidado y lo cotidiano. |
| `teal` | verde azulado | sarcelle | verde-azulado | teal | `#008080` | Equilibrio, calma, profesionalismo y creatividad. | Agua y cielo; equilibrio entre emoción y razón, sanación y paz. |
| `thistle` | cardo | chardon | cardo | cardo | `#D8BFD8` | Suavidad, nostalgia, delicadeza y calma. | Planta de protección; defensa y dulzura. |
| `tomato` | tomate | tomate | tomate | pomodoro | `#FF6347` | Energía, calidez, pasión, apetito y alerta. | Fruto del sol y de la vida; vitalidad, amor y generosidad. |
| `turquoise` | turquesa | turquoise | turquesa | turchese | `#40E0D0` | Calma, claridad, frescura y comunicación. | Piedra de protección y sanación; amuleto para el viajero y guía contra el mal. |
| `violet` | violeta | violet | violeta | violetto | `#EE82EE` | Creatividad, espiritualidad, misterio y sensibilidad. | En la tradición de los chakras, asociado al chakra corona: conexión con lo trascendente. |
| `wheat` | trigo | blé | trigo | grano | `#F5DEB3` | Sencillez, calidez, abundancia tranquila y confort. | Cosecha y pan; fertilidad, abundancia y gratitud. |
| `white` | blanco | blanc | branco | bianco | `#FFFFFF` | Pureza, limpieza, paz, inocencia y comienzos; en otras culturas, luto. | Pureza, luz y lo sagrado; renacimiento y unión con lo divino (la albedo alquímica). |
| `whitesmoke` | humo blanco | fumée blanche | fumaça-branca | fumo bianco | `#F5F5F5` | Neutralidad suave, limpieza, calma y sencillez. | Humo sutil: lo intangible, la transición y la plegaria que se eleva. |
| `yellow` | amarillo | jaune | amarelo | giallo | `#FFFF00` | Alegría, optimismo, energía, claridad mental y atención; también precaución. | En la tradición de los chakras, asociado al plexo solar: iluminación, voluntad y conocimiento. |
| `yellowgreen` | verde amarillo | jaune-vert | amarelo-esverdeado | giallo verde | `#9ACD32` | Frescura, vitalidad, juventud y calma. | Primavera y crecimiento; vida nueva y renovación. |
<!-- tabla:fin -->
