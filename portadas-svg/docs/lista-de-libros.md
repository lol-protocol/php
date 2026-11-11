# Lista de libros

Para muchos libros, una fila de un CSV por libro, en UTF-8 (en Excel o LibreOffice: «CSV UTF-8»). El separador puede ser coma, punto y coma o tabulador. La primera fila son los encabezados, que no distinguen mayúsculas ni tildes (`Título` y `titulo` son lo mismo) y pueden ir en cualquier orden. Una celda vacía es no dar el dato.

```csv
titulo,subtitulo,autor,epoca,tradicion,categoria,paginas,cita,editorial,coleccion
Meditaciones,,Marco Aurelio,siglo II,romana,filosofia,224,"Lo que no conviene a la colmena, no conviene a la abeja.",Edición de ejemplo,Clásicos
Tao Te King,,Lao Tsé,siglo IV a. C.,china,libros-sagrados,96,,Edición de ejemplo,Clásicos
```

| Columna | Qué es |
|---|---|
| `titulo`, `autor` | Obligatorias: tienen que estar en los encabezados y llenas en cada fila. |
| `paginas` | Número entero. Obligatoria salvo que la fila dé `lomo_mm`. |
| `id` | Nombre de la carpeta del libro: minúsculas sin tilde, cifras y guiones. Si falta, sale del autor y el título (`marco-aurelio-meditaciones`); si el título no tiene letras latinas (chino, árabe…), se le añade un sufijo sacado de él (`lao-tse-3f9a1c`), así que dos libros del mismo autor no chocan. |
| `subtitulo`, `traductor`, `editorial`, `coleccion`, `isbn` | Como en el JSON. |
| `epoca`, `tradicion`, `categoria`, `cita` | Ver [Clasificación y ficha](clasificacion-y-ficha.md). |
| `contratapa`, `solapa_portada`, `solapa_contratapa` | Texto a mano; manda sobre la ficha. Un salto de línea doble separa párrafos. |
| `plantilla`, `idioma`, `lomo_direccion`, `solapas_mm`, `lomo_mm`, `sangrado_mm`, `grosor_hoja_mm` | Como en el JSON (los mm admiten punto o coma decimal). |
| `formato` | Un formato con nombre (`novela`, `bolsillo`, `a5`, `a6`, `ebook`). No se mezcla con `ancho_mm` ni `alto_mm`. |
| `tema`, `volumen` | `claro` u `oscuro`, y el número de volumen de la serie. Ver [Plantillas](plantillas.md). |
| `imagen`, `logo` | Ruta de la ilustración y del logo, relativa a la carpeta del CSV. Ver [Plantillas](plantillas.md). |
| `ancho_mm`, `alto_mm` | El formato de corte (`formato.ancho_mm` y `formato.alto_mm` del JSON). |
| `degradado`, `degradado_tipo`, `degradado_angulo` | El degradado del fondo: `auto` o de 2 a 6 colores separados por espacios (`#f3ead7 #c9a96e`); `lineal` o `radial`; y los grados. Con solo el tipo o el ángulo, los colores son `auto`. |
| `fondo`, `texto`, `acento` | Colores `#rrggbb`. |

Cada fila pasa por los mismos controles que un JSON. Si alguna falla, el error nombra la fila (como se ve en la hoja de cálculo) y el título, se dan todas juntas y no se escribe ningún libro, para corregir la lista de una vez:

```
lista.csv: 2 filas con errores:
  fila 4 («Mala tradición»): 'tradicion': la tradición «marciana» no existe; usa una de: griega, romana, …
  fila 9 («Sin autor»): 'titulo' y 'autor' son obligatorios.
```

Una columna que no se conoce (un `tradicioon` mal escrito) también se rechaza, en vez de ignorarla en silencio.

Si prefieres seguir con las filas buenas, `--omitir-errores` salta las malas, avisa de cada una (con su fila y su error) y escribe el resto:

```bash
php bin/generar.php --lista=libros.csv --omitir-errores --salida=salida
```

Con 300 libros, el generador tarda un par de segundos y escribe unos 60 MB (cada SVG lleva las partes de fuente que usa). Si un título o un texto lleva letras que las fuentes no cubren, el generador lo avisa por libro y sigue.
