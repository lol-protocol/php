# Listado de países

Tabla de 208 países y territorios con características básicas y dos juegos de índices propios:

- **Genealogía:** qué tanto le interesa la genealogía a cada país (IG) y qué tan bueno es para
  nosotros operar ahí (IO). Cada país lleva un comentario que explica sus puntajes.
- **Etiquetador POS** (partes del discurso): los mismos dos conceptos, aplicados a un
  etiquetador gramatical: qué tanto interés lector hay en la lengua del país (IL) y qué tan
  viable es construir o usar un etiquetador ahí (IP), medido por las palabras anotadas que
  existen en cada lengua.

Además estima la **población histórica acumulada**: cuántas personas han nacido en cada país
desde la prehistoria.

**Para consultarla:** abre `vista.html` en un navegador (mapa coloreado por prioridad, filtros y
tabla ordenable), o usa `priorizacion.csv` desde otro programa. Las tablas en Markdown
(`tabla_genealogia.md` y `tabla_pos.md`) tienen todas las columnas.

## Archivos

- `paises.csv`: datos generales, una fila por país. Es la fuente de verdad de genealogía.
- `paises_pos.csv`: campos del etiquetador POS, unidos a `paises.csv` por `codigo`.
- `niveles_lenguas.csv`: cada lengua con sus códigos ISO 639-3, sus palabras anotadas, su texto
  web y su nivel (1 a 5). De ahí salen IP (el máximo de las lenguas de un país) e IP local (la
  primera lengua).
- `generar_tabla.py`: valida los CSV y genera `tabla_genealogia.md`, `tabla_pos.md`,
  `priorizacion.csv` y `vista.html`.
- `priorizacion.csv`: una fila por país con las dos prioridades, si está excluido y por qué.
  Pensado para usarlo desde otro programa.
- `vista.html`: la vista web, generada desde `vista_plantilla.html` con los datos y el mapa
  incrustados. Funciona sin servidor; solo las fuentes tipográficas vienen de internet.
- `preparar_lenguas.py`: mide los recursos de cada lengua (ver «Fuentes») y escribe las columnas
  medidas de `niveles_lenguas.csv` y los IP de `paises_pos.csv`.
- `preparar_mapa.py`: proyecta las fronteras de Natural Earth y escribe `mapa_paises.csv`, los
  trazados que usa la vista.
- `preparar_datos.py`: lee las fuentes de Our World in Data, Gapminder y el Banco Mundial (ver
  «Fuentes») y escribe `poblacion_larga.csv`, `natalidad.csv` y las columnas de datos de
  `paises.csv`.
- `codigos_iso.csv`: código ISO alfa-2 del repositorio y su alfa-3, con el que se cruzan Our World
  in Data, Gapminder y el Banco Mundial (Kosovo es `OWID_KOS`, y `XKX` en el Banco Mundial). Se
  generó con `pycountry` para que los scripts no dependan de él.
- `poblacion_larga.csv` y `natalidad.csv`: datos de entrada de la población histórica, generados
  por `preparar_datos.py`.
- `historia_poblacion.py`: calcula los nacidos en la historia de cada país a partir de esos dos
  archivos, y escribe la columna de `paises.csv` y `poblacion_historica_detalle.csv` (con la banda
  de sensibilidad).
- `pruebas.py`: pruebas del proyecto. La CI las corre junto con `generar_tabla.py --comprobar`
  (`.github/workflows/pruebas-listado-paises.yml`).

`tabla_genealogia.md`, `tabla_pos.md`, `priorizacion.csv` y `vista.html` son salida generada — no se editan a mano. Se regeneran con `python3 generar_tabla.py` (`--comprobar` solo valida que estén al día).

```bash
python3 generar_tabla.py              # valida y regenera las tablas, priorizacion.csv y vista.html
python3 generar_tabla.py --comprobar  # valida y falla si alguna salida no está al día (no escribe)
python3 -m unittest -v pruebas        # pruebas
python3 preparar_datos.py ...         # datos de los países; ver «Fuentes»
python3 historia_poblacion.py         # recalcula los nacidos en la historia
python3 preparar_lenguas.py ...       # recursos de cada lengua; ver «Fuentes»
python3 preparar_mapa.py ...          # trazados del mapa; ver «Fuentes»
```

Todos los scripts usan solo la biblioteca estándar de Python 3. `generar_tabla.py` se detiene con
un mensaje si una fila está mal: encabezado distinto, índice fuera de 1–5, código repetido o que
no coincida entre los archivos, nivel de una lengua que no sale de sus palabras anotadas, o un IL
o IO por encima del tope que fijan sus datos (ver las rúbricas).

## Alcance

193 Estados miembros de la ONU, más Taiwán, Palestina, Kosovo, Hong Kong, Macao y Puerto Rico
(marcados como `territorio` o `entidad disputada`), y nueve territorios con valor genealógico:
Bermudas, Curazao, Gibraltar, Groenlandia, Guernsey, Isla de Man, Islas Feroe, Jersey y Reunión.
Varios tienen registros excelentes y diáspora, pero pocas fuentes publican sus datos: no tienen
IDH ni PIB PPA, y Guernsey, Jersey y Reunión tampoco población actual. No incluye la Ciudad del
Vaticano ni otros territorios menores.

