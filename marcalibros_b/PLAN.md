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

## Legibilidad del texto sin velos (generador 0.8, octubre)

- Medición previa: con los tonos de texto de marca (crema y azul) 361 de 1000 piezas necesitaban velo; con blanco y casi negro, 240. En 220 de esas 240 el problema era solo que la franja caía en un tono medio uniforme del degradado.
- Cambios: los tonos de texto pasan a `#FFFFFF` / `#111111`; si aun así la franja no llega a 4,5:1, el generador **calma el cielo** en la franja: añade paradas al degradado que lo oscurecen o aclaran (intensidad 0,1–0,6, la menor que baste) con transiciones de 80 px. Solo si eso no basta, velo (hoy ninguna pieza).
- Resultado: 760 piezas se leen tal cual, 240 con el cielo calmado (casi todas a 0,1–0,2) y **0 con velo**. Manifest: `calma_cielo`.

## Primera pasada de correcciones (taxonomía v0.7)

Hecha por Claude sin marcas de la página de revisión (no había ninguna guardada). 38 cambios, solo de datos:
- **Motivos:** 12 obras que estaban en «ninguno» reciben motivo propio (p. ej. *Don Juan* → barca en el mar, por el naufragio; *Los barateros* → navaja/espada; *La máscara de la anarquía* → barricada; *El llano en llamas* → caballo; *Macario* → pozo). Siguen en «ninguno» a propósito 5 paisajes puros (*Himno al sol*, *El mar de hielo del Montanvert*, *Los Pirineos y los Apeninos*, *Los páramos de Yorkshire*, *La excursión*).
- **Ánimo:** *El gato negro* pasa de melancólico a macabro.
- **Títulos que no reconocía con seguridad, sustituidos por obras que sí existen:** Belli (*Luciérnaga* → *Sobre la grama*), Garro (tema → *Isabel convertida en piedra*), Uslar Pietri (*Los visitantes* → *La visita en el tiempo*), Amado (*Tierras del sinfín*, su título habitual), Cambaceres (tema inventado → *La muerte de Andrea*), Gamboa (*El evangelista* → *La venganza de la gleba*), Agustini (*Fiesta* → *Lo inefable*), Gutiérrez Nájera (*Después de la lluvia* → *Mis enlutadas*), Herrera y Reissig (*Sonetos vascos*, *Berceuse blanca*, *La iglesia*), Casal (*Nostalgias*), Girondo (*Nocturno*, *Exvoto*), Marinetti (*Destrucción*), Moréas (*El té en casa de Miranda*), Laforgue (*Las lamentaciones* estaba repetida como *Complaintes* → *Pierrot fumista*), Gracián (tema → *La fuente de los engaños*).
- **Tipos corregidos:** *Apogeo* (poemario), *El taller de las mariposas* (cuento infantil), *Un hogar sólido* y *El árbol* (teatro), *Del natural* (cuentos), *Akëdysséril* (relato), *Y Matarazo no llamó…* (novela).
- El build incremental rehízo 454 piezas y reutilizó 546: la clave de cada pieza incluye las huellas de las anteriores de su movimiento, así que un cambio arrastra a las siguientes.
- Sigue siendo borrador: los títulos sustituidos son los que reconozco con más seguridad, no una verificación con fuentes.

## Página de revisión

- `generator/revision_web.py` genera `catalog/revision/web/` (una hoja de miniaturas por movimiento y la página) y se publica como Artifact: https://claude.ai/artifact/RmfFcDftrNTVdqBG7oTTy9 (privado).
- Permite marcar cada obra y cada autor como «bien» o «corregir» con una nota. Las marcas viven en la base de datos del Artifact (colecciones `revision`, id = id de la pieza, y `autores`, id = `movimiento~autor`) y Claude las lee para aplicar las correcciones a la taxonomía.

## Paso 3 hecho: los 10 movimientos (generador 0.8)

**1000 piezas** (100 JPG y 900 SVG): 10 movimientos × 10 autores × 10 obras o temas. 0 errores de validación y 23 tests. (La legibilidad del texto se rehízo después: ver la sección anterior.)

