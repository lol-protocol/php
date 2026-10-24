# Plan

Cada fase va en su propio PR, en borrador, y se fusiona solo cuando la persona lo decide.

## Decisiones tomadas

Tomadas el 24 de octubre de 2026. Están resumidas también en [`README.md`](README.md).

| Tema | Decisión | Consecuencia para el plan |
|---|---|---|
| Audiencia | Lectores y público general | Hay que explicar qué es el etiquetado dentro de la pieza; las hojas para docentes bajan de prioridad. |
| Canal | Sitio de Contrastocolor y correo | Sin tarjeta de redes. El correo lleva texto HTML con color en línea, no solo imagen. |
| Tono | Cercano y lúdico | Juego en el acompañamiento, nunca en la cita ni en los hechos. |
| Idiomas | Los 30 de `Listado-colores/`, con códigos de 3 letras | Ver el cuello de botella en «Riesgos». |
| Etiquetas | Manuales, revisadas por quien hable el idioma | Nada afirma la precisión del producto. |
| Script | PHP sin dependencias | Como `marketing/render.php` y `portadas-svg/`. |
| Marca | «Contrastocolor» directamente | Si la pregunta 30 de `PREGUNTAS-PARA-ABOGADO.md` acaba en un cambio de nombre, hay que reescribir las piezas. |
| Medición | Clics y aperturas | Ver «Cómo se mide si sirve». |

## Fases

| Fase | Qué se entrega | Cuándo está lista |
|---|---|---|
| 0. Decisiones | Las de arriba | Hecha |
| 1. Corpus | Citas candidatas, cada una con la ficha de [`CRITERIOS.md`](CRITERIOS.md) y su estado de verificación, empezando por el orden de entrada de los idiomas | Cada cita tiene fuente comprobada, o está marcada `sin-verificar` y no se publica |
| 2. Paleta y datos | La paleta de las 17 categorías (con variante para daltonismo) y `datos/citas.json` con un esquema que usa códigos de 3 letras | El JSON valida contra el esquema y no tiene citas sin fuente; la paleta pasa una comprobación de contraste en modo claro y oscuro |
| 3. Traducciones y etiquetas | Traducción de cada original y etiquetas de categoría, como borrador | Una persona que hable el idioma revisa y firma cada idioma antes de usarlo; hay una guía de revisión como la de `marketing/idiomas/GUIA-REVISION.md` |
| 4. Render | Un script PHP sin dependencias que genera la pieza del sitio y el texto HTML del correo, con `--comprobar` | `--comprobar` pasa sin dejar cambios sin commitear |
| 5. CI | `pruebas-citas-pos.yml` con filtro de rutas, junto con su viñeta en «CI/CD» del README raíz | Verde en un PR de una sola carpeta |

La fase 1 no necesita código, y es la que más valor tiene y más riesgo quita.

## Preguntas nuevas

Surgen de las decisiones tomadas y bloquean la fase 1:

1. **Cuántas citas por idioma.** Con 30 idiomas, 6 citas por idioma son 180 fichas, cada una verificada, etiquetada a mano y revisada. ¿Cuántas queremos de verdad?
2. **Quién etiqueta y revisa.** Para etiquetar a mano en un idioma hace falta hablarlo. ¿Hay personas disponibles para cuántos de los 30?
3. **Cómo se cubren los 30 idiomas.** Dos lecturas posibles: una cita *original* por idioma (de autores de cada tradición) o una misma idea traducida a muchos idiomas. `CRITERIOS.md` supone la primera, con la segunda para las piezas de «el mismo pensamiento en varias lenguas».
4. **Sistema de correo.** ¿Con qué herramienta se envía el correo? Cambia lo que se puede medir y cómo se inserta la cita.
5. **Tamaño de la audiencia.** Sin saber cuántas personas reciben el correo o visitan el sitio, no se sabe si la prueba de abajo tendrá datos suficientes.

## Cómo se mide si sirve

Antes de producir mucho contenido, una prueba pequeña:

- **6 citas, cada una en dos versiones**: coloreada y plana, con el mismo texto. Se prueban en pocos idiomas (propongo 2 o 3), no en los 30.
- Se reparten al azar entre quienes reciben el correo y entre quienes visitan el sitio, y se comparan **clics y aperturas**.
- Los **clics pesan más que las aperturas**: algunos clientes de correo protegen la privacidad de tal forma que inflan las aperturas.
- **Si la versión coloreada no gana de forma clara, se replantea la estrategia** antes de seguir con la fase 3 en adelante.
- La medición es **agregada, sin perfilar a nadie**, y respeta [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md).

## Riesgos

| Riesgo | Qué hacemos |
|---|---|
| Citas mal atribuidas | Fuente primaria obligatoria; `sin-verificar` no se publica. |
| Derechos de autor | Solo dominio público, edición citada, traducciones propias; comprobación en `copyright-laws/` y consulta antes de publicar con la marca. |
| El etiquetador no existe en este repo | Las etiquetas son manuales y se dice; nada afirma precisión del producto. |
| **30 idiomas con etiquetas manuales** | Es el riesgo mayor: cada idioma necesita a alguien que lo hable. Se entra por orden (ver `CRITERIOS.md`), un idioma sin revisor no se publica y se empieza con pocos idiomas para la prueba. |
| Idiomas difíciles | Escrituras sin espacios (`zho`, `jpn`, `tha`), de derecha a izquierda (`ara`, `heb`, `fas`) y con morfología rica exigen herramientas y revisión específicas. |
| 17 colores difíciles de distinguir | Series por categoría, variante apta para daltonismo, etiqueta visible además del color. |
| Traducciones con errores | Borrador hasta la revisión nativa. |
| Tono lúdico que derive en afirmaciones | El juego va en el acompañamiento; la cita y los hechos no se tocan. |
| Cambio de nombre de la marca | La pregunta 30 sigue abierta; las piezas llevan el nombre directamente y habría que reescribirlas. |
| Dos sesiones haciendo lo mismo | Mirar PR abiertos y ramas antes de cada fase (`CLAUDE.md`). |