## Columnas de `paises.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Código ISO 3166-1 alfa-2, como en el catálogo de `cobros-ingresos-funnels/`. Kosovo usa `XK`, que no es ISO oficial. |
| `pais` | Nombre en español. |
| `tipo` | `estado`, `territorio` o `entidad disputada`. |
| `region` | África, América, Asia, Europa u Oceanía. |
| `poblacion_millones` | Población de 2024, en millones, de Our World in Data (basada en ONU, *World Population Prospects 2024*). Gibraltar, Isla de Man y Puerto Rico, que esa fuente no incluye, usan la del Banco Mundial (`SP.POP.TOTL`). Vacía en Guernsey, Jersey y Reunión. |
| `poblacion_historica_acumulada` | Personas nacidas en el territorio del país desde el 10 000 a. C., en millones. Es una estimación (ver «Población histórica acumulada»). |
| `idh` | Índice de Desarrollo Humano de **2023**, del PNUD (*Informe sobre Desarrollo Humano 2025*), vía Gapminder. Vacía donde el PNUD no lo publica: 6 países y los 9 territorios nuevos. |
| `pib_per_capita_usd` | PIB per cápita **nominal** de 2024 en dólares corrientes, del Banco Mundial (`NY.GDP.PCAP.CD`). Si falta 2024, el del último año desde 2021; si no, vacía (ver «Estado de los datos»). |
| `pib_per_capita_ppa_usd` | PIB per cápita en **paridad de poder adquisitivo** de 2023, en dólares internacionales de 2021 (Gapminder, basado en el Banco Mundial). Vacía en Kosovo, Liechtenstein, Macao, Puerto Rico y los 9 territorios nuevos. |
| `alfabetizacion_adultos` | Porcentaje de mayores de 15 años que saben leer y escribir (UNESCO, vía Gapminder), del último año desde 2010. Vacía en 73, casi todos de ingreso alto: la UNESCO no la mide donde es casi universal. |
| `usuarios_internet` | Porcentaje de la población que usa internet (Banco Mundial, `IT.NET.USER.ZS`, vía Gapminder), del último año desde 2020; casi todos son de 2024. Vacía en 21. |
| `emigrantes_pct` | Personas nacidas en el país que viven en otro, en porcentaje de la población residente, de 2024 (ONU, *International Migrant Stock*, vía Gapminder). Mide la diáspora que puede buscar sus raíces. Vacía en 12, entre ellos los territorios nuevos. |
| `indice_genealogia` | IG, de 1 a 5. |
| `indice_operabilidad` | IO, de 1 a 5. |
| `comentarios` | Motivo de los puntajes de genealogía. |

## Columnas de `paises_pos.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Igual que en `paises.csv`. |
| `idioma_principal` | Las lenguas de uso amplio en textos escritos, con **la lengua local primero**: la local más las lenguas francas (inglés, ruso, francés, árabe estándar) que el comentario de genealogía ya nombra. |
| `indice_lectura` | IL, de 1 a 5. |
| `indice_pos` | IP (mejor lengua), de 1 a 5: el nivel de la lengua con más recursos entre las listadas. Lo escribe `preparar_lenguas.py`. |
| `indice_pos_local` | IP local, de 1 a 5: el nivel de la primera lengua listada, la local. Lo escribe `preparar_lenguas.py`. |
| `comentarios_pos` | Observaciones sobre la lectura (alfabetización, mercado editorial, segmentación del texto). Si IP sale de otra lengua que la local, empieza con «IP por <lengua> (mejor recurso listado).», que también escribe `preparar_lenguas.py`. |

## Columnas de `niveles_lenguas.csv`

| Columna | Qué es |
|---|---|
| `lengua` | Nombre en español, como aparece en `idioma_principal`. |
| `codigos` | Códigos ISO 639-3, separados por espacios. Hay varios cuando la lengua tiene variedades estándar que comparten la escritura y los recursos (serbio, croata, bosnio y montenegrino; persa y dari; malayo e indonesio; noruego bokmål y nynorsk; chino), o cuando el nombre agrupa varias lenguas (lenguas mayas, bereber, quechua). |
| `palabras_anotadas` | Palabras con etiqueta gramatical en Universal Dependencies más las de MasakhaPOS, sumando todos los códigos. |
| `treebanks_ud` | Número de treebanks de Universal Dependencies. |
| `paginas_web_pct` | Porcentaje de las páginas de Common Crawl en la lengua. Vacío si Common Crawl no la detecta. |
| `nivel` | Sale de `palabras_anotadas` (ver la rúbrica de IP). |

## Genealogía: índices IG e IO

