# Marcalibros — plan, decisiones y estado

Documento de traspaso: resume lo acordado en la conversación original para poder continuar desde Claude Code.

## Qué es

**Marcalibros** son marcadores de lectura con diseño propio para Plataforma POS (herramienta de etiquetado NLP sobre textos de Project Gutenberg) y su lector de textos. Este repo contiene el catálogo y el generador que los produce.

## Decisiones tomadas

- **Proporción 1:7, solo vertical (decidido).** 200×1400 px (400×2800 para alta densidad). No habrá versión horizontal. Cada pieza reserva una franja de texto central de 168×420.
- **1000 marcalibros iniciales** = 10 movimientos literarios × 10 autores × 10 variantes.
- **Solo 100 en JPG y 900 en SVG.** Regla actual: la variante 01 de cada autor es JPG, el resto SVG.
- **Generación automática**, no a mano: un generador determinista (misma entrada, misma salida) lee `catalog/taxonomia.yaml`.
- **Estilos conectados con estilos literarios y autores.**
- **Marcalibros de usuario:** los usuarios pueden crear los suyos eligiendo imágenes de la biblioteca o subiendo las propias.
- **Estilo visual:** híbrido. Iconos planos para la interfaz de etiquetado y estética de grabado para el lector y los paisajes.
- Carpeta y rama llevan la palabra "marcalibros".

## Movimientos y autores propuestos

Solo hay 4 autores por movimiento; faltan 6 en cada uno. Fechas, obras y regiones están sin verificar.

| Movimiento | Autores propuestos |
|---|---|
| Romanticismo | Bécquer, Espronceda, Poe, Byron |
| Realismo | Blest Gana, Galdós, Balzac, Dickens |
| Naturalismo | Zola, Pardo Bazán, Maupassant, Blasco Ibáñez |
| Modernismo | Rubén Darío, José Martí, Valle-Inclán, Nervo |
| Vanguardias | Huidobro, Lorca, Apollinaire, Tzara |
| Gótico | Mary Shelley, Bram Stoker, Ann Radcliffe, Le Fanu |
| Barroco | Sor Juana Inés de la Cruz, Quevedo, Góngora, Calderón |
| Clasicismo | Homero, Virgilio, Racine, Garcilaso |
| Simbolismo | Baudelaire, Mallarmé, Rimbaud, Verlaine |
| Realismo mágico | García Márquez, Rulfo, Carpentier, Asturias |

Los nombres de autores se usan solo como metadato e inspiración: sin citas, portadas ni retratos.

## Estado actual

Prototipo de **Romanticismo / Gustavo Adolfo Bécquer**: 10 piezas (1 JPG y 9 SVG). En `taxonomia.yaml` solo existe ese autor.

```
catalog/taxonomia.yaml   fuente de verdad (movimiento → autor → motivos)
catalog/svg/             salida del generador (SVG)
catalog/jpg/             salida del generador (JPG con grano)
catalog/manifest.json    índice generado
catalog/revision/        hojas de contacto para revisión humana
generator/scene.py       modelo de escena compartido (degradado, círculo, polígono)
generator/plantillas.py  paletas y plantillas por movimiento
generator/render_svg.py  escena → SVG determinista
generator/render_raster.py  escena → imagen (2× supersampling, grano opcional)
generator/qa.py          validaciones, detección de duplicados, hojas de contacto
generator/build.py       orquestador
```

Generar: `pip install -r generator/requirements.txt` y luego `python generator/build.py --movimiento romanticismo` (o `--all`).

Verificado:
- El build es determinista (hashes idénticos al regenerar).
- Los 9 SVG renderizan en Chromium (vía Playwright) a 1400×200 exactos, con diferencia media de 0,6 sobre 255 por píxel respecto del renderer raster de respaldo.
- Límites de QA: SVG ≤ 10 KB, JPG ≤ 150 KB, sin referencias externas, sin EXIF.

## Paso 1 hecho (generador 0.2.0)

