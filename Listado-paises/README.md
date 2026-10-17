# Listado de países

Tabla de países con características básicas y dos juegos de índices propios:

- **Genealogía:** qué tanto le interesa la genealogía a cada país (IG) y qué tan bueno es para
  nosotros operar ahí (IO). Cada país lleva un comentario que explica sus puntajes.
- **Etiquetador POS** (partes del discurso): los mismos dos conceptos, aplicados a un
  etiquetador gramatical: qué tanto interés lector hay en la lengua del país (IL) y qué tan
  viable es construir o usar un etiquetador ahí (IP), según sus recursos lingüísticos.

Además estima la **población histórica acumulada**: cuántas personas han nacido en cada país
desde la prehistoria.

## Archivos

- `paises.csv`: datos generales, una fila por país. Es la fuente de verdad de genealogía.
- `paises_pos.csv`: campos del etiquetador POS, unidos a `paises.csv` por `codigo`.
- `niveles_lenguas.csv`: nivel de recursos de cada lengua (1 a 5). De ahí salen IP (el máximo de
  las lenguas de un país) e IP local (la primera lengua); `generar_tabla.py` comprueba que coincidan.
- `generar_tabla.py`: valida los CSV y genera `tabla_genealogia.md` y `tabla_pos.md`.
- `preparar_datos.py`: lee las fuentes de Our World in Data, Gapminder y el Banco Mundial (ver
  «Fuentes») y escribe `poblacion_larga.csv` y `natalidad.csv`, y la población 2024, el IDH y los dos PIB per
  cápita de `paises.csv`.
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

```bash
python3 generar_tabla.py              # valida y regenera las dos tablas
python3 generar_tabla.py --comprobar  # valida y falla si las tablas no están al día (no escribe)
python3 -m unittest -v pruebas        # pruebas
python3 preparar_datos.py --co2 ... --maddison ... --hyde ... --natalidad-gapminder ... \
    --fecundidad-gapminder ... --idh ... --alfabetizacion ... --internet ... \
    --pib-bm ... --poblacion-bm ...   # ver «Fuentes»
python3 historia_poblacion.py         # recalcula los nacidos en la historia
```

Todos los scripts usan solo la biblioteca estándar de Python 3. `generar_tabla.py` se detiene con
un mensaje si una fila está mal: encabezado distinto, índice fuera de 1–5, código repetido o que
no coincida entre los dos archivos.

## Alcance

193 Estados miembros de la ONU, más Taiwán, Palestina, Kosovo, Hong Kong, Macao y Puerto Rico
(marcados como `territorio` o `entidad disputada`). No incluye la Ciudad del Vaticano ni otros
territorios menores.

## Columnas de `paises.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Código ISO 3166-1 alfa-2, como en el catálogo de `cobros-ingresos-funnels/`. Kosovo usa `XK`, que no es ISO oficial. |
| `pais` | Nombre en español. |
| `tipo` | `estado`, `territorio` o `entidad disputada`. |
| `region` | África, América, Asia, Europa u Oceanía. |
| `poblacion_millones` | Población de 2024, en millones, de Our World in Data (basada en ONU, *World Population Prospects 2024*). Puerto Rico, que esa fuente no incluye, usa la del Banco Mundial (`SP.POP.TOTL`). |
| `poblacion_historica_acumulada` | Personas nacidas en el territorio del país desde el 10 000 a. C., en millones. Es una estimación (ver «Población histórica acumulada»). |
| `idh` | Índice de Desarrollo Humano de **2023**, del PNUD (*Informe sobre Desarrollo Humano 2025*), vía Gapminder. Vacía en los 6 países que el PNUD no publica. |
| `pib_per_capita_usd` | PIB per cápita **nominal** de 2023 en dólares corrientes: PIB del Banco Mundial (`NY.GDP.MKTP.CD`) entre su población. Si falta 2023, el de 2022 o 2021; si no, vacía (ver «Estado de los datos»). |
| `pib_per_capita_ppa_usd` | PIB per cápita en **paridad de poder adquisitivo** de 2022, en dólares internacionales de 2011 (Maddison Project Database 2023, vía Our World in Data). Vacía en 35 países sin dato, casi todos micro-estados. |
| `alfabetizacion_adultos` | Porcentaje de mayores de 15 años que saben leer y escribir (UNESCO, vía Gapminder), del último año desde 2010. Vacía en 64 países, casi todos de ingreso alto: la UNESCO no la mide donde es casi universal. |
| `usuarios_internet` | Porcentaje de la población que usa internet (Banco Mundial, `IT.NET.USER.ZS`, vía Gapminder), del último año desde 2020; casi todos son de 2024. Vacía en 12 países. |
| `indice_genealogia` | IG, de 1 a 5. |
| `indice_operabilidad` | IO, de 1 a 5. |
| `comentarios` | Motivo de los puntajes de genealogía. |