**IG (interés en genealogía, 1–5).** Demanda y oferta: cuánto interesa el linaje en el país
(tradición y diáspora que busca sus raíces) y qué tan buenos son sus registros civiles y
parroquiales, digitalizados y accesibles.

- 5: demanda alta y registros excelentes y accesibles en línea.
- 4: demanda alta, o registros muy buenos con acceso parcial.
- 3: interés medio, o registros buenos pero dispersos.
- 2: interés bajo, o registros escasos o restringidos.
- 1: prácticamente nulo, o sin acceso a los registros.

**IO (operabilidad, 1–5).** Qué tan bueno es operar ahí: estabilidad política, sanciones, pagos e
internet y marco legal de datos personales. El idioma no entra en IO: ninguno de sus puntajes lo usa.

- 5: estable, sin sanciones, con pagos y datos sin fricción.
- 4: operable con fricciones menores (burocracia, regulación de datos exigente).
- 3: operable con cuidado (riesgo político o cambiario, pagos o conectividad limitados).
- 2: difícil (sanciones parciales, control estatal de internet, inestabilidad).
- 1: inviable (sanciones amplias, guerra o control total).

**Tope por datos:** con menos del 60 % de usuarios de internet, IO es como máximo 3
(`TOPE_IO_INTERNET`). Hoy ningún país lo supera; deja escrita la regla que bajó a Botsuana (ver
abajo).

**La columna de emigrantes** sirve para contrastar la parte de diáspora de IG. No cambia ningún
puntaje por sí sola.

**Prioridad** = IG × IO (1 a 25). Ordena la tabla; no es un tercer índice.

**Excluido** = IO ≤ 1. Quedan fuera **23 países**, cerca del objetivo de unos 20: Afganistán,
Bielorrusia, Burkina Faso, Corea del Norte, Cuba, Eritrea, Haití, Irán, Libia, Malí, Myanmar,
Níger, República Centroafricana, República Democrática del Congo, Rusia, Siria, Somalia, Sudán,
Sudán del Sur, Turkmenistán, Venezuela, Yemen y Palestina.

Nicaragua tenía IO = 1 y ahora tiene IO = 2: sus sanciones son parciales y sus factores (control
de medios e internet) son los mismos que los de Vietnam, que la rúbrica ya marca como «difícil».

Camboya y Vietnam tienen IO = 2 («difícil»): sus índices los marcan como difíciles, no como
inviables, así que con este umbral **siguen dentro**. Esa fue la decisión tomada. Para sacarlos
habría que bajar su IO a 1, lo que la rúbrica no justifica, o subir el umbral a IO ≤ 2, que deja
fuera 54 países. El umbral está en `UMBRAL_GENEALOGIA` de `generar_tabla.py`.

**IO sin factor en el comentario (resuelto).** Había 18 países con IO 2 o 3 cuyo comentario no
nombraba ningún factor de operabilidad. En 13 se añadió el factor al comentario y se mantuvo el
puntaje: pagos o conectividad limitados (Benín, Comoras, Costa de Marfil, Gambia, Liberia, Santo
Tomé y Príncipe, Sierra Leona, Islas Marshall y Samoa), golpe militar en 2023 (Gabón), riesgo
regional (Jordania), fronteras cerradas (Armenia) y crisis de deuda (Surinam). Los otros cinco no
tenían ningún factor y subieron de 3 a 4, como pide la rúbrica: Albania, Belice, Montenegro,
Paraguay y República Dominicana.

**Botsuana** bajó de IO 4 a IO 3 al cruzar IO con los usuarios de internet: tiene un 57 %, el más
bajo de los IO 4 (el siguiente, Namibia, tiene 65 %), y la conectividad limitada es una fricción
mayor. El comentario lo dice.

## Etiquetador POS: índices IL e IP

**IL (interés en lectura, 1–5).** Equivale a IG para la lengua: alfabetización, hábito lector y
mercado editorial.

- 5: alfabetización casi universal, hábito lector fuerte, mercado editorial amplio.
- 4: alfabetización alta y mercado editorial activo.
- 3: alfabetización media o alta, con mercado editorial pequeño o desigual.
- 2: alfabetización baja o mercado editorial muy reducido.
- 1: alfabetización muy baja, o casi no hay libros en la lengua.

**Tope por alfabetización** (`TOPES_IL`): la alfabetización de adultos fija el IL máximo. Sin dato
de la UNESCO no hay tope.

| Alfabetización | Se llama | IL máximo |
|---|---|---|
| menos del 50 % | muy baja | 1 |
| del 50 al 75 % | baja | 2 |
| del 75 al 90 % | media | 3 |
| del 90 al 97 % | alta | 4 |
| 97 % o más | casi universal | 5 |

Al fijar los topes bajaron nueve IL: Angola, Bután, Camboya, Camerún, Madagascar, Marruecos, Nepal
y la República Democrática del Congo de 3 a 2, e Irán de 4 a 3.