| Movimiento | Gramática visual (adorno propio) | Paletas nuevas | Motivos nuevos |
|---|---|---|---|
| Romanticismo | paisajes sin adorno | atardecer, noche, niebla… | torre, luna, golondrinas… |
| Gótico | arco ojival que enmarca la parte alta + bandas de niebla | cripta, sangre, luna fría | murciélagos, gárgola, casa encantada, ventana ojival, lobo, encapuchado |
| Realismo mágico | mariposas amarillas | trópico, cobre, polvo, aguamarina | mariposas, flores amarillas, ceiba, casa colonial, volcán, maíz, pececillos de oro, gallo, lluvia, aparecido, alas |
| Realismo | doble filete de grabado | hollín, papel | chimeneas, farola, carruaje, línea de edificios, tren, molino |
| Naturalismo | estratos de tierra al pie | barro, azufre, fango | mina, surcos, trigal, pozo |
| Modernismo | sarmientos art nouveau en los bordes | jade, malva, pavo | cisne, nenúfares, libélula, lirio, pavo real |
| Vanguardias | collage de formas geométricas planas | primarios, rojo y negro, dadá | biplano, torre Eiffel, guitarra cubista, rascacielos, ojo, engranaje |
| Barroco | marco dorado con volutas | oro oscuro, carmesí, azul real | calavera, reloj de arena, concha, laúd, cortinaje, columna salomónica |
| Clasicismo | grecas arriba y abajo | mármol, terracota, laurel, egeo | templo, corona de laurel, caballo, escudo |
| Simbolismo | aureola translúcida alrededor del sol + filamentos de bruma | místico, ámbar, ópalo | esfinge, espejo, mano, creciente, escalera |

- Biblioteca: 93 motivos + «ninguno»; 40 paletas; 25 ánimos (macabro, lúgubre, espectral, onírico, exuberante, seco, urbano, provinciano, crudo, sórdido, exótico, elegíaco, frenético, lúdico, fastuoso, vanitas, sereno, heroico, trágico, místico, decadente, ensueño… además de los anteriores).
- **Todos los autores y obras añadidos en esta tanda son propuesta mía y borrador de memoria: sin fechas y sin verificar.** Los autores de los 7 movimientos nuevos distintos de los 4 del plan original (que sí lo eran) los elegí yo para completar 10. Revisar sobre todo los títulos de autores menos conocidos o vivos (Belli, Garro, Uslar Pietri, Amado, Cambaceres, Gamboa) y los poemarios de Delmira Agustini, Gutiérrez Nájera y Herrera y Reissig.
- Dos cambios de motor: el renderer raster ya no engorda un píxel los filetes finos (diferencia con Chromium del Barroco pasó de 3,3 a 1,9) y el QA del peso SVG detectó trigal y lluvia demasiado pesados (>10 KB), que se aligeraron.
- Gutenberg: todo sigue `sin_verificar`. Realismo mágico y varios autores modernos/vanguardistas (Allende, Esquivel, Belli, Maiakovski…) serán previsiblemente `no_disponible`; Clasicismo (Homero, Virgilio, Sófocles…) y Gótico probablemente sí estén, en traducciones.
- Tiempo: construir las 1000 piezas desde cero tarda unos 17 minutos; cualquier cambio de código invalida toda la caché (la clave incluye el hash del generador), un cambio de taxonomía solo rehace las piezas afectadas.
- Limitaciones: las siluetas (alas, lobo, gárgola, esfinge, mano) son simples; 361 piezas con velo es mucho (36 %), sobre todo en paletas saturadas de las Vanguardias; hay muchas obras con motivo genérico o «ninguno».

## Decisiones tomadas en la última ronda

