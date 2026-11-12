# Fuentes: origen y licencias

Las fuentes que se incrustan en los SVG son de terceros, con licencia libre **SIL Open Font License 1.1 (OFL)**, que
permite usarlas, incrustarlas y redistribuirlas (también las portadas generadas) siempre que se conserve el aviso de
copyright y la licencia. El texto completo de cada una está al lado.

| Familia en el proyecto | Fuente | Autoría | Origen y versión | Licencia |
|---|---|---|---|---|
| `serif` (clásica, tipográfica) | Source Serif 4 | Copyright 2014-2023 Adobe, con el nombre reservado «Source» | `@fontsource-variable/source-serif-4@5.3.1` (npm) | [`LICENCIA-source-serif-4.txt`](LICENCIA-source-serif-4.txt) |
| `sans` (sobria, cartel) | Inter | Copyright 2016 The Inter Project Authors | `@fontsource-variable/inter@5.3.0` (npm) | [`LICENCIA-inter.txt`](LICENCIA-inter.txt) |

## Qué se les hizo

`herramientas/preparar-fuentes.py` (ver su cabecera) toma las fuentes variables de partida y:

- fija el eje de peso en el peso que usan las plantillas (400 y 700 en Inter, 400 en redonda y cursiva en Source Serif 4);
- las parte en trozos por alfabeto (`basico`, `latin`, `latin-ext`, `vietnamita`, `griego`, `cirilico`…), cada uno en `woff2`;
- extrae el contorno de cada letra a `fuentes/trazos/*.json.gz`, para la salida con el texto a trazos.

Eso es una modificación en el sentido de la OFL. La OFL prohíbe llamar a una versión modificada con un nombre
reservado; aquí el nombre «Source Serif 4» del `@font-face` es solo una etiqueta CSS dentro de cada SVG, y las
fuentes no se publican como una familia instalable. Si algún día se redistribuyen los `woff2` como fuente aparte,
hay que renombrarlos.

## Al añadir una fuente

1. Que su licencia permita incrustarla y redistribuirla (OFL, Apache 2.0…). Una fuente «gratis» no basta.
2. Copiar el texto de la licencia a `fuentes/LICENCIA-<nombre>.txt`.
3. Añadir una fila a la tabla de arriba con el paquete y la versión de origen.

Una prueba (`pruebas/temas/licencias.php`) falla si alguna familia de `datos/fuentes.json` no tiene su archivo de
licencia o su fila aquí.
