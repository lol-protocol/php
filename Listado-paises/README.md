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
- `generar_tabla.py`: valida los dos CSV y genera `tabla_genealogia.md` y `tabla_pos.md`.
- `niveles_lenguas.csv`: nivel de recursos de cada lengua (1 a 5). IP de cada país es el máximo
  de sus lenguas; `generar_tabla.py` comprueba que coincida.
- `historia_poblacion.py`: calcula la población histórica acumulada y la escribe en `paises.csv`.
  Necesita datos de entrada que todavía no están (ver «Población histórica acumulada»).

```bash
python3 generar_tabla.py      # valida y regenera las dos tablas
python3 historia_poblacion.py # solo hace algo si existe poblacion_larga.csv
```

Ambos scripts usan solo la biblioteca estándar de Python 3. `generar_tabla.py` se detiene con un
mensaje si una fila está mal: encabezado distinto, índice fuera de 1–5, código repetido o que
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
| `poblacion_millones` | Población actual, en millones. **Sin contrastar** (ver «Estado de los datos»). |
| `poblacion_historica_acumulada` | Personas que han nacido en el país desde la prehistoria, en millones. **Vacía: sin datos** (ver más abajo). |
| `idh` | Índice de Desarrollo Humano. **Sin contrastar.** |
| `pib_per_capita_usd` | PIB per cápita **nominal** en dólares. **Sin contrastar.** |
| `pib_per_capita_ppa_usd` | PIB per cápita en **paridad de poder adquisitivo** (dólares internacionales). **Vacía: sin datos** (ver «Estado de los datos»). |
| `indice_genealogia` | IG, de 1 a 5. |
| `indice_operabilidad` | IO, de 1 a 5. |
| `comentarios` | Motivo de los puntajes de genealogía. |

## Columnas de `paises_pos.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Igual que en `paises.csv`. |
| `idioma_principal` | Las lenguas en que se escribe la mayoría de los textos del país. IP usa la que tenga más recursos (ver abajo). |
| `indice_lectura` | IL, de 1 a 5. |
| `indice_pos` | IP, de 1 a 5. |
| `comentarios_pos` | Motivo de los puntajes del etiquetador. Si el comentario empieza con «IP por …», el puntaje sale de esa lengua. |

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
Níger, República Centroafricana, República Democrática del Congo, Rusia, Siria, Somalia, Sudán, Sudán del Sur,
Turkmenistán, Venezuela, Yemen y Palestina.

Nicaragua tenía IO = 1 y ahora tiene IO = 2: sus sanciones son parciales y sus factores (control
de medios e internet) son los mismos que los de Vietnam, que la rúbrica ya marca como «difícil».

Camboya y Vietnam tienen IO = 2 («difícil»): sus índices los marcan como difíciles, no como
inviables, así que con este umbral **siguen dentro**. Esa fue la decisión tomada. Para sacarlos
habría que bajar su IO a 1, lo que la rúbrica no justifica, o subir el umbral a IO ≤ 2, que deja
fuera 54 países. El umbral está en `UMBRAL_GENEALOGIA` de `generar_tabla.py`.

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
- 1: sin recursos abiertos en ninguna de las lenguas listadas.

**Criterio de IP (vigente, elegido por ti):** IP toma la lengua de `idioma_principal` con más
recursos. Así, en un país con inglés y otra lengua, cuenta el inglés. Los niveles de cada lengua
están en `niveles_lenguas.csv`, y `generar_tabla.py` se detiene si un IP no es el máximo de sus
lenguas o si una lengua no tiene nivel. Los países que cambiaron
de puntaje llevan el comentario «IP por …» para que se vea de qué lengua sale.

**Consecuencia que hay que conocer:** casi todos los países listan inglés, francés, español o
portugués, así que el criterio sube a 5 a la mayoría. Hoy **141 de 199** tienen IP = 5, y los
demás se reparten así: 28 con 4, 17 con 3, 11 con 2 y 2 con 1. Con esta distribución la columna
distingue poco entre países.