- **Orientación:** solo vertical (200×1400). El generador 0.8 rehízo la composición: cielo en el tercio superior, paisaje en el inferior, franja de texto en el centro. Las piezas horizontales anteriores desaparecen.
- **Catálogo:** artefacto de CI, no git. `.gitignore` excluye `catalog/svg|jpg|revision`; el workflow `.github/workflows/marcalibros-b.yml` (en la raíz del repo, con la misma convención que los otros) genera el catálogo completo con Python 3.13 y Chromium, ejecuta los tests y sube `marcalibros-catalogo` (30 días). En git quedan manifest y `catalog/muestras/`. **El workflow no se ha podido ejecutar desde esta sesión**: hay que lanzarlo una vez (workflow_dispatch) y vigilar que los hashes de referencia coincidan entre máquinas (el seno y coseno de libm podrían diferir en la última cifra).
- **Realismo mágico: se mantiene** (es un movimiento real e importante). Gutenberg solo aloja obras de dominio público y los autores previstos (García Márquez, Rulfo, Carpentier, Asturias) están bajo derechos de autor en la mayoría de países, así que sus obras serán `gutenberg.estado: no_disponible` una vez comprobado (estado nuevo; hoy todo es `sin_verificar`). Consecuencia: el lector no podrá sugerir marcalibro automáticamente para esos textos; el usuario lo elegirá a mano. Los nombres se usan solo como metadato: sin citas, portadas ni retratos.

## Frases horneadas (hecho, generador 0.9)

- 453 de las 1000 piezas llevan una frase dibujada en la franja de texto, con su autoría debajo («— Autor»); el reparto es al azar pero reproducible (se deriva del id de la pieza). Ninguna del Realismo mágico (ver abajo).
- **Texto como vectores:** los glifos se convierten en contornos (Liberation Serif Cursiva, licencia OFL, copiada en `generator/fuentes/` con su licencia) y el SVG los define una vez y los reutiliza con `<use>`; el renderer raster rellena los mismos contornos. No depende de las fuentes del visor. Chromium y el renderer raster difieren 1,0/255 de media.
- **Catálogo de frases:** `catalog/frases.yaml`, 86 frases breves de autores fallecidos antes de 1956 (dominio público), en su idioma original: 14 de Romanticismo, 8 de Gótico, 5 de Realismo, 2 de Naturalismo, 8 de Modernismo, 8 de Vanguardias, 14 de Barroco, 14 de Clasicismo y 13 de Simbolismo. El QA exige que el autor haya muerto antes de 1956 y que la fuente tenga todos los caracteres, y los tests comprueban que cada frase cabe en la franja.
- **Todas las frases están escritas de memoria y sin verificar** (texto exacto, obra y atribución). Hay que verificarlas antes de usarlas fuera del catálogo.
- **Realismo mágico no tiene frases:** sus autores (García Márquez, Rulfo, Carpentier, Asturias, Allende, Esquivel, Garro, Uslar Pietri, Amado, Belli) tienen derechos vigentes. **Naturalismo** solo tiene dos frases propias (Zola) y reutiliza las de Realismo (`usa_tambien`). Decisión pendiente: ¿las 100 piezas del Realismo mágico se quedan sin frase, o toman prestadas las de Modernismo (autores hispanos en dominio público)?
- Peso: SVG con frase hasta 17,8 KB; el límite de QA pasa de 10 a 20 KB. La fuente va a 15 px, legible a tamaño real (200×1400) pero no en miniatura de 50×350.
- La página de revisión muestra la frase de cada pieza.

## Decisiones de octubre (cuestionario)

