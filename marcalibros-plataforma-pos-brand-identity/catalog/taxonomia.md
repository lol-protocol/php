# Taxonomía de marcalibros

**Estado: confirmada.** Los 10 movimientos, sus autores y los 10 tipos de texto están aprobados por la persona que encarga el proyecto. Los tipos de texto salieron de su pedido ("manuales, libros sagrados, leyes, etc."). Cualquier cambio de categorías se consulta antes.

El catálogo tiene **20 categorías en dos familias** y **50 marcalibros por categoría: 1000 en total**. Cada categoría tiene 4 motivos visuales, así que cada motivo aparece 12 o 13 veces, con colores, posiciones y variaciones distintas (no hay dos SVG idénticos: el generador lo comprueba). El número por categoría se cambia con `--por-categoria` en el generador.

## Movimientos literarios

| Movimiento       | Motivos visuales                                           | Autores de referencia                                   |
|------------------|------------------------------------------------------------|---------------------------------------------------------|
| Romanticismo     | Amanecer, árbol solitario, nubes, acantilado y mar         | Lord Byron, John Keats, Gustavo Adolfo Bécquer          |
| Realismo         | Ciudad, puente, farolas, chimeneas                         | Gustave Flaubert, Honoré de Balzac, Benito Pérez Galdós |
| Naturalismo      | Bosque, juncos, trigo, río                                 | Émile Zola, Guy de Maupassant, Horacio Quiroga          |
| Modernismo       | Ondas, ondas concéntricas, art nouveau, mosaico            | Rubén Darío, Virginia Woolf, James Joyce                |
| Vanguardias      | Geometría, constructivista, caligrama, collage             | Guillaume Apollinaire, Vicente Huidobro, Tristan Tzara  |
| Gótico           | Arcos ojivales, rosetón, torres, cementerio                | Mary Shelley, Edgar Allan Poe, Bram Stoker              |
| Barroco          | Ornamento, volutas, drapeado, perlas                       | Luis de Góngora, Francisco de Quevedo, Pedro Calderón de la Barca |
| Clasicismo       | Columnas, frontón, laurel, meandro                         | Jean Racine, Molière, Pierre Corneille                  |
| Simbolismo       | Luna, reflejo lunar, constelaciones, lirio                 | Stéphane Mallarmé, Paul Verlaine, Arthur Rimbaud        |
| Realismo mágico  | Espiral, mariposas, flores cayendo, selva                  | Gabriel García Márquez, Juan Rulfo, Alejo Carpentier    |

## Tipos de texto

Pensados para Plataforma POS: el etiquetado gramatical se hace sobre textos de muy distintos registros, y el tipo de texto es tan útil para ubicarse como el movimiento. No llevan autores.

| Tipo de texto        | Motivos visuales                                            | Idea                                                        |
|----------------------|-------------------------------------------------------------|-------------------------------------------------------------|
| Manuales             | Engranajes, plano técnico, regla, circuito                  | Instrucciones, guías técnicas, reglamentos de uso           |
| Libros sagrados      | Rayos de luz, mandala, rollos, velas                        | Textos religiosos y espirituales, sin símbolos de una religión concreta |
| Leyes y reglamentos  | Balanza, sellos, artículos, mazo                            | Constituciones, códigos, contratos, normas                  |
| Cartas y epistolares | Sobres, estampillas, lacre, plumas                          | Cartas, correspondencia, telegramas                         |
| Diarios y memorias   | Calendario, fases lunares, ventana con lluvia, herbario     | Diarios íntimos, memorias, bitácoras                        |
| Tratados científicos | Órbitas, hexágonos, gráfica de datos, árbol filogenético    | Ensayos y artículos de ciencia                              |
| Recetas y cocina     | Especias, platos, frutas, ollas y vapor                     | Recetarios y textos gastronómicos                           |
| Filosofía y ensayo   | Laberinto, esfera armilar, puertas, red de ideas            | Tratados filosóficos, ensayos, pensamiento                  |
| Teatro               | Cortinas, máscaras, focos, platea                           | Obras dramáticas y guiones                                  |
| Viajes y crónicas    | Brújula, mapa y ruta, veleros, cordillera                   | Relatos de viaje, crónicas, diarios de expedición           |

## Dónde vive cada dato

- La categoría, su familia y el motivo de cada marcalibro están en `catalog/manifest.json` (`categoria`, `categoria_slug`, `familia`, `motivo`). La lista de categorías y los nombres de los motivos están en `categorias` y `motivos`.
- Los motivos se definen en `generator/motivos.py` (movimientos) y `generator/motivos_texto.py` (tipos de texto). Los identificadores son `mb-{categoría}-{NN}`, por ejemplo `mb-leyes-07`.

## Reglas de uso de los autores

- Solo los movimientos literarios tienen autores. Aparecen como metadatos e inspiración en `catalog/manifest.json` (`autores_inspiracion`).
- Ningún marcalibro muestra el nombre del autor, citas, portadas de libros ni retratos.
- Si se agrega un autor vivo, se usa solo su nombre, sin imagen ni fragmentos de obra.
- Los tipos de texto no citan obras concretas.

## Notas para revisar

- **"Modernismo"** se usa aquí en sentido amplio. Incluye al modernismo hispanoamericano (Darío) y al modernismo anglosajón (Woolf, Joyce). Puede separarse en dos movimientos si prefieres más precisión.
- **Realismo mágico** se queda como movimiento propio. Es una etiqueta que algunos críticos aplican solo a parte de la obra de estos autores, y los motivos (espiral, mariposas, flores cayendo, selva) son una interpretación visual.
- **Barroco** mezcla el culteranismo de Góngora con el conceptismo de Quevedo. Ambos entran en la categoría, pero son escuelas distintas.
- **Libros sagrados** evita a propósito cruces, medias lunas, estrellas de David y símbolos parecidos: los motivos son luz, mandala, rollos y velas. Si prefieres símbolos de una tradición concreta, hay que decidirlo con cuidado y tradición por tradición.
- **Tipos de texto que podrían sumarse:** cuentos y leyendas, poesía, historia, periodismo, diccionarios y enciclopedias, discursos. Cada uno cuesta 4 motivos nuevos y 50 marcalibros.
- **Motivos por categoría:** son una interpretación visual propia, no una norma. Si alguno no representa bien a su categoría, se cambia en el generador sin tocar el resto.