**Prioridad POS** = IL × IP. **Excluido POS** = IP ≤ 1: quedan fuera **2 países**, Maldivas
(dhivehi) y Turkmenistán (turcomano), los dos únicos cuya lengua listada no tiene recursos
abiertos. Antes eran 8, pero con el criterio de la lengua con más recursos la mayoría de esos
países listan también una lengua con recursos (por ejemplo, el francés en Burundi o la República
Centroafricana). El umbral está en `UMBRAL_POS`.

**Corea del Norte** pasó a IP = 4: su lengua listada (coreano) tiene recursos abiertos. Su acceso
restringido al texto del país es un problema de IO, no de IP, como dice la definición de arriba.

Los índices IL e IP son de juicio propio, con base en recursos y alfabetización que no se han
contrastado con fuentes (ver «Estado de los datos»).

## Población histórica acumulada

**Qué mide:** el número de personas que han nacido en el territorio del país desde el inicio de
la serie (en la práctica, desde el 10 000 a. C.). Así China o Irán, con milenios de población
densa, quedan muy por encima de Australia o Sudamérica, que tuvieron poblaciones muy pequeñas
durante casi toda la historia.

**Método:** `N = Σ P(t) × b(t)`, sumando año por año.

- `P(t)`: población del año t, interpolada entre los puntos de una serie larga. Entre dos puntos
  con población positiva la interpolación es geométrica (el crecimiento es aproximadamente
  exponencial).
- `b(t)`: tasa bruta de natalidad, como fracción (40 por mil = 0,040). Antes de 1950 se usa
  0,040 (sociedades preindustriales). Desde 1950, la tasa de natalidad del país para ese año.
- Banda de sensibilidad: `N` se recalcula con `b` × 0,9 y `b` × 1,1, y el resultado queda en
  `poblacion_historica_detalle.csv`.

**Reglas del cálculo:**

- Si una serie repite un año, el script se detiene con error. No elige uno en silencio.
- Si una serie termina antes del año 2000, el país se salta y el script lo lista. No extiende la
  última población hasta 2025 en silencio.

**Fuente elegida: HYDE 3.3 directo.** HYDE publica cuadrículas de 5 minutos de arco (archivos
ESRI ASCII), no totales por país. Para llegar a un total por país hace falta una máscara de
fronteras nacionales que sume las celdas de cada país. Esa agregación todavía no existe en el
repositorio, y el dominio de HYDE no está habilitado en esta sesión. Si la agregación resulta
demasiado pesada, la alternativa es el producto *Population, 10,000 BCE to 2023* de Our World in
Data, que ya trae totales por país y se construye a partir de HYDE. Como respaldo está el
*Maddison Project Database 2023* (desde el año 1; DOI `10.34894/INZBF2`).

**Datos de entrada (no están en el repositorio):**

- `poblacion_larga.csv` (`codigo,anio,poblacion`): la serie larga por país, con la fuente que se
  elija arriba.
- `natalidad.csv` (`codigo,anio,tasa_por_mil`): natalidad anual desde 1950, de la ONU (*World
  Population Prospects*) o del Banco Mundial (`SP.DYN.CBRT.IN`). Si falta un país, usa 0,040.

**Limitaciones que hay que conocer:**

- El resultado depende sobre todo de la serie antigua, y esa incertidumbre es grande: la banda
  de ±10 % solo cubre la natalidad, no las poblaciones de hace miles de años.
- La fuente de HYDE antes de 1800, por país, no está documentada en el resumen que se consultó.
  Hay que verificarla antes de usar las cifras más antiguas.
- Antes de 1950 la natalidad varió mucho entre épocas y regiones; el 0,040 constante es una
  simplificación.
- Banco Mundial empieza su natalidad en 1960; los años 1950 a 1959 quedan con 0,040 hasta que
  haya una fuente que los cubra.

## Estado de los datos

Léelo antes de usar la tabla para decidir.

- **Población actual, IDH y PIB per cápita nominal: son referencias cargadas de memoria, no
  consultadas.** No se pudieron abrir las fuentes oficiales desde la sesión donde se generó la
  tabla: el Banco Mundial y el PNUD devolvían 403 por el proxy, y en otras pruebas no resolvían. En
  la última sesión, todos los hosts de datos (Banco Mundial, PNUD, Our World in Data y PBL) seguían
  sin responder. Las cifras son aproximadas y redondeadas. Antes de decidir con ellas, contrástalas con:
  - Banco Mundial: `SP.POP.TOTL` (población) y `NY.GDP.PCAP.CD` (PIB per cápita nominal).
  - ONU, *World Population Prospects* (población).
  - PNUD, *Informe sobre Desarrollo Humano* (IDH).
  - FMI, *World Economic Outlook* (PIB per cápita nominal).