**IP (operabilidad para un etiquetador POS, 1–5).** Equivale a IO para la lengua. Mide los
recursos abiertos para entrenar y evaluar el etiquetador, **contados, no estimados**: las palabras
con etiqueta gramatical en Universal Dependencies (versión 2.18) y en MasakhaPOS (20 lenguas
africanas). **No usa sanciones ni política**: eso lo cubre IO.

| Palabras anotadas | Nivel | Qué significa |
|---|---|---|
| 500 000 o más | 5 | recursos abundantes, con treebanks grandes |
| 100 000 o más | 4 | recursos moderados |
| 10 000 o más | 3 | recursos pequeños pero utilizables |
| alguna | 2 | recursos mínimos |
| ninguna | 1 | sin corpus anotado abierto |

Los umbrales están en `UMBRALES_NIVEL` de `generar_tabla.py`. Hoy hay 21 lenguas en el nivel 5, 26
en el 4, 35 en el 3, 11 en el 2 y 70 en el 1. El porcentaje de texto web (Common Crawl) va al lado
como contexto: no cambia el nivel. Antes los niveles venían de memoria; entre la medición y el
nuevo orden de lenguas (árabe y criollos, ver abajo) cambió el IP local de 93 de los 199 países que
ya estaban. Varias descripciones viejas resultaron falsas (el islandés, por ejemplo, tiene 1,2
millones de palabras anotadas, no «un treebank pequeño»), así que se quitaron de
`comentarios_pos` las frases sobre recursos.

Hay **dos versiones**, porque responden a preguntas distintas:

- **IP (mejor lengua):** el nivel de la lengua con más recursos entre las listadas en
  `idioma_principal`. Dice si se puede etiquetar *algún* texto del país con recursos existentes.
  Casi todos los países listan inglés, francés, español o portugués, así que **181 de 208** tienen
  IP = 5 (y 16 con 4, 7 con 3, 1 con 2 y 3 con 1). Por eso casi no distingue entre países.
- **IP local:** el nivel de la primera lengua listada, que es la lengua local. Dice si se puede
  etiquetar el texto que el país escribe en su propia lengua. Se reparte mejor: 107 países con 5,
  23 con 4, 31 con 3, 7 con 2 y 40 con 1.

**La prioridad y la exclusión POS usan IP local** (decisión tomada). **Prioridad POS** =
IL × IP local. **Excluido POS** = IP local ≤ 1, es decir, la lengua local no tiene ninguna palabra
anotada en esas fuentes. Con los niveles medidos quedan fuera **40 países y territorios** (antes,
con los niveles de memoria, 13): Burundi (kirundi), Bután (dzongkha), Camboya (jemer), Chad
(árabe chadiano), Comoras (shikomor), Congo (lingala), Curazao (papiamento), Eritrea (tigriña),
Fiyi (fiyiano), Gambia (mandinga), Groenlandia (groenlandés), Guinea (pular), Guinea Ecuatorial
(fang), Islas Marshall (marshalés), Islas Salomón (pijin de las Salomón), Kiribati (gilbertés),
Laos (lao), Lesoto (sesoto), Madagascar (malgache), Maldivas (dhivehi), Micronesia (chuukés),
Mongolia (mongol), Myanmar (birmano), Namibia (oshiwambo), Nauru (nauruano), Palaos (palauano),
Papúa Nueva Guinea (tok pisin), República Centroafricana (sango), República Democrática del
Congo (lingala), Samoa (samoano), Somalia (somalí), Sudán del Sur (árabe juba), Tayikistán
(tayiko), Timor Oriental (tetun), Tonga (tongano), Turkmenistán (turcomano), Tuvalu (tuvaluano),
Vanuatu (bislama), Yibuti (somalí) y Zambia (bemba). El umbral está en `UMBRAL_POS`.

`generar_tabla.py` se detiene si el nivel de una lengua no sale de sus palabras anotadas, si IP no
es el máximo de las lenguas listadas, si IP local no es el nivel de la primera, si una lengua no
tiene nivel, o si el prefijo «IP por …» del comentario no corresponde.

**Qué lenguas se listan (decidido):** todas las de uso amplio en textos escritos. En la práctica,
se añadió el inglés, el ruso o el árabe estándar donde el comentario de genealogía ya los nombra
(Omán, Jordania, Uzbekistán, Turkmenistán y otros), en 30 países. Una lengua que ese comentario no
nombra no se listó, aunque se use. Eso afecta a IP, no a IP local.

**La lengua local** es la primera de cada lista. En 35 países se reordenó la lista para que la
primera fuera la lengua local y no la lengua franca (por ejemplo, hausa en Nigeria, zulú en
Sudáfrica, tok pisin en Papúa Nueva Guinea). Donde solo se listó una lengua franca (por ejemplo,
Benín o Gabón, que listan solo el francés), la lengua local queda sin representar y IP local
coincide con IP.