- **Variedad:** 5 arquetipos de composición (colinas, montañas, llanura, marina, bosque), 8 paletas, desplazamiento aleatorio del horizonte y cielo invertido. Cada autor recorre su propia ordenación de los 40 pares arquetipo×paleta, sin repetir par entre sus 10 variantes.
- **Duplicados bloquean el build:** si una pieza queda casi idéntica a otra del mismo movimiento, se cambia la semilla (hasta 30 reintentos; el nº de reintento queda en el manifest como `intento`). Si no se resuelve, el build falla. Simulación de 100 piezas de un movimiento con autores de motivos idénticos: 17 reintentos, 0 fallos.
- **QA con Chromium:** los SVG se rasterizan con Chromium headless (variable `CHROMIUM` o `/opt/pw-browsers/chromium`) y se comparan con el renderer raster (tolerancia 3/255; hoy 0,5–1,5). Sin Chromium el build falla; ya no hay respaldo silencioso.
- **Tests:** `python -m pytest tests` (determinismo, hashes de referencia, duplicados, pares únicos, archivos del catálogo). Tras un cambio intencional de plantillas: `python tests/test_generador.py --actualizar`.
- Pendiente de este paso: fijar la versión de Python en CI y compilación incremental. Hay que ajustar con datos reales la métrica de duplicados cuando haya más autores.

## Paso 2 en curso (generador 0.3.0)

- Taxonomía v0.2: cada variante es una obra/tema del autor (`obras`), con `motivo` de `catalog/motivos.yaml` y `gutenberg: {id, estado}`. `qa.validar_taxonomia` bloquea motivos inexistentes y `verificado` sin id.
- Romanticismo completo en borrador: 10 autores × 10 obras/temas = 100 piezas (10 JPG, 90 SVG), 0 errores de validación y 19 reintentos de semilla por duplicado. Las obras de los 9 autores distintos de Bécquer están escritas de memoria, sin fechas y sin verificar; los 6 autores tras Poe/Byron (Rosalía de Castro, Larra, Hugo, Keats, P. B. Shelley, Wordsworth) son propuesta.
- Hojas de contacto por autor (`catalog/revision/hoja-romanticismo-<autor>.jpg`), no una de 100.
- **Vínculo obra→imagen (generador 0.4.0):** cada obra tiene `animo` (catalog/animos.yaml: nocturno, tormentoso, melancólico, crepuscular, auroral, sentimental, pastoral, sublime, otonal) que decide la paleta, y opcionalmente `escena` que fija la composición. La biblioteca de motivos pasó de 4 a 14 (cuervo, cipreses, ruinas, velero, castillo, árbol, catedral, rayo, cruz, estrellas). El QA exige coherencia: luna → ánimo nocturno/tormentoso, rayo → tormentoso, estrellas → nocturno, velero → escena marina. 4 paletas nuevas (12 en total). Los ánimos y motivos de las 100 obras son una asignación mía, sin revisar.
- **Duplicados:** la huella visual pasó de gris a color con rango estirado (las paletas oscuras se confundían) y la asignación arquetipo×paleta evita pares repetidos en el movimiento. Resultado: 0 reintentos de semilla en las 100 piezas.
- **Biblioteca ampliada (taxonomía v0.4):** 40 motivos + «ninguno». Los 26 nuevos incluyen figuras específicas de obra (órgano, fuente, puente, faro, horca, gato, corazón, reloj, ataúd, lápidas, vela, olas, urna, coloso caído, serpiente, espada, corona, barca, arco gótico, hórreo gallego, barricada, rosa, lira, narcisos, hojas, nube). Se asignaron a unas 35 obras (p. ej. órgano → *Maese Pérez*, fuente → *Los ojos verdes*, puente → *La cruz del diablo*, horca → *El reo de muerte*, narcisos → *Vagaba solitario como una nube*). Restricciones nuevas: faro, barca y olas exigen escena marina; narcisos ánimo pastoral; hojas ánimo otonal. Las 100 piezas se usan 41 motivos distintos y siguen en 0 reintentos.
- **Anclaje y escala (generador 0.5):** los motivos se dibujan entre la capa lejana y la cercana, de modo que la cercana tapa su base. El suelo es la capa lejana (colinas, llanura, marina), la cordillera cercana (montañas) o el pie del bosque. Cada motivo tiene factor de escala propio (`ESCALAS`, ×1,2 a ×1,8); olas, narcisos, hojas y río van por delante.
- **Legibilidad (generador 0.6):**
  - *Siluetas:* `paleta_legible` ajusta el color de las siluetas para que lleguen a 3:1 contra el cielo bajo y la capa lejana. En las paletas nocturnas (noche, medianoche, tormenta, tempestad) las siluetas pasan al tono claro. Test: las 12 paletas cumplen.
  - *Miniatura (50×350):* el QA compara la escena con y sin motivo y exige ≥30 píxeles cambiados (miniatura de 50×350); si no, cambia la semilla como con los duplicados. Esto detectó que el río quedaba tapado. `hoja-…` sigue siendo la vista grande y `miniaturas-romanticismo.jpg` la de tamaño real.
  - *Texto encima (generador 0.7):* cada pieza reserva una **franja de texto** de 500×88 px (izquierda, centro o derecha; la elige el generador según dónde queden el sol y el motivo, que nunca la pisan, salvo motivos ambientales más anchos de 700 px como estrellas o golondrinas). Dentro de la franja se mide el contraste (percentil 10) con texto claro y oscuro; si no llega a 4,5:1, se hornea en la propia pieza un **velo translúcido** solo dentro de la franja (opacidad 0,35–0,8, la más suave que lo logre). Manifest: `franja`, `texto_recomendado`, `contraste_texto`, `velo`. Resultado en Romanticismo (vertical): 75 piezas la leen sin velo y 25 con velo.
  - Limitación: el velo es un recuadro de bordes redondeados duros; se nota como «caja» en las paletas medias. Mejora posible: bordes difuminados o ajustar el degradado de la propia escena en esa zona.