- **Texto del marcalibros:** cada pieza sirve para cualquier libro. El generador **hornea** la frase en la franja de unas **500 de las 1000 piezas** (elección al azar reproducible); el resto queda con la franja libre.
- **Frases:** citas breves de autores **en dominio público**, en su **idioma original**, con autoría. Para cada pieza la frase sale de cualquier autor de dominio público **de su movimiento** (así las piezas de autores con derechos, como García Márquez, también pueden llevarla). Se escriben de memoria y quedan marcadas para verificar; nunca de autores con derechos.
- **Obra y autor de cada pieza:** siguen como inspiración (motivo, ánimo, escena) y metadato (el lector puede sugerir la pieza al abrir ese texto), pero no se muestran en el marcalibros.
- **Variantes claro/oscuro:** no hacen falta; el marcalibros es una imagen y el tono de texto ya está calculado por pieza.
- **Línea de marca:** se hace **ya**, en paralelo. Logo de Plataforma POS y variante Marcalibros. Dirección: el símbolo de Marcalibros es una **tira vertical 1:7 con un paisaje mínimo**; el de Plataforma POS, la misma tira con un libro abierto o una etiqueta. Incluye iconos 24×24, estados vacíos, cabeceras 1920×1080 y previews sociales 1200×630.
- **Siguiente trabajo (todo):** frases horneadas, línea de marca, editor de marcalibros de usuario, modelo de datos del lector (diseño separado de la marca en el texto), versiones de alta densidad 400×2800 y pulido de siluetas.

## Orden propuesto para lo que queda

1. Frases horneadas (cambia las piezas: mejor antes de la alta densidad).
2. Línea de marca: logo y símbolo, luego iconos, estados vacíos, cabeceras y previews.
3. Pulido de siluetas.
4. Alta densidad 400×2800.
5. Modelo de datos del lector.
6. Editor de marcalibros de usuario y subidas seguras.

## Decisiones abiertas

- Autores definitivos: los 6 por movimiento añadidos para completar 10 son propuesta de Claude (revisables en la página de revisión).
- Gutenberg: verificar ids cuando la sesión tenga acceso a gutenberg.org y gutendex.com.


## Línea de marca (hecha, primera versión)

`python generator/marca.py [--png]` genera `marca/` (todo versionado, pesa poco):
- `brand-tokens.yaml`: tinta, papel, acento y un acento por movimiento sacado de su paleta, con contraste ≥4,5 sobre papel (validado en tests).
- `logos/`: símbolo de Marcalibros (tira 1:7 con paisaje), símbolo de Plataforma POS (libro abierto con la tira como cinta del lomo) y lockups horizontales claro/oscuro; cada uno nombra a los dos.
- `iconos/`: 15 iconos 24×24 de trazo (`currentColor`, 1,5 px) más `sprite.svg`.
- `estados-vacios/`: biblioteca vacía, sin resultados, sin conexión, sin marcas (480×320).
- `cabeceras/`: inicio, biblioteca, lector, editor (1920×1080, sin texto).
- `previews/`: marcalibros, plataforma-pos, lector (1200×630, SVG y PNG con Chromium).
Texto como vectores con `texto.py` (Liberation Serif Italic). Tests en `tests/test_marca.py`.
Pendiente de revisión del usuario: ajustar paisaje del símbolo, tipografía del logo y paletas de cabeceras.


## Pulido de siluetas (hecho)
Rediseñadas alas (siete plumas de punta redondeada), lobo (sentado, aullando, cola caída), gárgola (alas plegadas, cuernos, hocico), esfinge (nemes, zarpas, grupa) y mano (dedos de punta redonda y pulgar). Ayudas nuevas en `scene.py`: `bezier`, `suave` (Catmull-Rom) y `mover`. Build completo: 1000 piezas, 0 errores; 33 tests pasan; hashes de referencia regenerados.

## Alta densidad 400×2800 (hecho)
Los 100 JPG se generan también a 400×2800 en `catalog/jpg2x/` (misma escena, `a_imagen(esc, 2)`, grano incluido; ~160 KB, límite 600 KB). El manifest lleva `archivo_2x`, `sha256_2x`, `ancho_2x`, `alto_2x` solo en las piezas JPG. Los SVG son vectoriales: se escalan sin versión aparte. La salida a 1× no cambió (hashes de referencia idénticos). CI: `jpg2x` va en la caché y en el artefacto.