**Árabe:** en los países de la Liga Árabe la lengua local escrita es el árabe estándar, y el
dialecto va después. Egipto, Mauritania, Palestina, Siria, Sudán, Túnez y Yemen listaban primero
el dialecto, al revés que Argelia, Irak o Marruecos; se igualaron, y a Siria, que solo listaba el
dialecto, se le añadió el árabe estándar. Con los niveles medidos, los dialectos casi no tienen
palabras anotadas, así que sin este cambio esos siete países quedaban excluidos. Chad y Sudán del
Sur, fuera de la Liga, conservan el árabe chadiano y el árabe juba como lengua local.

**Criollos** (decidido): donde el texto escrito está sobre todo en la lengua oficial, esa va
primero y el criollo después, con el mismo criterio que el árabe. Así se reordenaron Dominica,
Guinea-Bisáu, Jamaica, Mauricio, Reunión, San Vicente y las Granadinas, Santa Lucía, Seychelles y
Sierra Leona, que dejaron de estar excluidos. Haití y Curazao conservan el criollo primero (el
criollo haitiano y el papiamento), porque son lenguas oficiales y se escriben.

**Corea del Norte** tiene IP = 5: su lengua (coreano) tiene recursos abiertos. Su acceso
restringido al texto del país es un problema de IO, no de IP, como dice la definición de arriba.

**IL** es juicio propio, con el tope por alfabetización de arriba. La tabla POS muestra al lado la
alfabetización y los usuarios de internet, con fuente, para contrastarlo. Al cruzarlos con la
rúbrica, Burundi (71 % de alfabetización), Benín (51 %) y Somalia (54 %) pasaron de IL 1 a IL 2.

## Población histórica acumulada

**Qué mide:** el número de personas que han nacido en el territorio del país desde el 10 000 a. C.
Así China o Irán, con milenios de población densa, quedan muy por encima de Australia o
Sudamérica, que tuvieron poblaciones muy pequeñas durante casi toda la historia.

**Método:** `N = Σ P(t) × b(t)`, sumando año por año (`historia_poblacion.py`).

- `P(t)`: población del año t, interpolada entre los puntos de la serie larga. Entre dos puntos
  con población positiva la interpolación es geométrica (el crecimiento es aproximadamente
  exponencial).
- `b(t)`: tasa bruta de natalidad, como fracción (40 por mil = 0,040). Desde 1800, la del país
  (`natalidad.csv`); antes, la de su época (ver abajo).
- Banda de sensibilidad: `N` se recalcula con `b` × 0,9 y `b` × 1,1, y el resultado queda en
  `poblacion_historica_detalle.csv`.

**Serie larga de cada país** (`preparar_datos.py`, método compuesto, decidido). Cada fuente cubre
solo los años anteriores al primero de las fuentes de arriba:

- **Our World in Data:** población anual hasta 2024. Empieza en 1850 en la mayoría de los países
  (132) y en 1750 en 35.
- **Maddison 2020:** sus puntos anteriores (años 1, 1000, 1500, 1600, 1700, 1820…). Aporta en 77
  países; en 45, alguno anterior a 1750.
- **Clio-Infra (2016):** puntos cada 50 años desde 1500, en 121 países. Se empalma: se escala para
  coincidir con la fuente de arriba en su primer año (el factor casi siempre es 1, porque
  Clio-Infra coincide con Maddison y Our World in Data donde se solapan). Si difieren más de un
  50 %, se descarta: Bolivia, Chipre, Dominica y Fiyi.
- **Antes del primer año conocido:** la población de la región del país según HYDE (seis
  regiones), multiplicada por la cuota del país en esa región en su primer año conocido. Supone
  que esa cuota fue constante hacia atrás.

**Natalidad desde 1800** (`natalidad.csv`):

- **1800 a 2015:** la tasa bruta de natalidad de Gapminder, año por año. Desde 1950 se basa en la
  ONU (*World Population Prospects*); antes, en las series históricas que compila Gapminder.
- **2016 a 2025:** la tasa de 2015 escalada por la fecundidad de Gapminder (basada en la ONU):
  `tasa(t) = tasa(2015) × fecundidad(t) / fecundidad(2015)`. Macao y Puerto Rico, sin esa
  fecundidad, mantienen la tasa de 2015.
- **Sin tasa de Gapminder** (Andorra, Dominica, Islas Marshall, Liechtenstein, Mónaco, Nauru,
  Palaos, San Cristóbal y Nieves, San Marino y Tuvalu): 7 × su fecundidad, una aproximación de la
  tasa bruta. Kosovo, sin ninguna de las dos, usa la de su época.

**Natalidad antes de 1800, por épocas** (decidido), como el Population Reference Bureau en *How
Many People Have Ever Lived on Earth?*: 80 por mil antes del año 1, 60 hasta 1650, 50 hasta 1750 y
40 desde entonces. Las tasas altas de la antigüedad reflejan una mortalidad infantil enorme: para
que la población apenas creciera, tenían que nacer muchos. **Las cifras se cargaron de memoria**,
porque el sitio del PRB no era accesible; conviene contrastarlas (`EPOCAS` en
`historia_poblacion.py`).

**Reglas del cálculo:**

