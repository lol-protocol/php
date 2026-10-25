# Plan

Cada fase va en su propio PR, en borrador, y se fusiona solo cuando la persona lo decide.

## Decisiones tomadas

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
| Piloto | 3 idiomas × 6 citas = 18 fichas | Empezamos con pocos idiomas; el resto de los 30 entra cuando haya revisor. Idiomas propuestos: `spa`, `eng`, `deu`; el tercero se cambia si quien revisa habla otro (ver `CRITERIOS.md`). |
| Cobertura de los 30 | Una cita original por idioma primero; una «cita ancla» traducida a los 30 después | Criterios de la cita ancla en `CRITERIOS.md`. |
| Registro de revisiones | El formato de `Listado-colores/` | [`REVISIONES.md`](REVISIONES.md) y `revisiones.csv`, validado por la CI. |

El piloto, la cobertura de los 30 y el registro los propuso Claude el 25 de octubre de 2026 y los aprobó la persona; el resto son decisiones de la persona del 24 de octubre.

## Fases

| Fase | Qué se entrega | Estado |
|---|---|---|
| 0. Decisiones | Las de arriba | Hecha |
| 1. Corpus | Las 18 fichas del piloto (6 citas en `spa`, `eng` y `deu`), cada una con la ficha de [`CRITERIOS.md`](CRITERIOS.md) y su estado de verificación | Pendiente. Cada cita tiene fuente comprobada, o está marcada `sin-verificar` y no se publica |
| 2. Paleta y datos | La paleta de las 17 categorías (con variante para daltonismo) y `datos/citas.json` con un esquema que usa códigos de 3 letras | **Paleta hecha, provisional**: [`datos/paleta.json`](datos/paleta.json), su comprobador y un [prototipo web](prototipo/index.html). Falta `datos/citas.json` y probar la paleta con personas |
| 3. Traducciones y etiquetas | Traducción de cada original y etiquetas de categoría, como borrador | Pendiente. Una persona que hable el idioma revisa y firma cada idioma en [`revisiones.csv`](revisiones.csv); hay una guía de revisión como la de `marketing/idiomas/GUIA-REVISION.md` |
| 4. Render | Un script PHP sin dependencias que genera la pieza del sitio y el texto HTML del correo, con `--comprobar` | Pendiente. `bin/prototipo.php` es un antecedente: genera la página del prototipo con el mismo `--comprobar` |
| 5. CI | `.github/workflows/pruebas-citas-pos.yml` con filtro de rutas y su viñeta en «CI/CD» del README raíz | **Creado**, con tres comprobaciones (paleta, prototipo al día, registro de revisiones). Crece con cada fase |

La fase 1 no necesita código, y es la que más valor tiene y más riesgo quita.

### Qué se puede ejecutar hoy

```bash
php citas-pos/bin/comprobar-paleta.php          # las 17 categorías, contraste, colores confundibles (--lista para verlos)
php citas-pos/bin/prototipo.php --comprobar     # prototipo/index.html al día con datos/ (sin --comprobar, lo regenera)
php citas-pos/bin/comprobar-revisiones.php      # revisiones.csv válido
```

### Estado de la paleta

La paleta es **provisional**: sirve para ver el producto y para pruebas, no está aprobada como identidad de marca. Lo que ya se comprobó (con las matrices de simulación y la fórmula de distancia de color que cita `bin/comprobar-paleta.php`):

- Contraste mínimo contra el fondo de 4,5:1 en los dos modos.
- Los 17 colores se distinguen entre sí con visión normal, en claro y en oscuro.
- Con daltonismo simulado, los colores de **grupos distintos** que se confunden se salvan con la marca (el estilo de subrayado de cada grupo); los del **mismo grupo** que se confunden figuran como avisos y se distinguen por la etiqueta de la categoría.

Lo que **no** se comprobó: ninguna persona con daltonismo la ha probado, ni se ha visto en un correo real, y la simulación es una aproximación. La paleta se aprueba, o se cambia, después de probarla con personas y en el sitio (ver «Preguntas nuevas»).

## Preguntas nuevas

Tras las decisiones tomadas, quedan abiertas:

1. **El tercer idioma del piloto y quién revisa.** `spa`, `eng` y `deu` son una propuesta. ¿Quién puede etiquetar y revisar de verdad, y en qué idiomas? Define la fase 1.
2. **Sistema de correo.** ¿Con qué herramienta se envía? Cambia lo que se puede medir y cómo se inserta la cita (texto HTML con color en línea, ver «Decisiones tomadas»).
3. **Tamaño de la audiencia.** Sin saber cuántas personas reciben el correo o visitan el sitio, no se sabe si la prueba de abajo tendrá datos suficientes.
4. **Prueba de la paleta con personas.** ¿Quién puede probar el prototipo con visión de color distinta (protanopía, deuteranopía) y con lector de pantalla?
5. **Número de citas por idioma tras el piloto.** Con 6 por idioma, 30 idiomas son 180 fichas. ¿Cuántas queremos de verdad, una vez visto el piloto?

## Cómo se mide si sirve

Antes de producir mucho contenido, una prueba pequeña:

- **6 citas, cada una en dos versiones**: coloreada y plana, con el mismo texto. Se prueban en los idiomas del piloto (3), no en los 30.
- Se reparten al azar entre quienes reciben el correo y entre quienes visitan el sitio, y se comparan **clics y aperturas**.
- Los **clics pesan más que las aperturas**: algunos clientes de correo protegen la privacidad de tal forma que inflan las aperturas.
- **Si la versión coloreada no gana de forma clara, se replantea la estrategia** antes de seguir con la fase 3 en adelante.
- La medición es **agregada, sin perfilar a nadie**, y respeta [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md).
- Con una audiencia pequeña, un A/B de clics no dará señal clara: se completa con una prueba cualitativa con 10 o 20 lectores a quienes se enseñan las dos versiones y se les pregunta cuál entienden mejor.

## Riesgos

| Riesgo | Qué hacemos |
|---|---|
| Citas mal atribuidas | Fuente primaria obligatoria; `sin-verificar` no se publica. |
| Derechos de autor | Solo dominio público, edición citada, traducciones propias; comprobación en `copyright-laws/` y consulta antes de publicar con la marca. |
| El etiquetador no existe en este repo | Las etiquetas son manuales y se dice; nada afirma precisión del producto. |
| **30 idiomas con etiquetas manuales** | Es el riesgo mayor: cada idioma necesita a alguien que lo hable. Se empieza con el piloto de 3, se entra por orden (ver `CRITERIOS.md`) y un idioma sin revisor no se publica. |
| Idiomas difíciles | Escrituras sin espacios (`zho`, `jpn`, `tha`), de derecha a izquierda (`ara`, `heb`, `fas`) y con morfología rica exigen herramientas y revisión específicas. |
| 17 colores difíciles de distinguir | Una marca por grupo, etiqueta visible opcional, y probar la paleta con personas antes de aprobarla. |
| La paleta provisional se toma por definitiva | Está marcada como provisional en `datos/paleta.json` y aquí; se cambia con la prueba con personas. |
| Traducciones con errores | Borrador hasta la revisión nativa. |
| Tono lúdico que derive en afirmaciones | El juego va en el acompañamiento; la cita y los hechos no se tocan. |
| Cambio de nombre de la marca | La pregunta 30 sigue abierta; las piezas llevan el nombre directamente y habría que reescribirlas. |
| Dos sesiones haciendo lo mismo | Mirar PR abiertos y ramas antes de cada fase (`CLAUDE.md`). |