## Modelo de datos del lector (hecho)
`datos/` (JSON Schema 2020-12) y `generator/modelo.py` (validación con `jsonschema`, dependencia de desarrollo). Tests: `tests/test_modelo.py`.
- **Diseño** (`diseno.schema.json`): `cat:<id de pieza>` (referencia al manifest, con `sha256` opcional) o `usr:<uuid>` (receta declarativa: fondo = plantilla | generado | imagen subida con recorte 1:7, y texto opcional; propietario, visibilidad privado/compartido; compartido exige moderación aprobada). Nunca guarda SVG, HTML ni URLs: las imágenes son ids de archivos ya recodificados.
- **Marca** (`marca.schema.json`): usuario, texto (`gutenberg`|`subido`, id, huella sha256 del texto normalizado), rango en puntos de código (`inicio`/`fin`, `inicio = fin` para un punto) con ancla de contexto opcional para recolocar si cambia la edición, etiqueta (texto plano ≤80) y `diseno` (solo el id). Cambiar o borrar un diseño no toca las marcas.
- `sugerencias_por_gutenberg(manifest)`: id de Gutenberg → diseños sugeribles; solo cuenta `verificado`. Hoy devuelve vacío porque ningún id está verificado.
- Decisiones por defecto (revisables): posiciones en puntos de código del texto normalizado (NFC, `\n`); una marca tiene un único diseño; los diseños de usuario no se editan, se crea uno nuevo (versionado simple).
- Siguiente: editor de marcalibros de usuario y subidas seguras (renderizado de recetas en el servidor, recodificación, cuotas, moderación).

## Editor de usuario y subidas seguras (hecho: lado servidor)
`generator/editor.py` (tests en `tests/test_editor.py`, 15 casos). No hay interfaz ni servidor HTTP en este repo: es la lógica que el servidor debe llamar.
- **Subidas** (`recodificar`, `AlmacenSubidas`): solo JPG, PNG y WebP, detectados por firma del contenido (no por nombre ni tipo declarado); ≤5 MB, ≤24 MP y ≤8000 px por lado, comprobados antes de decodificar; mínimo 50×350; sin animación; aplica orientación EXIF y descarta todo metadato; PNG con transparencia se aplana sobre blanco; **siempre se recodifica a JPEG** (máx. 4000 px) y no se guarda el original. SVG, GIF, PDF y demás se rechazan. Nombre aleatorio (`secrets.token_hex(16)`), nunca derivado del original; las rutas solo se componen con ids hexadecimales de 32 caracteres y se comprueba el propietario.
- **Cuotas por usuario:** 50 imágenes y 100 MB (constantes `CUOTA_*`, ajustables).
- **Recetas** (`renderizar_receta`): fondo `plantilla` (reconstruye el paisaje de una pieza del manifest con su semilla), `generado` (paleta, arquetipo, motivo y semilla del catálogo; usa los adornos de Romanticismo) o `imagen` (recorte 1:7 reescalado a 200×1400 o 400×2800). El texto usa la fuente vectorial de las frases y su tono se elige/valida a ≥4,5:1; si la imagen no lo permite, se añade un velo del color contrario en la franja. Todo valor desconocido (paleta, arquetipo, motivo, pieza, recorte fuera de la imagen o no 1:7) lanza `ErrorEditor`.
- **Servirlas (responsabilidad del servidor, anotado en el docstring):** otro origen sin cookies, solo como `<img>`, `Content-Type: image/jpeg`, `X-Content-Type-Options: nosniff`.
- **Moderación:** el modelo ya exige `moderacion: aprobado` para `visibilidad: compartido`; la cola de revisión humana y el motor de reglas (texto de la frase, imagen) quedan para cuando exista el servidor. Los diseños privados no necesitan moderación.
- **Pendiente:** interfaz del editor (vista previa en vivo con `renderizar_receta`), HTTP, borrado en cascada (si se borra una imagen, avisar de los diseños que la usan), moderación de contenido de imagen.

