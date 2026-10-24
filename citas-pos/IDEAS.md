# Ideas

Cada idea lleva su justificación, su **utilidad** (qué aporta a Contrastocolor), su **esfuerzo** y de qué depende. Las dos que eligió la persona son **A4** y **A6**; el resto son propuestas.

## Prioridad según las decisiones

Con **lectores y público general** como audiencia, **sitio y correo** como canal y un tono **cercano y lúdico** (ver `README.md`):

- **Suben:** A4 y A6 (elegidas), la **1** (la paleta fija, porque en un sitio y un correo el código se aprende por repetición), la **3** (series por categoría, buenas para una sección del sitio), la **7** (la cita de la semana encaja con un correo periódico) y la **9** (la línea de cita en el pie del correo es ahora un canal elegido). También la **2** y la **5** (el correo y el sitio deben ser legibles con daltonismo y con lector de pantalla).
- **Bajan:** la **4** (hojas para docentes: el impreso no es canal y los docentes no son la audiencia principal; queda para cuando se amplíe), la **6** (cita por país: depende de tener antes el sitio funcionando por país) y la **8** (estadística de autores: necesita un etiquetador fiable que hoy no existe).
- **Ya no aplica:** la tarjeta cuadrada de 1080×1080 de A5, pensada para redes. Las piezas se generan para el sitio y el correo.
- **Correo:** la cita va como **texto HTML con el color en línea**, no solo como imagen, para que se lea con las imágenes bloqueadas y con lector de pantalla.

## Las dos elegidas

### A4. 🧠 Gramática como juego

Citas sobre la forma de las palabras, y citas cualesquiera con cada palabra en el color de su categoría. Es la idea que explica el producto sin explicarlo.

Variantes, de menor a mayor esfuerzo:

- **Adivina la categoría.** La cita en gris; al tocar cada palabra se revela su categoría y su color. Sirve para carruseles de redes.
- **Leyenda en el idioma de la cita.** Cada tarjeta trae su leyenda con los nombres de las categorías en ese idioma. Conecta con los nombres de color en 30 idiomas de `Listado-colores/`.
- **Palabras que cambian de color dentro de una cita.** Elegir un término con categorías distintas según el contexto (en inglés, *light*: sustantivo, adjetivo o verbo). Es la pieza que mejor muestra qué resuelve un etiquetador.
- **Mapa completo.** Una frase corta en la que aparecen casi todas las categorías, como ejemplo de «mapa de la oración».

**Utilidad:** alta. **Esfuerzo:** bajo a medio. **Depende de:** etiquetas manuales o de un etiquetador externo (ver `README.md`, regla 3).

### A6. 🌐 Original primero

Se publica la cita en su idioma original y debajo la traducción, y solo en los idiomas que ya tengan revisión nativa. Evita traducir de más y evita presentar un borrador como definitivo.

Variantes:

- **Carrusel de origen.** Diapositiva 1: la cita original coloreada. Diapositiva 2: la traducción coloreada. Diapositiva 3: la traducción sin color, para quien solo quiere leer.
- **Alineación por color.** Las palabras con el mismo significado en original y traducción comparten un tono. Hay que alinear a mano, así que solo con citas cortas.
- **Escrituras sin espacios** (chino, japonés, tailandés). La pieza muestra dónde se parte una palabra, que es un reto real del etiquetado. Va en una oleada posterior (ver `CRITERIOS.md`) y solo cuando la segmentación esté comprobada.
- **Tarjeta para el sitio.** El original y debajo la cita sin color: ahí el color es decoración y manda el texto.

**Utilidad:** alta. **Esfuerzo:** medio. **Depende de:** revisión nativa por idioma.

## Otras ideas

1. 🎨 **Paleta fija de 17 categorías.** Una sola leyenda en todas las piezas. Con repetición, la audiencia aprende el código, y se vuelve un activo de marca. Hay precedente pedagógico en los símbolos gramaticales de colores de la tradición Montessori. **Utilidad:** alta. **Esfuerzo:** bajo. **Depende de:** decidir la paleta (ver `PLAN.md`, fase 2).
2. 🔁 **El mismo pensamiento en cuatro lenguas.** Una idea en `es`, `en`, `de` y `ru`, coloreada. Se ve que el alemán manda el verbo al final y que el ruso no tiene artículos. Muestra por qué hace falta un etiquetador por lengua. **Utilidad:** alta. **Esfuerzo:** medio. **Depende de:** traducciones propias revisadas.
3. 🎯 **Una serie por categoría.** «Solo los adjetivos de Wilde»: la cita en gris y una sola categoría en color. Da 17 episodios con estructura propia y evita el problema de distinguir 17 colores a la vez. **Utilidad:** alta. **Esfuerzo:** bajo.
4. 🎓 **Hojas para docentes.** Citas breves para colorear a mano en clase, imprimibles. Quien enseña gramática es público natural de un etiquetador. **Utilidad:** alta. **Esfuerzo:** medio. **Depende de:** conocer la audiencia (decisión abierta 3 en `PLAN.md`).
5. ♿ **Variante apta para daltonismo.** La misma paleta con patrones o etiquetas visibles. Evita excluir a un público real y es un argumento de marca. **Utilidad:** media. **Esfuerzo:** medio.
6. 🌍 **Cita local por país.** Cada país con una cita en su lengua, usando los índices de `Listado-paises/` para decidir dónde empezar. Aprovecha datos ya hechos. **Utilidad:** media. **Esfuerzo:** medio.
7. 📅 **Cita de la semana, generada desde el JSON.** Un script determinista, como `marketing/render.php`. Convierte el corpus en contenido recurrente sin trabajo manual cada semana. **Utilidad:** media. **Esfuerzo:** bajo. **Depende de:** la fase 4 de `PLAN.md`.
8. 📊 **¿Cuántos adjetivos usa un autor?** Comparar dos textos de dominio público (por ejemplo, Cervantes frente a Dickinson). Muestra capacidad analítica real. **Utilidad:** alta. **Esfuerzo:** alto. **Depende de:** un etiquetador que funcione y de **verificar su precisión**; no antes de eso.
9. ✉️ **Línea de cita en el pie del correo.** De 4 a 8 palabras, rotativa. No cuesta un canal nuevo. **Utilidad:** baja. **Esfuerzo:** bajo.

## Ideas de la primera tanda que se conservan

- **Una cita, un color (A1).** Cada cita se asocia a un color de `Listado-colores/colores.csv`. Sirve para tarjetas con el nombre del color en el idioma de la cita. Cuidado con la regla 4 del README: no se presenta ningún significado de color como un efecto comprobado.
- **Palabras que cambian de lengua (A2)** y **Color y percepción (A3):** citas sobre traducir, nombrar y el color. Se usan como tema de selección en `CRITERIOS.md`.
- **Formatos (A5):** línea para el pie de correo y cita rotativa para el sitio, siempre con idioma y fuente a la vista. La tarjeta cuadrada de 1080×1080 se descartó: no hay redes sociales por ahora.