- Si una serie repite un año, el script se detiene con error. No elige uno en silencio.
- Si una serie termina antes del año 2000, el país se salta y queda vacío. No se extiende la
  última población hasta 2025, ni se conserva un valor de un cálculo anterior.

**Resultados y comprobación de cordura.** El total mundial da unos **90 200 millones** de
personas nacidas desde el 10 000 a. C. Los mayores son India (22 800 millones) y China (20 100
millones). Irán (1 150), Egipto (1 470) y Japón (1 670) suman muchas veces su población actual, y
Australia (350) casi toda en su población aborigen. El Population Reference Bureau estima unos
107 000 millones con las mismas natalidades por época; la diferencia viene sobre todo de que el
PRB cuenta desde la aparición del ser humano moderno, decenas de miles de años antes del 10 000
a. C., y de sus propias estimaciones de población. Con una natalidad fija de 40 por mil, el total
daba 61 600 millones. **Las cifras sirven para comparar países entre sí, no como conteo exacto.**

**Limitaciones que hay que conocer:**

- La retroproyección con cuota constante es gruesa: un país que antes tuvo más peso en su región
  (por ejemplo, Irak o Egipto frente al resto de Asia y África) queda subestimado, y uno que lo
  ganó después queda sobreestimado. Por eso importa que el primer año conocido sea antiguo: con
  Clio-Infra, la cuota de Argentina o Chile se toma de 1500 y no de 1820, cuando ya reflejaba la
  conquista y la inmigración, y sus nacidos en la historia bajaron a la mitad (de unos 200 a unos
  100 millones).
- La banda de ±10 % solo cubre la natalidad, no las poblaciones de hace miles de años.
- Antes de 1800 la natalidad es la misma para todos los países en cada época. Es lo que más pesa
  en el total: con 40 por mil fijo, el total mundial baja un tercio.
- Puerto Rico usa Maddison de 1820 a 1959 y el Banco Mundial desde 1960, porque Our World in Data
  no lo incluye. Gibraltar e Isla de Man usan el Banco Mundial desde 1960. Jersey y Reunión usan la
  población de Gapminder hasta 2015 (después es proyección). Guernsey no tiene ninguna serie y
  queda vacío.

## Fuentes

Los dominios de las fuentes (`ourworldindata.org`, `api.worldbank.org`, `hdr.undp.org`) están
bloqueados por el proxy del entorno donde se trabajó: responden 403. Por eso se usaron copias
publicadas en GitHub, que sí son accesibles: los repositorios de Our World in Data, los de
Gapminder (`open-numbers`) y los de `datasets` (de datahub.io), que copian los indicadores del
Banco Mundial.