- **Variantes claro/oscuro: no hechas.** Falta definir qué son (¿adaptar el marcalibros al tema de la interfaz o recolorearlo?). Lo cubre en parte `texto_recomendado`.
- Limitaciones: en bosque los árboles tapan parte del motivo (la fuente de *Los ojos verdes*); ~25 obras siguen en «ninguno» o con motivo genérico. Sin ilustrar: motivos para los otros 9 movimientos.
- **Ids de Gutenberg: sin verificar.** El entorno bloqueó gutenberg.org y gutendex.com. Cuando haya red: `python generator/verificar_gutenberg.py` genera `catalog/revision/gutenberg-candidatos.md` (probado solo con respuesta simulada, no contra la API real).
- Biblioteca de motivos: 4 motivos + `ninguno`; el objetivo (~40) sigue pendiente.

## Mejoras pendientes (de la revisión del plan)

**Prioridad alta**
1. **Variedad insuficiente.** Una simulación de 100 piezas de un movimiento dio 186 de 4.950 pares casi idénticos con el umbral actual (11 con uno más estricto). La métrica es burda, pero indica el riesgo. Causa: 3 paletas × 3–4 motivos sobre la misma composición de colinas. Mejora: más arquetipos de composición, 6 o más paletas por movimiento, ornamentos por capas, y que el detector de duplicados bloquee el build y cambie la semilla en vez de solo avisar.
2. **El vínculo literario es solo metadato.** Propuesta: que cada variante sea una obra o tema del autor (10 por autor), con motivos tomados de una biblioteca compartida (~40) y una gramática visual por movimiento. Los 1000 registros de obras y fechas deben tratarse como borrador a verificar.
3. **Enlace con Gutenberg.** Cada autor y obra debería llevar su id de Gutenberg para que el lector sugiera el marcalibro del texto abierto. Riesgo: Gutenberg solo aloja obras de dominio público, así que los autores del Realismo mágico probablemente no tienen textos allí. No se pudo confirmar (el sitio bloquea la consulta automática): verificar a mano y considerar reemplazar ese movimiento.

**Otras**
- Plan original perdido: marca y logo, iconos de 24×24, ilustraciones de estados vacíos, cabeceras de 1920×1080, previews sociales de 1200×630 y variantes claro y oscuro no están agendados desde el giro a marcalibros. Decidir si son una línea aparte o se descartan.
- Sacar la paleta del código a un `brand-tokens.yaml`: base de marca más un acento por movimiento, con contraste validado para texto encima.
- Dar un rol a los 100 JPG: biblioteca de imágenes para marcalibros de usuario, con mayor resolución (por ejemplo 2800×400) para poder recortar.
- Build: falta la compilación incremental (ya hay hashes en el manifest) y los tests con hashes de referencia. Fijar la versión de Python en CI.
- Git: regenerar 900 SVG por cada ajuste de plantilla ensucia el historial. Recomendación: versionar taxonomía, generador, manifest y unas pocas muestras, y publicar el catálogo completo como artefacto de CI.
- Modelo de datos: separar el diseño del marcalibro (catálogo y recetas de usuario) de la marca guardada en un texto (id del texto, rango de caracteres, etiqueta, id del diseño).
- Subidas de usuario: solo JPG, PNG y WebP; volver a codificar la imagen (no solo quitar EXIF); limitar píxeles (bombas de descompresión); nombres aleatorios; cuota por usuario; moderación; servir como `<img>` desde otro origen; no aceptar SVG subidos.

## Orden propuesto

1. Variedad, bloqueo de duplicados, QA con Chromium y tests, sobre el prototipo.
2. Taxonomía v2 (obras, biblioteca de motivos, ids de Gutenberg) y completar Romanticismo con 10 autores.
3. Resto de los movimientos.
4. Editor de marcalibros de usuario (recetas en JSON: plantilla, imagen, recorte, texto).
5. Línea de marca base.

