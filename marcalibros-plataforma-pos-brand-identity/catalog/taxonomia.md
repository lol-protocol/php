# Taxonomía de marcalibros

**Estado: borrador pendiente de confirmación.** Los movimientos y autores de esta lista aún no están aprobados.

Cada movimiento tiene 4 motivos visuales. Con 10 variantes por movimiento (100 marcalibros) cada motivo aparece 2 o 3 veces, con colores y posiciones distintos. El número de variantes se cambia con `--por-movimiento` en el generador.

## Movimientos

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

El motivo de cada marcalibro queda registrado en el campo `motivo` de `catalog/manifest.json`. Los motivos se definen en `generator/motivos.py`.

## Reglas de uso de los autores

- Los nombres aparecen solo como metadatos e inspiración en `catalog/manifest.json`.
- Ningún marcalibro muestra el nombre del autor, citas, portadas de libros ni retratos.
- Si se agrega un autor vivo, se usa solo su nombre, sin imagen ni fragmentos de obra.

## Notas para revisar

- **"Modernismo"** se usa aquí en sentido amplio. Incluye al modernismo hispanoamericano (Darío) y al modernismo anglosajón (Woolf, Joyce). Puede separarse en dos movimientos si prefieres más precisión.
- **Realismo mágico** es una etiqueta que algunos críticos aplican solo a parte de la obra de estos autores. Conviene confirmarla.
- **Barroco** mezcla el culteranismo de Góngora con el conceptismo de Quevedo. Ambos entran en la categoría, pero son escuelas distintas.
- **Motivos por movimiento:** son una interpretación visual propia, no una norma. Si algún motivo no representa bien a su movimiento, se cambia en `generator/motivos.py` sin tocar el resto.