## Columnas de `paises_pos.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Igual que en `paises.csv`. |
| `idioma_principal` | Las lenguas de uso amplio en textos escritos, con **la lengua local primero**: la local más las lenguas francas (inglés, ruso, francés, árabe estándar) que el comentario de genealogía ya nombra. |
| `indice_lectura` | IL, de 1 a 5. |
| `indice_pos` | IP (mejor lengua), de 1 a 5: el nivel de la lengua con más recursos entre las listadas. |
| `indice_pos_local` | IP local, de 1 a 5: el nivel de la primera lengua listada, la local. |
| `comentarios_pos` | Motivo de los puntajes del etiquetador. Si el comentario empieza con «IP por …», IP sale de esa lengua. |

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
- 3: operable con cuidado (riesgo político o cambiario, pagos limitados).
- 2: difícil (sanciones parciales, control estatal de internet, inestabilidad).
- 1: inviable (sanciones amplias, guerra o control total).

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

## Etiquetador POS: índices IL e IP

**IL (interés en lectura, 1–5).** Equivale a IG para la lengua: alfabetización, hábito lector y
mercado editorial.

- 5: alfabetización casi universal, hábito lector fuerte, mercado editorial amplio.
- 4: alfabetización alta y mercado editorial activo.
- 3: alfabetización media o alta, con mercado editorial pequeño o desigual.
- 2: alfabetización baja o mercado editorial muy reducido.
- 1: alfabetización muy baja, o casi no hay libros en la lengua.

**IP (operabilidad para un etiquetador POS, 1–5).** Equivale a IO para la lengua. Mide los
recursos para entrenar y evaluar el etiquetador: corpus anotados, treebanks, diccionarios y
texto digital utilizable. **No usa sanciones ni política**: eso lo cubre IO.

- 5: recursos abiertos abundantes, con varios treebanks grandes.
- 4: varios recursos moderados.
- 3: recursos pequeños pero utilizables (un treebank o un corpus limitado).
- 2: recursos mínimos, casi sin texto digital anotable.
- 1: sin recursos abiertos en la lengua.

Hay **dos versiones**, porque responden a preguntas distintas:

- **IP (mejor lengua):** el nivel de la lengua con más recursos entre las listadas en
  `idioma_principal`. Dice si se puede etiquetar *algún* texto del país con recursos existentes.
  Casi todos los países listan inglés, francés, español o portugués, así que **160 de 199** tienen
  IP = 5 (y 25 con 4, 7 con 3, 6 con 2 y 1 con 1). Por eso casi no distingue entre países.
- **IP local:** el nivel de la primera lengua listada, que es la lengua local. Dice si se puede
  etiquetar el texto que el país escribe en su propia lengua. Se reparte mejor: 65 países con 5,
  38 con 4, 33 con 3, 50 con 2 y 13 con 1.

**La prioridad y la exclusión POS usan IP local** (decisión tomada). **Prioridad POS** =
IL × IP local. **Excluido POS** = IP local ≤ 1: quedan fuera **13 países**, aquellos cuya lengua
local no tiene recursos abiertos: Burundi (kirundi), Chad (árabe chadiano), Comoras (shikomor),
Guinea Ecuatorial (fang), Islas Marshall (marshalés), Kiribati (gilbertés), Maldivas (dhivehi),
Nauru (nauruano), Palaos (palauano), República Centroafricana (sango), Somalia (somalí),
Turkmenistán (turcomano) y Yibuti (somalí). El umbral está en `UMBRAL_POS`.

Los niveles de cada lengua están en `niveles_lenguas.csv`. `generar_tabla.py` se detiene si IP no
es el máximo de las lenguas listadas, si IP local no es el nivel de la primera, o si una lengua no
tiene nivel. Los países cuyo IP cambió por una lengua franca llevan el comentario «IP por …».

**Qué lenguas se listan (decidido):** todas las de uso amplio en textos escritos. En la práctica,
se añadió el inglés, el ruso o el árabe estándar donde el comentario de genealogía ya los nombra
(Omán, Jordania, Uzbekistán, Turkmenistán y otros), en 30 países. Una lengua que ese comentario no
nombra no se listó, aunque se use. Eso afecta a IP, no a IP local.

