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
| Medición | Clics y aperturas, **pospuesta** hasta que haya audiencia y herramienta de correo | Hoy no hay ni audiencia ni herramienta de correo. Se prepara el material y la prueba queda pendiente (ver «Cómo se mide si sirve»). |
| Piloto | **1 idioma, `spa`, con 6 citas = 6 fichas**, revisadas por la persona | No habrá revisores nativos de otros idiomas por ahora. Antes era de 3 idiomas (`spa`, `eng`, `deu`, 18 fichas); bajó porque solo hay quien revise español. |
| Otros idiomas | Borrador interno, sin publicar, hasta que haya revisores | Los 30 siguen siendo el alcance a largo plazo, pero **solo se publica lo que una persona que habla el idioma haya revisado**. Hoy eso es solo `spa`. |
| Cobertura de los 30 | Una cita original por idioma primero; una «cita ancla» traducida a los 30 después | Criterios de la cita ancla en `CRITERIOS.md`. |
| Registro de revisiones | El formato de `Listado-colores/` | [`REVISIONES.md`](REVISIONES.md) y `revisiones.csv`, validado por la CI. |

El piloto de 3 idiomas, la cobertura de los 30 y el registro los propuso Claude el 25 de octubre de 2026 y los aprobó la persona; el resto son decisiones de la persona del 24 de octubre. **Después**, al responder las preguntas abiertas de este plan, la persona decidió que no habrá revisores nativos por ahora: el piloto bajó a `spa` (revisado por ella), los demás idiomas quedan como borrador interno y la prueba de medición se pospone.

## Fases

| Fase | Qué se entrega | Estado |
|---|---|---|
| 0. Decisiones | Las de arriba | Hecha |
| 1. Corpus | Las 6 fichas del piloto (6 citas en `spa`), cada una con la ficha de [`CRITERIOS.md`](CRITERIOS.md) y su estado de verificación | Pendiente: **es lo siguiente**. Cada cita tiene fuente comprobada, o está marcada `sin-verificar` y no se publica |
| 2. Paleta y datos | La paleta de las 17 categorías (con variante para daltonismo) y `datos/citas.json` con un esquema que usa códigos de 3 letras | **Paleta hecha, provisional**: [`datos/paleta.json`](datos/paleta.json), su comprobador y un [prototipo web](prototipo/index.html). Falta `datos/citas.json` y probar la paleta con personas |
| 3. Traducciones y etiquetas | Etiquetas de categoría de las citas en `spa`; traducciones a otros idiomas solo como borrador interno | Pendiente. Una persona que hable el idioma revisa y firma cada idioma en [`revisiones.csv`](revisiones.csv). **Con solo `spa` revisable, esta fase se limita a ese idioma** hasta que haya revisores de otros; entonces hace falta una guía de revisión como la de `marketing/idiomas/GUIA-REVISION.md` |
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

## Preguntas

### Contestadas (25 de octubre de 2026)

1. **Quién revisa, y el tercer idioma del piloto.** Nadie nativo: el piloto es solo `spa`, revisado por la persona. Ver «Decisiones tomadas».
2. **Sistema de correo.** Todavía no hay ninguno. La cita va como texto HTML con color en línea, genérico, y se adapta cuando haya herramienta.
3. **Tamaño de la audiencia.** Todavía no hay audiencia.
4. **Prueba de la paleta con personas.** Nadie por ahora: la paleta sigue provisional.
5. **Número de citas por idioma tras el piloto.** Deja de aplicar: el piloto es de 6 citas en un solo idioma.

### Siguen abiertas

Estas dependen de que existan cosas que hoy no existen. Se retoman cuando alguna de ellas aparezca:

1. **Cuándo hay revisores de otros idiomas.** Cada idioma nuevo que tenga un revisor entra al piloto, por el orden de `CRITERIOS.md`.
2. **Cuándo hay audiencia y herramienta de correo.** Entonces se corre la prueba de «Cómo se mide si sirve», y se decide el sistema de correo.
3. **Cuándo se prueba la paleta con personas.** Con visión de color distinta (protanopía, deuteranopía) y con lector de pantalla; hasta entonces la paleta es provisional.
4. **Cómo conseguir audiencia.** Sin ella, el trabajo sobre las citas no se puede medir. No es una pregunta de este proyecto, pero lo condiciona.

## Cómo se mide si sirve

**Pospuesta**: hoy no hay audiencia ni herramienta de correo, así que esta prueba no se puede correr. Se deja descrita para cuando existan; mientras, se prepara el material (las 6 citas del piloto y las piezas) para tenerlo listo. Sin esta prueba **no sabemos si la versión coloreada sirve más que la plana**, y esa duda sigue abierta.

Antes de producir mucho contenido, una prueba pequeña:

- **6 citas, cada una en dos versiones**: coloreada y plana, con el mismo texto. Se prueban en los idiomas del piloto (hoy solo `spa`), no en los 30.
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
| **30 idiomas con etiquetas manuales y sin revisores nativos** | Es el riesgo mayor, y hoy se materializó: no hay revisores de ningún idioma salvo `spa`. El piloto bajó a ese idioma, los demás quedan como borrador interno y **un idioma sin revisor no se publica**. El alcance de 30 idiomas sigue como horizonte, no como plan a corto plazo. |
| No haber audiencia ni correo | La medición se pospone; se prepara el material para cuando existan. Sin esa medición, la estrategia coloreada se apoya en una intuición no comprobada. |
| Idiomas difíciles | Escrituras sin espacios (`zho`, `jpn`, `tha`), de derecha a izquierda (`ara`, `heb`, `fas`) y con morfología rica exigen herramientas y revisión específicas. |
| 17 colores difíciles de distinguir | Una marca por grupo, etiqueta visible opcional, y probar la paleta con personas antes de aprobarla. |
| La paleta provisional se toma por definitiva | Está marcada como provisional en `datos/paleta.json` y aquí; se cambia con la prueba con personas. |
| Traducciones con errores | Borrador hasta la revisión nativa. |
| Tono lúdico que derive en afirmaciones | El juego va en el acompañamiento; la cita y los hechos no se tocan. |
| Cambio de nombre de la marca | La pregunta 30 sigue abierta; las piezas llevan el nombre directamente y habría que reescribirlas. |
| Dos sesiones haciendo lo mismo | Mirar PR abiertos y ramas antes de cada fase (`CLAUDE.md`). |
