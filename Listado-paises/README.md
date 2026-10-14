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
| `pib_per_capita_usd` | PIB per cápita nominal en dólares. **Sin contrastar.** |
| `indice_genealogia` | IG, de 1 a 5. |
| `indice_operabilidad` | IO, de 1 a 5. |
| `comentarios` | Motivo de los puntajes de genealogía. |

## Columnas de `paises_pos.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Igual que en `paises.csv`. |
| `idioma_principal` | Las lenguas de la mayoría de los textos escritos. La puntuación se da según la que tenga más recursos digitales. |
| `indice_lectura` | IL, de 1 a 5. |
| `indice_pos` | IP, de 1 a 5. |
| `comentarios_pos` | Motivo de los puntajes del etiquetador. |

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
internet, marco legal de datos personales e idioma.

- 5: estable, sin sanciones, con pagos y datos sin fricción.
- 4: operable con fricciones menores (burocracia, regulación de datos exigente).
- 3: operable con cuidado (riesgo político o cambiario, pagos limitados).
- 2: difícil (sanciones parciales, control estatal de internet, inestabilidad).
- 1: inviable (sanciones amplias, guerra o control total).

**Prioridad** = IG × IO (1 a 25). Ordena la tabla; no es un tercer índice.

**Excluido** = IO ≤ 1. Quedan fuera **24 países**, cerca del objetivo de unos 20: Afganistán,
Bielorrusia, Burkina Faso, Corea del Norte, Cuba, Eritrea, Haití, Irán, Libia, Malí, Myanmar,
Nicaragua, Níger, República Centroafricana, RD del Congo, Rusia, Siria, Somalia, Sudán, Sudán
del Sur, Turkmenistán, Venezuela, Yemen y Palestina.

Camboya y Vietnam tienen IO = 2 («difícil»), así que **no quedan excluidos** con este umbral.
Si se quieren fuera, el umbral pasa a IO ≤ 2, que deja fuera 55 países, o se marcan a mano.
El umbral está en `UMBRAL_GENEALOGIA` de `generar_tabla.py`.

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
- 1: sin recursos, o sin acceso a texto digital.

**Prioridad POS** = IL × IP. **Excluido POS** = IP ≤ 1: quedan fuera **11 países**: Bután,
Burundi, Chad, Comoras, Corea del Norte, Eritrea, Maldivas, República Centroafricana, Somalia,
Sudán del Sur y Turkmenistán. El umbral está en `UMBRAL_POS`.

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

**Datos de entrada (no están en el repositorio):**

- `poblacion_larga.csv` (`codigo,anio,poblacion`): la serie larga por país. Fuente propuesta:
  *Population, 10,000 BCE to 2023*, de Our World in Data (combina HYDE, Gapminder y la ONU). Como
  respaldo, el *Maddison Project Database 2023* (desde el año 1; DOI `10.34894/INZBF2`).
- `natalidad.csv` (`codigo,anio,tasa_por_mil`): natalidad anual desde 1950, de la ONU (*World
  Population Prospects*) o del Banco Mundial (`SP.DYN.CBRT.IN`). Si falta un país, usa 0,040.

**Limitaciones que hay que conocer:**

- El resultado depende sobre todo de la serie antigua, y esa incertidumbre es grande: la banda
  de ±10 % solo cubre la natalidad, no las poblaciones de hace miles de años.
- La fuente de HYDE antes de 1800, por país, no está documentada en el resumen que se consultó.
  Hay que verificarla antes de usar las cifras más antiguas.
- Antes de 1950 la natalidad varió mucho entre épocas y regiones; el 0,040 constante es una
  simplificación.

## Estado de los datos

Léelo antes de usar la tabla para decidir.

- **Población actual, IDH y PIB per cápita: son referencias cargadas de memoria, no consultadas.**
  No se pudieron abrir las fuentes oficiales desde la sesión donde se generó la tabla: el Banco
  Mundial y el PNUD devolvían 403 por el proxy, y en otra prueba no resolvían. Las cifras son
  aproximadas y redondeadas. Antes de decidir con ellas, contrástalas con:
  - Banco Mundial: `SP.POP.TOTL` (población) y `NY.GDP.PCAP.CD` (PIB per cápita).
  - ONU, *World Population Prospects* (población).
  - PNUD, *Informe sobre Desarrollo Humano* (IDH).
  - FMI, *World Economic Outlook* (PIB per cápita).
- **Celdas vacías (—):** cuando no hay una cifra fiable se deja vacía. Por ejemplo, el IDH de
  Corea del Norte, Palestina, Siria o Nauru, o el PIB per cápita de Cuba o Corea del Norte.
- **Población histórica acumulada: sin datos todavía.** `historia_poblacion.py` está listo para
  calcularla en cuanto existan `poblacion_larga.csv` y, si se quiere, `natalidad.csv`.
- **IG, IO, IL e IP:** son juicio propio, con las rúbricas de arriba. No son datos oficiales.
  Las alfabetizaciones y la lista de recursos lingüísticos vienen de memoria, sin contrastar.

## Pendientes

1. **Población histórica acumulada:** conseguir `poblacion_larga.csv` (OWID o Maddison) y
   `natalidad.csv`, y ejecutar `historia_poblacion.py`. Hay que confirmar el dominio desde donde
   se descarga antes de habilitarlo.
2. **Contrastar población, IDH y PIB per cápita** con las fuentes oficiales.
3. **Revisar los índices** IG, IO, IL e IP con la persona que decide. La rúbrica es el criterio.
4. **Decidir el umbral de exclusión** de genealogía: con IO ≤ 1 quedan 24 países; con IO ≤ 2,
   55. Y decidir si Camboya y Vietnam deben quedar fuera.
5. **Agregar este proyecto a la tabla de `README.md` de la raíz.** Ese archivo es compartido,
   así que el cambio va en un PR aparte.