- **PIB per cápita PPA: sin datos todavía.** Se llenará desde el Banco Mundial, indicador
  `NY.GDP.PCAP.PP.CD` (que hay que confirmar al consultarlo). No se han cargado cifras de memoria
  para esta columna, para no mezclar dos métodos sin fuente.
- **Celdas vacías (—):** cuando no hay una cifra fiable se deja vacía. Por ejemplo, el IDH de
  Corea del Norte, Palestina, Siria o Nauru, o el PIB per cápita de Cuba o Corea del Norte. Una
  revisión posterior también vació nueve IDH que quedaban fuera de su grupo de desarrollo humano:
  Cuba, Perú, Turkmenistán, Kirguistán, Benín, Tanzania, Libia, Gabón y Cabo Verde. No se
  reemplazaron por otra cifra de memoria.
- **Población histórica acumulada: sin datos todavía.** `historia_poblacion.py` está listo para
  calcularla en cuanto existan `poblacion_larga.csv` y, si se quiere, `natalidad.csv`.
- **IG, IO, IL e IP:** son juicio propio, con las rúbricas de arriba. No son datos oficiales.
  Las alfabetizaciones y la lista de recursos lingüísticos vienen de memoria, sin contrastar.
  Algunos casos se revisaron contra la rúbrica en una revisión posterior (Bután, Chad, Eritrea,
  Azerbaiyán, Nepal y Nicaragua), pero siguen siendo juicio.

## Pendientes

1. **Criterio de IP:** ya está fijado en «la lengua con más recursos», pero con esa regla 141 de
   199 países quedan en 5. Hay que decidir si se mantiene, si se usa la lengua local o si se
   agrega un desempate.
2. **Población histórica acumulada:** decidir si se agrega HYDE por país (con máscara de
   fronteras) o se usa el producto de Our World in Data. Después, conseguir `poblacion_larga.csv`
   y `natalidad.csv`, y ejecutar `historia_poblacion.py`. Hay que habilitar el dominio elegido
   antes de descargar.
3. **PIB per cápita PPA:** cargar la columna desde el Banco Mundial, con el indicador
   `NY.GDP.PCAP.PP.CD`.
4. **Contrastar población, IDH y PIB per cápita nominal** con las fuentes oficiales.
5. **Revisar los índices** IG, IO, IL e IP con la persona que decide. La rúbrica es el criterio.
6. **Umbral de exclusión de genealogía:** con IO ≤ 1 quedan 23 países (vigente); con IO ≤ 2,
   54. Camboya y Vietnam siguen dentro, por decisión tomada.
7. **Lenguas listadas en `idioma_principal`:** no hay un criterio único. Algunos países listan el
   inglés o el ruso y otros no, aunque su comentario de genealogía los nombre. Como IP toma la
   lengua con más recursos, eso cambia el puntaje: Baréin, Kuwait, Catar y Emiratos listan el
   inglés (IP 5), pero Omán y Jordania no (IP 4); Kazajistán y Kirguistán listan el ruso (IP 5),
   pero Uzbekistán, Tayikistán, Armenia, Azerbaiyán, Mongolia y Turkmenistán no. Hay que fijar
   si se listan solo las lenguas oficiales o todas las de uso amplio.
8. **Factor de operabilidad sin justificar:** unos 18 países tienen IO 2 o 3 sin que su comentario nombre
   un factor de operabilidad (mercado, pagos, conectividad). Es el caso de varios micro-estados y
   de África occidental (Albania, Armenia, Belice, Benín, Paraguay, República Dominicana, Samoa y
   otros). Hay que añadir el factor al comentario o revisar el puntaje.
9. **Agregar este proyecto a la tabla de `README.md` de la raíz.** Ese archivo es compartido,
   así que el cambio va en un PR aparte.