## Paso 3 en curso: Gótico y Realismo mágico (generador 0.8)

Hecho: 3 movimientos × 10 autores × 10 obras = **300 piezas** (30 JPG, 270 SVG), 0 errores de validación y 17 tests.

- **Gótico** (Mary Shelley, Stoker, Radcliffe, Le Fanu, Walpole, M. G. Lewis, Beckford, Maturin, Stevenson, Emily Brontë; los 4 primeros del plan original). Gramática visual: arco ojival que enmarca la parte alta, bandas de niebla bajas y paletas frías o rojizas (cripta, sangre, luna fría). Motivos nuevos: murciélagos, gárgola, casa encantada, ventana ojival, lobo, figura encapuchada.
- **Realismo mágico** (García Márquez, Rulfo, Carpentier, Asturias, Isabel Allende, Laura Esquivel, Elena Garro, Uslar Pietri, Jorge Amado, Gioconda Belli; los 4 primeros del plan original). Gramática visual: paletas cálidas y saturadas (trópico, cobre, polvo, aguamarina) y mariposas que acompañan a cada pieza. Motivos nuevos: mariposas, lluvia de flores amarillas, ceiba, casa colonial, volcán, maíz, pececillos de oro, gallo, lluvia, aparecido, alas.
- Biblioteca: 57 motivos + «ninguno». Ánimos nuevos: macabro, lúgubre, espectral, onírico, exuberante, seco.
- Campo `de` opcional en la taxonomía: cuando la variante es un tema de una obra (p. ej. «La lluvia de flores amarillas», de *Cien años de soledad*).
- **Los 6 + 6 autores añadidos para completar 10 por movimiento son propuesta mía; las 200 obras y temas están escritos de memoria, sin fechas y sin verificar.** Especialmente delicados los de autores vivos o poco conocidos (Belli, Garro, Uslar Pietri, Amado) y los títulos de poemas de Emily Brontë.
- Realismo mágico y Gutenberg: previsiblemente `no_disponible` (autores bajo derechos), a comprobar cuando haya red.
- Limitaciones: en paletas cálidas el oro de las mariposas no contrasta con el cielo y se dibujan oscuras (`color_oro`); 100 de las 300 piezas llevan velo en la franja de texto (más que en Romanticismo porque las paletas medias dominan); el alas/lobo/gárgola son siluetas simples. Quedan 7 movimientos: Realismo, Naturalismo, Modernismo, Vanguardias, Barroco, Clasicismo y Simbolismo.
- Tiempo de build: unos 6 minutos para 300 piezas; con 1000 será necesaria la compilación incremental.

## Decisiones tomadas en la última ronda

- **Orientación:** solo vertical (200×1400). El generador 0.8 rehízo la composición: cielo en el tercio superior, paisaje en el inferior, franja de texto en el centro. Las piezas horizontales anteriores desaparecen.
- **Catálogo:** artefacto de CI, no git. `.gitignore` excluye `catalog/svg|jpg|revision`; el workflow `.github/workflows/marcalibros-b.yml` (en la raíz del repo, con la misma convención que los otros) genera el catálogo completo con Python 3.13 y Chromium, ejecuta los tests y sube `marcalibros-catalogo` (30 días). En git quedan manifest y `catalog/muestras/`. **El workflow no se ha podido ejecutar desde esta sesión**: hay que lanzarlo una vez (workflow_dispatch) y vigilar que los hashes de referencia coincidan entre máquinas (el seno y coseno de libm podrían diferir en la última cifra).
- **Realismo mágico: se mantiene** (es un movimiento real e importante). Gutenberg solo aloja obras de dominio público y los autores previstos (García Márquez, Rulfo, Carpentier, Asturias) están bajo derechos de autor en la mayoría de países, así que sus obras serán `gutenberg.estado: no_disponible` una vez comprobado (estado nuevo; hoy todo es `sin_verificar`). Consecuencia: el lector no podrá sugerir marcalibro automáticamente para esos textos; el usuario lo elegirá a mano. Los nombres se usan solo como metadato: sin citas, portadas ni retratos.

## Decisiones abiertas

- ¿Qué se hace con el plan original de marca (logo, iconos, estados vacíos, cabeceras)?
- ¿Qué son las variantes claro/oscuro (adaptar al tema de la interfaz o recolorear)?
- Autores definitivos de Romanticismo (los 6 tras Poe y Byron son propuesta) y de los otros 9 movimientos (hoy 4 por movimiento sin verificar).