**La lengua local** es la primera de cada lista. En 35 países se reordenó la lista para que la
primera fuera la lengua local y no la lengua franca (por ejemplo, hausa en Nigeria, zulú en
Sudáfrica, tok pisin en Papúa Nueva Guinea). Donde solo se listó una lengua franca (por ejemplo,
Benín o Gabón, que listan solo el francés), la lengua local queda sin representar y IP local
coincide con IP.

**Corea del Norte** tiene IP = 4: su lengua (coreano) tiene recursos abiertos. Su acceso
restringido al texto del país es un problema de IO, no de IP, como dice la definición de arriba.

Los índices IL e IP son de juicio propio. La tabla POS muestra al lado la alfabetización y los
usuarios de internet, con fuente, para contrastar IL (ver «Señales para revisar» en «Pendientes»).

## Población histórica acumulada

**Qué mide:** el número de personas que han nacido en el territorio del país desde el 10 000 a. C.
Así China o Irán, con milenios de población densa, quedan muy por encima de Australia o
Sudamérica, que tuvieron poblaciones muy pequeñas durante casi toda la historia.

**Método:** `N = Σ P(t) × b(t)`, sumando año por año (`historia_poblacion.py`).

- `P(t)`: población del año t, interpolada entre los puntos de la serie larga. Entre dos puntos
  con población positiva la interpolación es geométrica (el crecimiento es aproximadamente
  exponencial).
- `b(t)`: tasa bruta de natalidad, como fracción (40 por mil = 0,040). Desde 1800, la del país
  (`natalidad.csv`); antes, 0,040 (sociedades preindustriales).
- Banda de sensibilidad: `N` se recalcula con `b` × 0,9 y `b` × 1,1, y el resultado queda en
  `poblacion_historica_detalle.csv`.

**Serie larga de cada país** (`preparar_datos.py`, método compuesto, decidido):

- **1750 a 2024:** población anual de Our World in Data.
- **Año 1 a 1700:** los puntos de Maddison 2020 (años 1, 1000, 1500, 1600 y 1700, según el país).
  165 de los 199 países tienen alguno.
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
  tasa bruta. Kosovo, sin ninguna de las dos, usa 0,040.

**Reglas del cálculo:**

- Si una serie repite un año, el script se detiene con error. No elige uno en silencio.
- Si una serie termina antes del año 2000, el país se salta y queda vacío. No se extiende la
  última población hasta 2025, ni se conserva un valor de un cálculo anterior.

**Resultados y comprobación de cordura.** El total mundial da unos **61 500 millones** de
personas nacidas desde el 10 000 a. C. Los mayores son India (15 000 millones) y China (13 700
millones). Irán (740), Egipto (920) y Japón (1 190) suman muchas veces su población actual, y
Australia (200) casi toda en su población aborigen. El Population Reference Bureau estima unos
107 000 millones desde el 8000 a. C., pero supone natalidades mucho más altas en la antigüedad
(hasta 80 por mil, por la mortalidad infantil). Con 40 por mil el total es de ese orden de magnitud
pero menor: otros investigadores lo sitúan entre 50 000 y 150 000 millones. **Las cifras sirven
para comparar países entre sí, no como conteo exacto.**

**Limitaciones que hay que conocer:**

- La retroproyección con cuota constante es gruesa: un país que antes tuvo más peso en su región
  (por ejemplo, Irak o Egipto frente al resto de Asia y África) queda subestimado, y uno que lo
  ganó después queda sobreestimado.
- La banda de ±10 % solo cubre la natalidad, no las poblaciones de hace miles de años.
- Antes de 1800 la natalidad varió mucho entre épocas y regiones; el 0,040 constante es una
  simplificación, y es lo que más pesa en el total (ver la comparación con el PRB).
- Puerto Rico usa Maddison de 1820 a 1959 y el Banco Mundial desde 1960, porque Our World in Data
  no lo incluye.

## Fuentes

Los dominios de las fuentes (`ourworldindata.org`, `api.worldbank.org`, `hdr.undp.org`) están
bloqueados por el proxy del entorno donde se trabajó: responden 403. Por eso se usaron copias
publicadas en GitHub, que sí son accesibles: los repositorios de Our World in Data, los de
Gapminder (`open-numbers`) y los de `datasets` (de datahub.io), que copian los indicadores del
Banco Mundial.

