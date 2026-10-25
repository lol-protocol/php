# Categorías gramaticales de las palabras de color

Generado por `generar-tabla.py` a partir de `categorias.csv`. No editar a mano.

Muchas palabras de color son también sustantivos, nombres propios o verbos (`coral`, `lime`, `tan`, `peru`, `violet`, `lima`). Para cada palabra de color de una sola palabra, las categorías gramaticales con que puede aparecer en un texto (las de [Universal Dependencies](https://universaldependencies.org/u/pos/): `ADJ` adjetivo, `NOUN` sustantivo, `PROPN` nombre propio y `VERB` verbo) y una frase de ejemplo por categoría.

Límites:

- Cubre dos idiomas: inglés (los nombres CSS de una sola palabra y los términos del léxico básico que no son CSS) y español (los lemas de una sola palabra del léxico básico). Las palabras compuestas (`azul marino`, `light blue`) no entran.
- Son las lecturas **posibles** de la palabra, no cuántas veces ocurre cada una, y cada frase es un ejemplo, no el único uso. No se distingue el sentido: `coral` sustantivo es el animal y el color.
- Hay casos discutibles: un sustantivo que modifica a otro (`a linen shirt`) puede etiquetarse `NOUN` o `ADJ` según el criterio; los ejemplos de `ADJ` en inglés usan la palabra como color en una posición clara (`painted the walls linen`).
- Nueve nombres CSS casi no se usan como color fuera de CSS (`cornsilk`, `firebrick`, `gainsboro`, `goldenrod`, `moccasin`, `peru`, `snow`, `thistle`, `tomato`): su ejemplo de `ADJ` habla de una página web (`The header is thistle`), porque en otro contexto no sonaría natural. Es uso de nombre CSS, no de lengua corriente.
- Está escrito por un asistente de IA y ningún hablante nativo lo ha revisado.

## Resumen

| Idioma | Palabras | `ADJ` | `NOUN` | `PROPN` | `VERB` | Con más de una categoría |
|---|---|---|---|---|---|---|
| Inglés (`en`) | 61 | 61 | 60 | 8 | 16 | 61 |
| Español (`es`) | 32 | 32 | 31 | 1 | 1 | 31 |

## Inglés (`en`)

| Concepto | Forma | Categoría | Ejemplo |
|---|---|---|---|
| `aqua` | aqua | `ADJ` | She painted the bathroom walls aqua. |
| `aqua` | aqua | `NOUN` | The aqua of the lagoon looked unreal. |
| `aquamarine` | aquamarine | `ADJ` | The aquamarine water was warm and clear. |
| `aquamarine` | aquamarine | `NOUN` | He gave her a ring set with an aquamarine. |
| `azure` | azure | `ADJ` | The azure sky stretched over the bay. |
| `azure` | azure | `NOUN` | The azure of the sea faded at dusk. |
| `azure` | azure | `PROPN` | The company moved its servers to Azure. |
| `beige` | beige | `ADJ` | He wore a beige coat. |
| `beige` | beige | `NOUN` | Beige is a safe color for a sofa. |
| `bisque` | bisque | `ADJ` | The kitchen walls were bisque and the floor was slate. |
| `bisque` | bisque | `NOUN` | We started dinner with a lobster bisque. |
| `black` | black | `ADJ` | She wore a black dress. |
| `black` | black | `NOUN` | Black suits him. |
| `black` | black | `VERB` | They blacked out the windows before the storm. |
| `blue` | blue | `ADJ` | The sky was blue. |
| `blue` | blue | `NOUN` | Blue is her favorite color. |
| `blue` | blue | `VERB` | The gunsmith blued the steel to stop it rusting. |
| `brown` | brown | `ADJ` | He has brown eyes. |
| `brown` | brown | `NOUN` | Brown goes well with green. |
| `brown` | brown | `VERB` | Brown the onions in a little butter. |
| `burlywood` | burlywood | `ADJ` | The table was painted burlywood. |
| `burlywood` | burlywood | `NOUN` | Burlywood is a pale tan in CSS. |
| `chartreuse` | chartreuse | `ADJ` | The chartreuse jacket stood out in the crowd. |
| `chartreuse` | chartreuse | `NOUN` | He ordered a glass of chartreuse after dinner. |
| `chocolate` | chocolate | `ADJ` | The new couch is chocolate. |
| `chocolate` | chocolate | `NOUN` | I would like some chocolate. |
| `coral` | coral | `ADJ` | She painted her nails coral. |
| `coral` | coral | `NOUN` | The reef is made of living coral. |
| `cornsilk` | cornsilk | `ADJ` | The page background is cornsilk. |
| `cornsilk` | cornsilk | `NOUN` | The cornsilk stuck to my fingers. |
| `crimson` | crimson | `ADJ` | A crimson sunset covered the hills. |
| `crimson` | crimson | `NOUN` | The crimson of the flag had faded. |
| `crimson` | crimson | `VERB` | Her cheeks crimsoned with embarrassment. |
| `cyan` | cyan | `ADJ` | The printer is out of cyan ink. |
| `cyan` | cyan | `NOUN` | Cyan and magenta mix to make blue. |
| `firebrick` | firebrick | `ADJ` | The button turned firebrick when hovered. |
| `firebrick` | firebrick | `NOUN` | The oven is lined with firebrick. |
| `fuchsia` | fuchsia | `ADJ` | She wore a fuchsia scarf. |
| `fuchsia` | fuchsia | `NOUN` | The fuchsia bloomed on the porch. |
| `gainsboro` | gainsboro | `ADJ` | The panel background is gainsboro. |
| `gainsboro` | gainsboro | `NOUN` | Gainsboro is the lightest of the grays here. |
| `gold` | gold | `ADJ` | The trim is gold. |
| `gold` | gold | `NOUN` | They found gold in the river. |
| `goldenrod` | goldenrod | `ADJ` | The header is goldenrod. |
| `goldenrod` | goldenrod | `NOUN` | Goldenrod grows along the roadside in autumn. |
| `gray` | gray | `ADJ` | The sky turned gray. |
| `gray` | gray | `NOUN` | Gray is the color of ash. |
| `gray` | gray | `VERB` | His hair grayed early. |
| `green` | green | `ADJ` | The grass is green. |
| `green` | green | `NOUN` | Green is the color of spring. |
| `green` | green | `VERB` | The hills greened after the first rains. |
| `grey` | grey | `ADJ` | The clouds were grey. |
| `grey` | grey | `NOUN` | Grey is the color of the pavement. |
| `grey` | grey | `VERB` | He greyed prematurely. |
| `honeydew` | honeydew | `ADJ` | The hallway walls were painted honeydew. |
| `honeydew` | honeydew | `NOUN` | She cut a honeydew for breakfast. |
| `indigo` | indigo | `ADJ` | The indigo night was full of stars. |
| `indigo` | indigo | `NOUN` | Indigo was once an expensive dye. |
| `ivory` | ivory | `ADJ` | She chose an ivory gown. |
| `ivory` | ivory | `NOUN` | Poachers hunt elephants for ivory. |
| `khaki` | khaki | `ADJ` | The soldiers wore khaki uniforms. |
| `khaki` | khaki | `NOUN` | He wore khakis to the office. |
| `lavender` | lavender | `ADJ` | The lavender curtains moved in the breeze. |
| `lavender` | lavender | `NOUN` | Lavender grows well in dry soil. |
| `lime` | lime | `ADJ` | The lime walls glowed in the sun. |
| `lime` | lime | `NOUN` | Add a slice of lime. |
| `lime` | lime | `VERB` | Farmers lime acidic soil to improve it. |
| `linen` | linen | `ADJ` | The walls were painted linen. |
| `linen` | linen | `NOUN` | The hotel changes the linen daily. |
| `magenta` | magenta | `ADJ` | She chose a magenta ribbon. |
| `magenta` | magenta | `NOUN` | Magenta is one of the printing inks. |
| `maroon` | maroon | `ADJ` | The team wears maroon jerseys. |
| `maroon` | maroon | `NOUN` | Maroon looks good with gold. |
| `maroon` | maroon | `VERB` | Pirates marooned him on a desert island. |
| `moccasin` | moccasin | `ADJ` | The text color is moccasin. |
| `moccasin` | moccasin | `NOUN` | He lost one moccasin in the creek. |
| `navy` | navy | `ADJ` | He wore a navy blazer. |
| `navy` | navy | `NOUN` | Navy suits her better than black. |
| `olive` | olive | `ADJ` | The jacket was olive. |
| `olive` | olive | `NOUN` | He ate an olive with his drink. |
| `olive` | olive | `PROPN` | Olive moved to Dublin last year. |
| `orange` | orange | `ADJ` | The orange sunset lit the sky. |
| `orange` | orange | `NOUN` | She peeled an orange. |
| `orange` | orange | `PROPN` | We switched to Orange for our mobile plan. |
| `orchid` | orchid | `ADJ` | The walls were orchid. |
| `orchid` | orchid | `NOUN` | An orchid bloomed on the windowsill. |
| `peru` | peru | `ADJ` | The border is peru. |
| `peru` | peru | `PROPN` | She was born in Peru. |
| `pink` | pink | `ADJ` | She wore a pink dress. |
| `pink` | pink | `NOUN` | Pink looks good on her. |
| `pink` | pink | `VERB` | The old engine pinked under load. |
| `plum` | plum | `ADJ` | The walls were plum. |
| `plum` | plum | `NOUN` | He ate a ripe plum. |
| `purple` | purple | `ADJ` | The purple flowers opened at dawn. |
| `purple` | purple | `NOUN` | Purple is the color of royalty. |
| `purple` | purple | `VERB` | The sky purpled at dusk. |
| `red` | red | `ADJ` | The red door was open. |
| `red` | red | `NOUN` | Red is a warm color. |
| `salmon` | salmon | `ADJ` | She painted the room salmon. |
| `salmon` | salmon | `NOUN` | We grilled salmon for dinner. |
| `seashell` | seashell | `ADJ` | The bedroom walls were seashell. |
| `seashell` | seashell | `NOUN` | He picked up a seashell on the beach. |
| `sienna` | sienna | `ADJ` | The sienna earth glowed in the evening. |
| `sienna` | sienna | `NOUN` | Burnt sienna is a warm brown pigment. |
| `sienna` | sienna | `PROPN` | Sienna came to the meeting late. |
| `silver` | silver | `ADJ` | The car is silver. |
| `silver` | silver | `NOUN` | The thieves stole the family silver. |
| `silver` | silver | `VERB` | They silvered the glass to make a mirror. |
| `snow` | snow | `ADJ` | The page background is snow. |
| `snow` | snow | `NOUN` | Snow covered the roofs. |
| `snow` | snow | `VERB` | It will snow tonight. |
| `tan` | tan | `ADJ` | He wore tan boots. |
| `tan` | tan | `NOUN` | Tan is a neutral color. |
| `tan` | tan | `VERB` | She tans easily in the sun. |
| `teal` | teal | `ADJ` | The teal sofa brightened the room. |
| `teal` | teal | `NOUN` | Teal is a blue-green color. |
| `thistle` | thistle | `ADJ` | The header is thistle. |
| `thistle` | thistle | `NOUN` | A thistle grew by the gate. |
| `tomato` | tomato | `ADJ` | The button turned tomato when clicked. |
| `tomato` | tomato | `NOUN` | She sliced a tomato for the salad. |
| `turquoise` | turquoise | `ADJ` | The turquoise sea was calm. |
| `turquoise` | turquoise | `NOUN` | Turquoise is a blue-green stone. |
| `violet` | violet | `ADJ` | The violet curtains were closed. |
| `violet` | violet | `NOUN` | A violet grew by the path. |
| `violet` | violet | `PROPN` | Violet is waiting outside. |
| `wheat` | wheat | `ADJ` | The walls were painted wheat. |
| `wheat` | wheat | `NOUN` | The farmers harvested the wheat. |
| `white` | white | `ADJ` | She wore a white dress. |
| `white` | white | `NOUN` | White is the color of snow. |
| `yellow` | yellow | `ADJ` | The yellow bus stopped. |
| `yellow` | yellow | `NOUN` | Yellow is a cheerful color. |
| `yellow` | yellow | `VERB` | The old pages yellowed with age. |
| `lilac` | lilac | `ADJ` | She wore a lilac dress. |
| `lilac` | lilac | `NOUN` | The lilac bloomed in May. |
| `burgundy` | burgundy | `ADJ` | She wore a burgundy coat. |
| `burgundy` | burgundy | `NOUN` | He ordered a bottle of burgundy. |
| `burgundy` | burgundy | `PROPN` | They toured the vineyards of Burgundy. |
| `cream` | cream | `ADJ` | He wore a cream suit. |
| `cream` | cream | `NOUN` | Whip the cream until it thickens. |
| `cream` | cream | `VERB` | Cream the butter and sugar together. |
| `scarlet` | scarlet | `ADJ` | A scarlet ribbon hung from the door. |
| `scarlet` | scarlet | `NOUN` | Scarlet is brighter than crimson. |
| `amber` | amber | `ADJ` | The amber light flashed. |
| `amber` | amber | `NOUN` | The insect was trapped in amber. |
| `amber` | amber | `PROPN` | Amber lives next door. |

## Español (`es`)

| Concepto | Forma | Categoría | Ejemplo |
|---|---|---|---|
| `beige` | beige | `ADJ` | Eligió una pared beige. |
| `beige` | beige | `NOUN` | El beige combina con todo. |
| `black` | negro | `ADJ` | Compró un abrigo negro. |
| `black` | negro | `NOUN` | El negro le sienta bien. |
| `blue` | azul | `ADJ` | El mar estaba azul. |
| `blue` | azul | `NOUN` | El azul del cielo era intenso. |
| `brown` | marrón | `ADJ` | Compró un sofá marrón. |
| `brown` | marrón | `NOUN` | El marrón combina con el verde. |
| `coral` | coral | `ADJ` | Llevaba un vestido coral. |
| `coral` | coral | `NOUN` | El coral se muere por el calor. |
| `crimson` | carmesí | `ADJ` | Un atardecer carmesí cubrió el mar. |
| `crimson` | carmesí | `NOUN` | El carmesí de la bandera estaba desteñido. |
| `cyan` | cian | `ADJ` | La tinta cian se acabó. |
| `cyan` | cian | `NOUN` | El cian es uno de los colores de impresión. |
| `gold` | dorado | `ADJ` | El marco dorado brillaba. |
| `gold` | dorado | `NOUN` | El dorado de la corona deslumbraba. |
| `gray` | gris | `ADJ` | El cielo está gris. |
| `gray` | gris | `NOUN` | El gris domina la ciudad en invierno. |
| `green` | verde | `ADJ` | El césped está verde. |
| `green` | verde | `NOUN` | El verde es su color favorito. |
| `indigo` | índigo | `ADJ` | El cielo índigo se llenó de estrellas. |
| `indigo` | índigo | `NOUN` | El índigo es un tinte natural. |
| `khaki` | caqui | `ADJ` | Llevaba un pantalón caqui. |
| `khaki` | caqui | `NOUN` | Comió un caqui maduro. |
| `lavender` | lavanda | `ADJ` | Eligió un tono lavanda. |
| `lavender` | lavanda | `NOUN` | La lavanda huele muy bien. |
| `lime` | lima | `ADJ` | Llevaba una camiseta lima. |
| `lime` | lima | `NOUN` | Añade zumo de lima. |
| `lime` | lima | `VERB` | Ella lima las uñas antes de pintarlas. |
| `magenta` | magenta | `ADJ` | Usó un tono magenta. |
| `magenta` | magenta | `NOUN` | El magenta es un color de impresión. |
| `maroon` | granate | `ADJ` | Llevaba un vestido granate. |
| `maroon` | granate | `NOUN` | El granate es una piedra preciosa. |
| `olive` | oliva | `ADJ` | Compró una chaqueta oliva. |
| `olive` | oliva | `NOUN` | Aliñó la ensalada con aceite de oliva. |
| `orange` | naranja | `ADJ` | Pintó la pared naranja. |
| `orange` | naranja | `NOUN` | Peló una naranja. |
| `pink` | rosa | `ADJ` | Llevaba un vestido rosa. |
| `pink` | rosa | `NOUN` | Le regaló una rosa roja. |
| `purple` | morado | `ADJ` | Se puso un jersey morado. |
| `purple` | morado | `NOUN` | El morado es el color de la cuaresma. |
| `red` | rojo | `ADJ` | Llevaba un vestido rojo. |
| `red` | rojo | `NOUN` | El rojo es un color cálido. |
| `salmon` | salmón | `ADJ` | Llevaba una blusa salmón. |
| `salmon` | salmón | `NOUN` | Cenamos salmón a la plancha. |
| `silver` | plateado | `ADJ` | Compró un coche plateado. |
| `turquoise` | turquesa | `ADJ` | Compró un collar turquesa. |
| `turquoise` | turquesa | `NOUN` | El turquesa es una piedra azulada. |
| `violet` | violeta | `ADJ` | Llevaba una bufanda violeta. |
| `violet` | violeta | `NOUN` | Cogió una violeta del jardín. |
| `white` | blanco | `ADJ` | Tiene el pelo blanco. |
| `white` | blanco | `NOUN` | El blanco de la pared brillaba con el sol. |
| `yellow` | amarillo | `ADJ` | El autobús amarillo se detuvo. |
| `yellow` | amarillo | `NOUN` | El amarillo alegra la habitación. |
| `lilac` | lila | `ADJ` | Se puso una blusa lila. |
| `lilac` | lila | `NOUN` | La lila perfuma el jardín. |
| `burgundy` | burdeos | `ADJ` | Compró un sofá burdeos. |
| `burgundy` | burdeos | `NOUN` | Bebimos un burdeos excelente. |
| `burgundy` | burdeos | `PROPN` | Viajaron a Burdeos en tren. |
| `cream` | crema | `ADJ` | Llevaba un abrigo crema. |
| `cream` | crema | `NOUN` | Se puso crema en las manos. |
| `scarlet` | escarlata | `ADJ` | Un lazo escarlata colgaba de la puerta. |
| `scarlet` | escarlata | `NOUN` | El escarlata destacaba sobre el fondo. |
| `amber` | ámbar | `ADJ` | El semáforo ámbar parpadeaba. |
| `amber` | ámbar | `NOUN` | El insecto quedó atrapado en el ámbar. |