- [`owid/co2-data`](https://github.com/owid/co2-data), commit `382ee6c` (2 de junio de 2026),
  archivo `owid-co2-data.csv`: población por país hasta 2024.
- [`owid/owid-datasets`](https://github.com/owid/owid-datasets), commit `6155d4c` (14 de abril de
  2025): *Maddison Project Database 2020*, *Global Population by Region (HYDE 2016 & UN 2017)* y
  *Population (Clio-infra (2016)), with Island of Ireland (Rep. + Northern)*.
- [`open-numbers/ddf--gapminder--systema_globalis`](https://github.com/open-numbers/ddf--gapminder--systema_globalis),
  commit `ee19060` (7 de mayo de 2026): tasa bruta de natalidad por país de 1800 a 2015
  (`crude_birth_rate_births_per_1000_population`) e IDH del PNUD de 1990 a 2023
  (`hdi_human_development_index`), alfabetización de adultos de la UNESCO
  (`literacy_rate_adult_total_percent_of_people_ages_15_and_above`), usuarios de internet del
  Banco Mundial (`internet_users`) y población histórica (`total_population_with_projections`, solo
  para la serie larga de Jersey y Reunión).
- [`open-numbers/ddf--gapminder--fasttrack`](https://github.com/open-numbers/ddf--gapminder--fasttrack),
  commit `dee8978` (15 de octubre de 2026): fecundidad por país de 1800 a 2100
  (`children_per_woman_total_fertility`), PIB per cápita PPA en dólares internacionales de 2021
  (`gdp_pcap`, versión 32, basado en el Banco Mundial) y emigrantes por país de origen
  (`emigrant_n`, de la ONU).
- [`datasets/world-development-indicators`](https://github.com/datasets/world-development-indicators),
  commit `4ebec5a` (1 de julio de 2026), archivo `indicators/ny.gdp.pcap.cd/data.csv`: PIB per
  cápita en dólares corrientes del Banco Mundial (`NY.GDP.PCAP.CD`), hasta 2025.
- [`datasets/population`](https://github.com/datasets/population), commit `d4b1a4a` (1 de octubre
  de 2026), archivo `data/population.csv`: población del Banco Mundial (`SP.POP.TOTL`), hasta 2025.

Para los recursos de las lenguas y el mapa:

- [Universal Dependencies](https://universaldependencies.org/), versión 2.18 (15 de mayo de
  2026): el `stats.xml` de cada uno de sus 353 treebanks, en la etiqueta `r2.18` de cada
  repositorio. La lista de treebanks (`valdan/releases.json`) y los códigos de cada lengua
  (`codes_and_flags.yaml`) son de
  [`UniversalDependencies/docs-automation`](https://github.com/UniversalDependencies/docs-automation),
  commit `dd6111b` (17 de octubre de 2026).
- [`masakhane-io/masakhane-pos`](https://github.com/masakhane-io/masakhane-pos), commit `376f416`
  (25 de junio de 2025): MasakhaPOS, carpeta `data/`.
- [`commoncrawl/cc-crawl-statistics`](https://github.com/commoncrawl/cc-crawl-statistics), commit
  `ea9a79e` (17 de octubre de 2026), archivo `plots/languages.csv`: porcentaje de páginas por
  lengua del rastreo `CC-MAIN-2026-39` (`CRAWL` en `preparar_lenguas.py`).
- [`nvkelso/natural-earth-vector`](https://github.com/nvkelso/natural-earth-vector), commit
  `ca96624` (2 de junio de 2022), archivo `geojson/ne_50m_admin_0_countries.geojson`: fronteras de
  Natural Earth (dominio público), escala 1:50 millones.

Para reproducir `poblacion_larga.csv`, `natalidad.csv` y las columnas de `paises.csv`:

```bash
git clone --depth 1 https://github.com/owid/co2-data
git clone --depth 1 --filter=blob:none --no-checkout https://github.com/owid/owid-datasets
d="owid-datasets"; m="Maddison Project Database 2020 (Bolt and van Zanden (2020))"
h="Global Population by Region with projections - HYDE (2016) & UN (2017)"
git -C $d show "HEAD:datasets/$m/$m.csv" > maddison-2020.csv
git -C $d show "HEAD:datasets/$h/$h.csv" > hyde-regiones.csv
c="Population (Clio-infra (2016)), with Island of Ireland (Rep. + Northern)"
git -C $d show "HEAD:datasets/$c/$c.csv" > clio-infra.csv
r=https://raw.githubusercontent.com
sg=$r/open-numbers/ddf--gapminder--systema_globalis/ee190605fdec9d1c35c38e18e869a400a5818fe6/countries-etc-datapoints
ft=$r/open-numbers/ddf--gapminder--fasttrack/dee8978535ee563526704d2793497f8537280469/countries_etc_datapoints
curl -so natalidad-gapminder.csv $sg/ddf--datapoints--crude_birth_rate_births_per_1000_population--by--geo--time.csv
curl -so idh-gapminder.csv $sg/ddf--datapoints--hdi_human_development_index--by--geo--time.csv
curl -so alfabetizacion-gapminder.csv \
    $sg/ddf--datapoints--literacy_rate_adult_total_percent_of_people_ages_15_and_above--by--geo--time.csv
curl -so internet-gapminder.csv $sg/ddf--datapoints--internet_users--by--geo--time.csv
curl -so fecundidad-gapminder.csv $ft/ddf--datapoints--children_per_woman_total_fertility--by--country--time.csv
curl -so pib-ppa-gapminder.csv $ft/ddf--datapoints--gdp_pcap--by--country--time.csv
curl -so emigrantes-gapminder.csv $ft/ddf--datapoints--emigrant_n--by--country--time.csv
curl -so poblacion-gapminder.csv $sg/ddf--datapoints--total_population_with_projections--by--geo--time.csv
curl -so pib-pc.csv $r/datasets/world-development-indicators/4ebec5a2273f531ba238a34dbe440394dc5a6c4b/indicators/ny.gdp.pcap.cd/data.csv
curl -sO $r/datasets/population/d4b1a4a9a17e2123baf0ef119de05bceced1ab1b/data/population.csv
python3 preparar_datos.py --co2 co2-data/owid-co2-data.csv --maddison maddison-2020.csv \
    --hyde hyde-regiones.csv --clio clio-infra.csv --natalidad-gapminder natalidad-gapminder.csv \
    --fecundidad-gapminder fecundidad-gapminder.csv --idh idh-gapminder.csv \
    --alfabetizacion alfabetizacion-gapminder.csv --internet internet-gapminder.csv \
    --emigrantes emigrantes-gapminder.csv --poblacion-gapminder poblacion-gapminder.csv \
    --pib-ppa pib-ppa-gapminder.csv --pib-pc-bm pib-pc.csv --poblacion-bm population.csv
python3 historia_poblacion.py && python3 generar_tabla.py
```

Para reproducir las columnas medidas de `niveles_lenguas.csv`, los IP y `mapa_paises.csv`:

```bash
git clone --depth 1 --filter=blob:none --no-checkout https://github.com/UniversalDependencies/docs-automation
git -C docs-automation show dd6111b:codes_and_flags.yaml > codes_and_flags.yaml
git -C docs-automation show dd6111b:valdan/releases.json \
    | python3 -c "import json,sys; print('\n'.join(json.load(sys.stdin)['releases']['2.18']['treebanks']))" \
    > treebanks.txt
mkdir -p ud-stats && xargs -P 8 -I{} curl -sfo ud-stats/{}.xml \
    https://raw.githubusercontent.com/UniversalDependencies/{}/r2.18/stats.xml < treebanks.txt
git clone https://github.com/masakhane-io/masakhane-pos && git -C masakhane-pos checkout 376f416
curl -so languages.csv https://raw.githubusercontent.com/commoncrawl/cc-crawl-statistics/ea9a79ea09b492cd2a0afcafbe93daf8fee45564/plots/languages.csv
python3 preparar_lenguas.py --ud-stats ud-stats --ud-codigos codes_and_flags.yaml \
    --masakhapos masakhane-pos/data --common-crawl languages.csv
curl -sO https://raw.githubusercontent.com/nvkelso/natural-earth-vector/ca96624a56bd078437bca8184e78163e5039ad19/geojson/ne_50m_admin_0_countries.geojson
python3 preparar_mapa.py --natural-earth ne_50m_admin_0_countries.geojson
python3 generar_tabla.py
```

Cuando los dominios estén accesibles, conviene reemplazar la serie compuesta por la serie por país
de Our World in Data desde el 10 000 a. C., y extender la natalidad después de 2015 con la de la
ONU o el Banco Mundial (`SP.DYN.CBRT.IN`) en vez de escalarla.

## Estado de los datos

Léelo antes de usar la tabla para decidir.

- **Población 2024: de fuente.** Our World in Data (ONU, WPP 2024) en 202; Gibraltar, Isla de Man
  y Puerto Rico, del Banco Mundial. Vacía en Guernsey, Jersey y Reunión.
- **IDH: de fuente**, de 2023 (PNUD, *Informe 2025*, vía Gapminder). Coincide con valores
  conocidos de ese informe (Islandia 0,972; Noruega y Suiza 0,970; Sudán del Sur 0,388). Vacío
  donde el PNUD no publica IDH: Corea del Norte, Kosovo, Macao, Mónaco, Puerto Rico, Taiwán y los
  nueve territorios nuevos.
- **PIB per cápita nominal: de fuente** (Banco Mundial, `NY.GDP.PCAP.CD`), de 2024. Usan 2023
  Groenlandia, Isla de Man, San Marino y Tuvalu, y 2022 Siria, porque el Banco Mundial no tiene un
  año posterior. Vacío en:
  - Corea del Norte, Taiwán, Gibraltar, Guernsey, Jersey y Reunión, que el Banco Mundial no publica
    por separado.
  - Cuba, Eritrea, Sudán del Sur y Yemen, cuyo último dato es anterior a 2021.

  Antes se calculaba como PIB entre población, que fallaba en Chipre: el PIB cubre solo la zona que
  controla la República y la población, toda la isla.
- **PIB per cápita PPA: de fuente** (Gapminder, basado en el Banco Mundial), de 2023 y en dólares
  internacionales de 2021. Viene ya por persona. Antes se
  usaba Maddison 2023 (vía Our World in Data), que dejaba 35 vacíos. Mónaco (unos 463 000) es una
  estimación de Gapminder: el Banco Mundial no publica su PIB en PPA. Vacío en 13, entre ellos los
  territorios nuevos.
- **Población histórica acumulada: estimada** con el método compuesto de arriba. Sirve para
  comparar países, no como conteo exacto.
- **Alfabetización, internet y emigrantes: de fuente** (UNESCO, Banco Mundial y ONU, vía
  Gapminder). En alfabetización e internet el año varía por país; es el último disponible.
- **IP: medido** con las palabras anotadas de Universal Dependencies y MasakhaPOS. Solo cuenta
  corpus abiertos con etiqueta gramatical: una lengua con otros recursos (diccionarios, corpus sin
  anotar, modelos) queda en el nivel 1 si no tiene ninguno de esos.
- **IG, IO e IL:** son juicio propio, con las rúbricas de arriba y los topes por datos. No son
  datos oficiales. Las menciones a la alfabetización en `comentarios_pos` coinciden con la columna
  de alfabetización.

## Pendientes

1. **Población histórica:** reemplazar la serie compuesta por la de Our World in Data por país
   desde el 10 000 a. C., y la natalidad posterior a 2015 por la de la ONU o el Banco Mundial,
   cuando haya acceso.
2. **Natalidad antes de 1800:** contrastar con el PRB las tasas por época, que se cargaron de
   memoria.
3. **Revisar los índices** IG, IO e IL con la persona que decide. La rúbrica es el criterio.
4. **Umbral de exclusión de genealogía:** con IO ≤ 1 quedan 23 países (vigente); con IO ≤ 2,
   54. Camboya y Vietnam siguen dentro, por decisión tomada.
5. **Lenguas francas sin listar:** una lengua que el comentario de genealogía no nombra no se
   listó, aunque se use (el inglés en Maldivas, por ejemplo). Afecta a IP, no a IP local.