- [`owid/co2-data`](https://github.com/owid/co2-data), commit `382ee6c` (2 de junio de 2026),
  archivo `owid-co2-data.csv`: población por país de 1750 a 2024 y PIB de Maddison 2023.
- [`owid/owid-datasets`](https://github.com/owid/owid-datasets), commit `6155d4c` (14 de abril de
  2025): *Maddison Project Database 2020* y *Global Population by Region (HYDE 2016 & UN 2017)*.
- [`open-numbers/ddf--gapminder--systema_globalis`](https://github.com/open-numbers/ddf--gapminder--systema_globalis),
  commit `ee19060` (7 de mayo de 2026): tasa bruta de natalidad por país de 1800 a 2015
  (`crude_birth_rate_births_per_1000_population`) e IDH del PNUD de 1990 a 2023
  (`hdi_human_development_index`), alfabetización de adultos de la UNESCO
  (`literacy_rate_adult_total_percent_of_people_ages_15_and_above`) y usuarios de internet del
  Banco Mundial (`internet_users`).
- [`open-numbers/ddf--gapminder--fasttrack`](https://github.com/open-numbers/ddf--gapminder--fasttrack),
  commit `dee8978` (15 de octubre de 2026): fecundidad por país de 1800 a 2100
  (`children_per_woman_total_fertility`).
- [`datasets/gdp`](https://github.com/datasets/gdp), commit `36d761d` (27 de julio de 2026),
  archivo `data/gdp.csv`: PIB en dólares corrientes del Banco Mundial (`NY.GDP.MKTP.CD`), hasta 2023.
- [`datasets/population`](https://github.com/datasets/population), commit `d4b1a4a` (1 de octubre
  de 2026), archivo `data/population.csv`: población del Banco Mundial (`SP.POP.TOTL`), hasta 2025.

Para reproducir `poblacion_larga.csv`, `natalidad.csv` y las columnas de `paises.csv`:

```bash
git clone --depth 1 https://github.com/owid/co2-data
git clone --depth 1 --filter=blob:none --no-checkout https://github.com/owid/owid-datasets
d="owid-datasets"; m="Maddison Project Database 2020 (Bolt and van Zanden (2020))"
h="Global Population by Region with projections - HYDE (2016) & UN (2017)"
git -C $d show "HEAD:datasets/$m/$m.csv" > maddison-2020.csv
git -C $d show "HEAD:datasets/$h/$h.csv" > hyde-regiones.csv
r=https://raw.githubusercontent.com
sg=$r/open-numbers/ddf--gapminder--systema_globalis/ee190605fdec9d1c35c38e18e869a400a5818fe6/countries-etc-datapoints
ft=$r/open-numbers/ddf--gapminder--fasttrack/dee8978535ee563526704d2793497f8537280469/countries_etc_datapoints
curl -so natalidad-gapminder.csv $sg/ddf--datapoints--crude_birth_rate_births_per_1000_population--by--geo--time.csv
curl -so idh-gapminder.csv $sg/ddf--datapoints--hdi_human_development_index--by--geo--time.csv
curl -so alfabetizacion-gapminder.csv \
    $sg/ddf--datapoints--literacy_rate_adult_total_percent_of_people_ages_15_and_above--by--geo--time.csv
curl -so internet-gapminder.csv $sg/ddf--datapoints--internet_users--by--geo--time.csv
curl -so fecundidad-gapminder.csv $ft/ddf--datapoints--children_per_woman_total_fertility--by--country--time.csv
curl -sO $r/datasets/gdp/36d761dc5617982d3e50b213e02b9a1b47c4e958/data/gdp.csv
curl -sO $r/datasets/population/d4b1a4a9a17e2123baf0ef119de05bceced1ab1b/data/population.csv
python3 preparar_datos.py --co2 co2-data/owid-co2-data.csv --maddison maddison-2020.csv \
    --hyde hyde-regiones.csv --natalidad-gapminder natalidad-gapminder.csv \
    --fecundidad-gapminder fecundidad-gapminder.csv --idh idh-gapminder.csv \
    --alfabetizacion alfabetizacion-gapminder.csv --internet internet-gapminder.csv \
    --pib-bm gdp.csv --poblacion-bm population.csv
python3 historia_poblacion.py && python3 generar_tabla.py
```

Cuando los dominios estén accesibles, conviene reemplazar la serie compuesta por la serie por país
de Our World in Data desde el 10 000 a. C., y extender la natalidad después de 2015 con la de la
ONU o el Banco Mundial (`SP.DYN.CBRT.IN`) en vez de escalarla.

## Estado de los datos

Léelo antes de usar la tabla para decidir.

- **Población 2024: de fuente.** Our World in Data (ONU, WPP 2024) en 198 países; Puerto Rico, del
  Banco Mundial.
- **IDH: de fuente**, de 2023 (PNUD, *Informe 2025*, vía Gapminder). Coincide con valores
  conocidos de ese informe (Islandia 0,972; Noruega y Suiza 0,970; Sudán del Sur 0,388). Vacío
  donde el PNUD no publica IDH: Corea del Norte, Kosovo, Macao, Mónaco, Puerto Rico y Taiwán.
- **PIB per cápita nominal: de fuente** (Banco Mundial), de 2023. Usan 2022 Afganistán, Bután,
  Catar, Líbano, Liechtenstein, Mónaco y Tonga, y 2021 San Marino y Siria, porque el Banco Mundial
  no tiene un año posterior. Vacío en:
  - Corea del Norte y Taiwán, que el Banco Mundial no publica.
  - Cuba, Eritrea, Sudán del Sur, Venezuela y Yemen, cuyo último dato es anterior a 2021.
  - Chipre: el PIB del Banco Mundial cubre solo la zona que controla la República, y su población,
    toda la isla, así que el cociente sale alrededor de un 30 % por debajo. Su indicador per cápita
    (`NY.GDP.PCAP.CD`) sí lo corrige, pero no está publicado en GitHub.
- **PIB per cápita PPA: de Maddison 2023** (vía Our World in Data), de 2022 y en dólares
  internacionales de 2011. Es el PIB de Maddison entre la población de Our World in Data: donde
  Maddison usa otra población, el cociente se desvía. Chipre es un caso (Our World in Data cuenta
  toda la isla). Es distinto del indicador del Banco Mundial (`NY.GDP.PCAP.PP.CD`, en dólares
  internacionales corrientes), que sigue inaccesible. Vacío en 35 países sin dato.
- **Población histórica acumulada: estimada** con el método compuesto de arriba. Sirve para
  comparar países, no como conteo exacto.
- **Alfabetización e internet: de fuente** (UNESCO y Banco Mundial, vía Gapminder). El año varía
  por país; es el último disponible.
- **IG, IO, IL e IP:** son juicio propio, con las rúbricas de arriba. No son datos oficiales.
  La lista de recursos lingüísticos viene de memoria, sin contrastar. Las menciones a la
  alfabetización en `comentarios_pos` se contrastaron con la columna nueva: coinciden salvo la de
  Benín (ver «Señales para revisar»).

## Pendientes

1. **PIB nominal de Chipre:** completarlo con `NY.GDP.PCAP.CD` cuando el Banco Mundial esté
   accesible.
2. **Población histórica:** reemplazar la serie compuesta por la de Our World in Data por país
   desde el 10 000 a. C., y la natalidad posterior a 2015 por la de la ONU o el Banco Mundial,
   cuando haya acceso.
3. **PIB per cápita PPA:** decidir si se pasa al indicador del Banco Mundial cuando haya acceso, y
   completar los 35 vacíos.
4. **Revisar los índices** IG, IO, IL e IP con la persona que decide. La rúbrica es el criterio.
   **Señales para revisar** (de cruzar los índices con los datos con fuente):
   - **Burundi:** IL 1, que la rúbrica reserva para alfabetización muy baja o lenguas casi sin
     libros, con una alfabetización del 71 % (2020), más que Marruecos (IL 3, 64 %). Su
     comentario habla de recursos (IP), no de lectura.
   - **Benín y Somalia:** IL 1 con un 51 % y un 54 %, más que Costa de Marfil (IL 2, 50 %). El
     comentario de Benín dice «Alfabetización muy baja»; el de Somalia habla solo de recursos.
   - El resto de los países con IL 1 tiene menos del 45 %. Ningún país con IL 4 tiene menos del
     85 %; los de IL 5 no tienen dato porque la UNESCO no la mide en países de ingreso alto.
   - **Botsuana:** IO 4 con un 57 % de usuarios de internet, el más bajo de los IO 4 (el
     siguiente, Namibia, tiene 65 %). Si la conectividad cuenta como fricción mayor, sería IO 3.
5. **Umbral de exclusión de genealogía:** con IO ≤ 1 quedan 23 países (vigente); con IO ≤ 2,
   54. Camboya y Vietnam siguen dentro, por decisión tomada.
6. **Lenguas francas sin listar:** una lengua que el comentario de genealogía no nombra no se
   listó, aunque se use (el inglés en Maldivas, por ejemplo). Afecta a IP, no a IP local.