### Revisión de código del editor y el modelo (corregida)
Hallazgos tratados: fotos de móvil MPO ya se aceptan (primera imagen); si ningún tono ni velo llega a 4,5:1 el render lanza `ErrorEditor` en vez de pintar texto ilegible; `usuario` es obligatorio en `ruta` y `renderizar_receta` (no hay lectura sin comprobar propietario); índice de subidas con bloqueo `flock` y escritura atómica (cuota a prueba de subidas simultáneas); el id del diseño debe corresponder a su origen y a su pieza siempre (también sin `piezas`), así no se puede suplantar un `cat:`; los recortes se miden sobre la imagen guardada (`AlmacenSubidas.dimensiones`); el texto usa la franja de la escena (`esc.franja`) con la fija como respaldo, se centra bien sin autor y se rechaza si no cabe; PNG de 16 bits se normaliza; se quitó el `MAX_IMAGE_PIXELS` global. No se tocó el generador del catálogo: la caché sigue válida.


## Decisiones de octubre (2.ª ronda)
- **Realismo mágico:** 14 frases originales breves (`original: true`, autor «Marcalibros», estado `original`); no citan a ningún autor real. 60 de sus 100 piezas llevan frase; en total, 513 de 1000. Sustituye a la decisión pendiente de «sin frase o prestadas».
- **Gutenberg:** se amplía la red del entorno (el usuario debe permitir `gutenberg.org` y `gutendex.com`) y se ejecuta `verificar_gutenberg.py`. Pendiente de que se cambie la configuración.
- **CI:** se probará abriendo un PR (pendiente de que el usuario lo pida expresamente).
- **Contenido de memoria:** revisión solo de lo dudoso: ver `REVISION-DUDAS.md`.

## Gutenberg a mano (hecho, a la espera de que se rellene)
Sin red desde el entorno, la verificación la hace una persona: `python generator/gutenberg_manual.py lista` escribe `REVISION-GUTENBERG.csv` (100 filas de autor y 1000 de obra, con enlace de búsqueda a gutenberg.org); se rellenan `estado` (`verificado` | `no_disponible`) e `id`; `python generator/gutenberg_manual.py aplicar REVISION-GUTENBERG.csv` valida las filas y edita solo esos bloques de `taxonomia.yaml`. Después reconstruir (`build.py --all`). Probable `no_disponible`: los autores del Realismo mágico.

## Interfaz del editor (hecha, prototipo)
`editor/index.html`: página estática de un solo archivo, publicada también como Artifact (https://claude.ai/artifact/Y7pFRFCB5RY94vSxyC4nck). Fondo «paisaje generado» (12 paletas del catálogo, composición, motivo, semilla; vista aproximada en JS) o «tu imagen» (recorte 1:7 con zoom y posición, sin salir del navegador; valida tipo, 5 MB y 24 MP). Texto con elección automática del tono y velo hasta 4,5:1, como `editor.py`; miniatura 50×350. Genera el documento de diseño `usr:` y lo valida (frase 1–200, autoría ≤60, recorte 1:7, semilla 0–4294967295); el `id` de imagen es un marcador de posición. No sube nada y no tiene servidor: la receta se copia. Pendiente: pestaña «plantilla del catálogo» (necesita el manifest), vista exacta con el motor del servidor y envío real.

## Frases originales sin firma (hecho)
Las 14 frases originales del Realismo mágico se dibujan sin «— Marcalibros»: solo la frase, centrada. `texto.componer` omite el bloque de autoría si `autor` está vacío; el manifest lleva `frase.firmada` (false en las originales). Hashes de referencia regenerados.

## Otro enfoque para Gutenberg: reconocer al autor por los metadatos (hecho)
Verificar a mano 1000 ids de obra bloquea el lector. `modelo.sugerencias_por_metadatos(libro, taxonomia)` no necesita ids: recibe los metadatos del libro abierto (título, autores con años, como los da Gutendex) y reconoce al autor del catálogo por nombre (sin tildes, «Apellido, Nombre» o al revés, Virgil ~ Virgilio) y por años de vida (a. C. incluidos). Devuelve sus diseños, con primero los que se parecen al título. Si el nombre es una sola palabra y no hay años que comparar, no sugiere nada (evita falsos positivos). El flujo de ids verificados (`gutenberg_manual.py`) sigue sirviendo para el vínculo exacto obra→texto. Cubierto por tests con registros simulados; falta probarlo con la API real cuando haya red.
