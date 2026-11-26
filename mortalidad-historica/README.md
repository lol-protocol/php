# Mortalidad histórica

Datos para leer con criterio las edades, las enfermedades y las causas de muerte de los antepasados en un árbol genealógico:

- **Esperanza de vida a lo largo de los siglos**, por país o región, sexo y **edad** (no solo al nacer; ver «Por qué no basta la esperanza de vida al nacer»).
- **Epidemias y pandemias** (28, de la peste de Justiniano a la gripe de 1968), con sus años, regiones y, cuando hace falta, países, para notar cuándo una serie de muertes en una familia tiene una explicación. Se leen de corrido en [`epidemias.md`](epidemias.md).
- **Las muertes por año en Londres, 1629-1902** (Bills of Mortality y los registros que las siguieron): la única serie anual de **muertes** (no de tasas) de esta carpeta, con la que se ve cuánto se disparó un año como 1665.
- **Un glosario de causas de defunción históricas** («hidropesía», «alferecía», «Schwindsucht», «bexigas»…) en 20 idiomas (más de 800 términos: entre 46 y 101 en español, inglés, alemán, francés, italiano, latín y portugués, y entre 19 y 32 en los otros 13 idiomas), con la causa moderna a la que suelen corresponder y qué tan segura es esa correspondencia, y **sin revisión por hablantes nativos prevista** (ver «Revisión del glosario»).
- **Las tasas de natalidad y mortalidad de Inglaterra y Gales, 1541-2015** (Wrigley y Schofield y el registro civil): un punto cada cinco años hasta 1861 y anual después, con una consulta que marca los períodos de mortalidad anormal.
- **Enfermedades hereditarias**, como contexto médico y poblacional.
- **Cuándo empezó el registro civil** en cada país, para saber qué fiabilidad esperar de una edad o una causa.

> **Estado: fase 2 (esperanza de vida).** La esperanza de vida (≈37 500 filas, con bandas de incertidumbre) viene de fuentes abiertas fijadas por commit y se rehace con un script (`estado=verificado`: la fila es lo que dice su fuente; la calidad de la fuente es otra cosa, ver `calidad` y «Esperanza de vida: de dónde sale y cómo leerla»). También son `verificado` las muertes por año en Londres (1629-1902), las tasas de Inglaterra y Gales (1541-2015), y tres cifras de cólera en Gran Bretaña (los códigos OMIM de las hereditarias están comprobados contra MONDO, pero sus filas siguen en `borrador`). Todo lo demás sigue siendo un **borrador escrito por un asistente de IA**: el glosario, casi todas las epidemias, las hereditarias y los hitos de registro civil no están verificados contra una fuente (`estado=borrador`) y **nadie ha revisado los términos ni se prevé una revisión por hablantes nativos** (ver «Revisión del glosario»). La esperanza de vida cubre las edades 0 y 5 (medidas o derivadas) y 15, 30, 45 y 60, que son **modeladas** (ver más abajo). Ver «Plan».

> **Antes de usar esto con datos de personas, lee [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md).** La salud y las causas de muerte son datos sensibles, y los de un antepasado alcanzan a sus descendientes vivos. Esta carpeta solo guarda **estadística y vocabulario**, nunca datos de personas reales, y no ofrece ninguna función que mire a una persona o una familia. Ver «Qué no hace» y [`PRIVACIDAD.md`](PRIVACIDAD.md), que lista lo que habría que decidir antes de mostrar causas de muerte o enfermedades al lado de una persona.

## Resumen

Cifras que cambian con los datos. Las escribe `generar_tabla.py` y `--comprobar` falla si no están al día.

<!-- resumen: lo escribe generar_tabla.py; no editar a mano -->
- **Causas modernas:** 54; **términos históricos:** 813 en 20 de 20 idiomas (confianza alta: 647, media: 141, baja: 25).
- **Términos revisados por una persona:** 0 de 813 (no se prevé revisión).
- **Idiomas sin términos:** ninguno.
- **Causas modernas sin ningún término histórico:** ninguna.
- **Epidemias:** 28 (3 verificadas). **Enfermedades hereditarias:** 12. **Hitos de registro civil:** 6. **Países:** 208.
- **Muertes en Londres:** 241 años (1629-1902).
- **Tasas de Inglaterra y Gales:** 219 registros (1541-2015).
- **Esperanza de vida:** 37493 filas (211 países o regiones).
- **Filas verificadas contra su fuente:** 37956 de 38812.
<!-- fin del resumen -->

## Por qué no basta la esperanza de vida al nacer

Un «30 años» en 1850 no quiere decir que la gente muriera a los 30. La cifra al nacer está arrastrada hacia abajo por la mortalidad infantil: en muchas poblaciones murió antes de los 5 años una cuarta parte de los niños o más. Quien sobrevivía a la infancia solía vivir mucho más. Con los datos de esta carpeta: en Suecia, en 1800-1809, la esperanza al nacer era de 36 años, pero a quien cumplía 5 le quedaban otros 47 (llegaba a los 52 en promedio), y solo siete de cada diez niños llegaban a esa edad. Por eso `esperanza_vida.csv` guarda la **esperanza restante a más de una edad** y la **probabilidad de sobrevivir hasta esa edad**: con eso se puede juzgar si una ficha es plausible (edad al morir, hijos que llegaron a adultos, hermanos esperados) sin confundirse con el promedio. Hoy están las edades 0, 5, 15, 30, 45 y 60. Por ejemplo, en esa misma Suecia de 1800-1809, a quien cumplía 15 le quedaban otros 41 años, y a quien cumplía 45, otros 23 (esas dos cifras son modeladas).

## Cómo leer una causa de defunción

Los registros antiguos no usan nombres modernos, y muchos son **síntomas**, no enfermedades: la «hidropesía» es una hinchazón; la «consunción» es un adelgazamiento. `causas_historicas.csv` liga cada término (con su idioma) a una causa moderna con una **confianza**:

- `alta`: el término designa casi siempre esa enfermedad («viruelas», «variolae», «Blattern»).
- `media`: suele serlo, pero también cubre otras («consunción», «dropsy»).
- `baja`: puede ser esa o varias más («croup»).

Es una **pista para leer un documento, no un diagnóstico**. Un mismo término puede aparecer varias veces con causas distintas. Los códigos CIE-10 son aproximados: la CIE moderna no existía, y la clasificación de cada época agrupaba distinto.

## Enfermedades hereditarias

`hereditarias.csv` describe condiciones hereditarias conocidas: modo de herencia, código OMIM, en qué poblaciones la frecuencia de portadores es mayor y cuándo suelen empezar. Son **estadísticas de población**, y tienen límites que importan:

- **Pertenecer a una población no implica tener ni portar la condición.** Casi todas las personas de una población con frecuencia alta no son portadoras.
- **Nada de aquí sirve para inferir la salud de una persona o una familia concreta** a partir de su origen, su apellido o su lugar de nacimiento.
- La ascendencia que se nombra es una **descripción geográfica o histórica de la distribución**, no una categoría para clasificar a nadie. El origen étnico es, por sí mismo, un dato sensible.
- **Los códigos OMIM están verificados contra MONDO** (la ontología de enfermedades de Monarch Initiative, fijada por commit en `fuentes.sha256`): el código de cada fila es un `xref` de su término de la columna `mondo`, y `preparar_datos.py` falla si no lo es. Al verificarlos salió un error: el código de la deficiencia de G6PD que yo había escrito de memoria era el número antiguo (305900) y MONDO usa el 300908. Eso **solo verifica el código**; el modo de herencia, las poblaciones y el resto de cada fila siguen siendo `borrador`.

## Esperanza de vida: de dónde sale y cómo leerla

`datos/esperanza_vida.csv` tiene una fila por país, **década** (1800-1809 … 2000-2009; desde 1540 solo la esperanza al nacer de cuatro países), sexo y edad: ≈37 500 filas para 206 países y territorios. Se rehace con `descargar_fuentes.py` y `preparar_datos.py`; no se edita a mano.

| Qué | Edad | Sexo | Años | Cómo se obtiene |
|---|---|---|---|---|
| Esperanza de vida al nacer | 0 | T (ambos) | 1800-2009 | Directa de la fuente: Gapminder hasta 1949 y la ONU (revisión 2017) desde 1950. |
| Esperanza de vida al nacer, antes de 1800 | 0 | T | 1540-1799 | Directa de Riley y Clio Infra, vía Our World in Data, solo para **Reino Unido** (desde 1540), **Suecia**, **Finlandia** (desde 1750) y **Dinamarca** (1770), y, para cada una de las cinco **regiones** (`R:África`, `R:América`, `R:Asia`, `R:Europa`, `R:Oceanía`), el valor «previo a la transición sanitaria» que Riley fija en 1770: no es una medición de esa década, sino el promedio de las estimaciones de los países de la región antes de que empezara su transición. Sin edades mayores: no hay mortalidad infantil tan atrás. |
| Esperanza restante y supervivencia a los 5 años | 5 | T | 1800-2009 | **Derivada** de la esperanza al nacer y de la mortalidad de 0 a 5 años de Gapminder. |
| Esperanza de vida al nacer por sexo | 0 | M y F | 1950-2009 | Directa de Gapminder (a partir de la ONU). |
| Esperanza restante y supervivencia a los 15, 30, 45 y 60 años | 15, 30, 45, 60 | T | 1800-2009 | **Modelada** con el modelo log-cuadrático de Wilmoth et al. (2012), desde la esperanza al nacer y la mortalidad de 0 a 5 años. `calidad` C siempre, también después de 1950. |
| Esperanza restante a los 5, 15, 30, 45 y 60 años, mujeres y hombres | 5, 15, 30, 45, 60 | F y M | 1950-2009 | **Modelada** igual, con los coeficientes de cada sexo y la esperanza al nacer por sexo, pero con la mortalidad de 0 a 5 años de **ambos sexos** (no hay por sexo). A los 5 años no se guarda la supervivencia (sería la de ambos sexos). |

**La derivación a los 5 años.** La esperanza al nacer es el promedio de dos grupos: los niños que mueren antes de los 5 años (una fracción *q5*, que viven en promedio unos 1,0 años) y los que llegan a los 5 (que viven 5 más su esperanza restante *e5*). De `e0 = q5·a + (1−q5)·(5+e5)` se despeja `e5`. El único supuesto es `a` (la edad media de muerte de quien no llega a los 5), que se fija en 1,0 año; si en realidad fuera 0,5 o 1,5, `e5` cambia menos de 0,35 años incluso con una mortalidad de 400 por mil. Siempre sale `5 + e5 ≥ e0`, y las pruebas lo comprueban en todas las filas.

**Las edades de 15 a 60 años: un modelo, no una medición.** No hay una fuente abierta por país y edad que se pueda redistribuir, así que esas filas salen de `modelo.py`: el modelo log-cuadrático de Wilmoth, Zureick, Canudas-Romo, Inoue y Sawyer (2012, *Population Studies* 66(1)), `log m(x) = a(x) + b(x)·h + c(x)·h² + v(x)·k` con `h = log(5q0)`. Los coeficientes `a`, `b`, `c` y `v` (`datos/modelo_logcuadratico.csv`) se ajustaron con 968 tablas de vida de la Human Mortality Database y se extraen del paquete DemoTools con `leer_rda.py`; `k` es el único parámetro libre y se busca el que da la esperanza al nacer de la fuente. Una tabla de vida completa sale de ahí, y se guardan las edades 15, 30, 45 y 60. Lo que hay que tener presente:

- **Es una extrapolación.** El modelo describe cómo suele repartirse la mortalidad por edades en poblaciones con esa mortalidad infantil y esa esperanza de vida, y se ajustó con poblaciones que tuvieron registro. Aplicado a Nigeria en 1800 o a Chile en 1850 supone que se parecían a ellas; las entradas (e0 y 5q0) son ya estimaciones. No hay bandas de error.
- **Se validó contra el ejemplo publicado de DemoTools** (ambos sexos, 5q0 = 0,05 y e0 = 65): coincide con menos de 0,01 años de diferencia hasta los 75 (53,98 contra 53,98 a los 15; 16,09 contra 16,09 a los 60). Mi implementación simplifica la edad media de muerte de los menores de 5 años (afecta menos de 0,01 años a las edades de 15 o más), y está escrita desde el artículo y no copia el código de DemoTools. Las pruebas comprueban eso y que la esperanza a los 5 años derivada y la del modelo cuadren.
- **Mujeres y hombres son una aproximación**: usan la mortalidad de 0 a 5 años de ambos sexos, que no existe por sexo en las fuentes. Con un error del 15 % en esa mortalidad, el modelo cambia las esperanzas de 15 a 60 años en unos ±0,3 años si la mortalidad infantil es baja y hasta ±1,2 si es alta; las diferencias reales entre niños y niñas están dentro de ese orden.
- **Hay décadas en que el modelo no puede cuadrar** la esperanza al nacer con la mortalidad de 0 a 5 años de la fuente (20 décadas de país: 5 de ambos sexos, todas de Barbados entre 1860 y 1900, y 15 de mujeres u hombres): esas filas no se escriben, y `esperanza_restante` devuelve `None`.

**Qué hay que saber antes de usar una cifra:**

- **Antes de 1950 es una estimación, no una medición** (`calidad` C). Gapminder junta pocos datos reales y **interpola** entre ellos: cuando varias décadas seguidas de un país tienen exactamente el mismo valor (Chile, 1800 y 1850: 32,0), es un valor supuesto, y los saltos bruscos entre dos décadas (España, esperanza a los 5 años de 47,9 en 1800 y 39,2 en 1850) suelen ser un artefacto de la interpolación. Desde 1950 es una estimación de la ONU (`calidad` B).
- **Bandas de incertidumbre (`incertidumbre_baja` e `incertidumbre_alta`): desde 1950 medidas, antes de 1950 supuestas.** La banda es el rango de la esperanza restante si la esperanza al nacer y la mortalidad de 0 a 5 años estuvieran en los extremos de su propia banda (con las combinaciones coherentes: menos esperanza con más mortalidad infantil, y al revés). Los anchos de esas dos bandas por época:

  | Época | Esperanza al nacer | Mortalidad de 0 a 5 años | De dónde sale |
  |---|---|---|---|
  | 1950-2009 | ±0,6 años | ±7 % | **Medido**: el percentil 80 de lo que discrepan las revisiones de la ONU entre sí (0,59 años) y las dos fuentes de mortalidad infantil, Gapminder y CME (6,6 %), en 1950-2009 |
  | 1900-1949 | ±5 años | ±20 % | **Supuesto del proyecto**, prudente |
  | 1800-1899 | ±8 años | ±25 % | **Supuesto del proyecto**, prudente |
  | Antes de 1800 | ±10 años | ±30 % | **Supuesto del proyecto**, prudente |

  Antes de 1950 no hay una segunda fuente independiente: Gapminder y Clio Infra salen de la misma compilación de Riley y casi no discrepan (mediana de 0,03 años), así que medir con ellas daría una precisión falsa. Esos anchos son un juicio mío, deliberadamente **prudente** (se ensancharon a petición de la persona a cargo, el 21 de noviembre de 2026, para que cubran con holgura lo que la literatura suele dar para estimaciones de esas épocas), para que ninguna cifra parezca más firme de lo que es; no son una medición, y **son un piso, no un techo**: la incertidumbre real de una cifra de 1800 puede ser mayor. Los valores están en `BANDAS` de `preparar_datos.py`.
- **Es una cifra de período, no de generación**: la mortalidad de esa década, aplicada a quien nace o cumple esa edad. No dice cuánto vivió una persona concreta ni una familia.
- **Cada década es el promedio de sus 10 años** y se omite si falta alguno. No hay 2010 en adelante a propósito: la serie larga llega hasta 2015 y esa década quedaría incompleta.
- **Países**: son los de `codigos_iso.csv` de `Listado-paises/` que tienen datos. Guernsey y la Isla de Man no tienen ninguna serie. Otros 15 solo tienen la serie por sexo desde 1950, sin los años anteriores ni la edad 5: Andorra, Bermudas, Dominica, Gibraltar, Islas Feroe, Islas Marshall, Jersey, Kosovo, Liechtenstein, Mónaco, Nauru, Palaos, San Cristóbal y Nieves, San Marino y Tuvalu. Un nombre de las fuentes que no sea un país ni esté en las listas de omitidos de `preparar_datos.py` hace fallar el script, para no perder un país sin enterarse.

### Fuentes

Fijadas por commit y comprobadas con SHA-256 (`fuentes.sha256`). Identificadores de la columna `fuente`:

| Identificador | Qué es | De dónde |
|---|---|---|
| `owid-larga` | Esperanza de vida al nacer, «Long run life expectancy - Gapminder, UN» | Gapminder hasta 1949, ONU (revisión 2017) desde 1950, reunida por Our World in Data (`owid/owid-datasets`) |
| `gapminder-0-5` | Mortalidad de 0 a 5 años por mil nacidos, 1800-2100 | Gapminder (`ddf--gapminder--fasttrack`) |
| `gapminder-sexo` | Esperanza de vida al nacer, mujeres y hombres, 1950-2100 | Gapminder (`ddf--gapminder--systema_globalis`), a partir de la ONU |
| `londres-owid` | Muertes totales y por viruela en Londres, 1629-1902 | Our World in Data (`owid/owid-datasets`), a partir de Guy (1882) y de los informes del Registrar-General; ver su `datapackage.json` |
| `wrigley-schofield-owid` | Tasas brutas de natalidad y mortalidad de Inglaterra y Gales, 1541-2015 | Our World in Data (`owid/owid-datasets`), a partir de Wrigley y Schofield (reconstrucción desde los registros parroquiales, 1541-1861) y del registro civil desde 1838 (Mitchell) |
| `thomas-2015-owid` | Muertes por cólera en Gran Bretaña en los brotes del siglo XIX | Our World in Data, a partir de Thomas (2015) y Pearson Schools and Colleges |

`owid-larga+gapminder-0-5` es una fila derivada de `owid-larga` y `gapminder-0-5`. `logquad` es una fila **modelada** (ambos sexos): el modelo log-cuadrático (coeficientes de DemoTools, `modelo-logcuadratico-b.rda` en `fuentes.sha256`) aplicado a la esperanza de vida de `owid-larga` y a la mortalidad de 0 a 5 años de `gapminder-0-5`. `logquad-sexo` es lo mismo para mujeres y hombres (`-f.rda` y `-m.rda`), con la esperanza al nacer de `gapminder-sexo`. `riley-clio` es la esperanza de vida de Riley (2005) y Clio Infra (2015), vía Our World in Data (`esperanza-riley-clio-owid.csv`), de la que solo se usan las décadas anteriores a 1800; **su licencia (Clio Infra) no la he podido comprobar** y hay que verificarla antes de publicar esas 42 filas (las de los cuatro países y las de las cinco regiones). Se mantienen por decisión de la persona a cargo (21 de noviembre de 2026).

**Licencias (pendiente de comprobar antes de publicar los datos):** `systema_globalis` declara en su README que Gapminder lo publica con **CC BY 4.0**. El repositorio `fasttrack` y el espejo `owid-datasets` no declaran ninguna licencia; Gapminder y Our World in Data publican sus datos con CC BY 4.0 según sus sitios, pero no pude comprobarlo desde el contenedor. En todo caso hay que **citar a Gapminder, a la ONU y a Our World in Data**. **No se copia ningún dato de la Human Mortality Database**: no se redistribuye. Existe una versión por edades (15 y 45) en Our World in Data que viene de sus tablas de período; no la copié por eso.

**Licencia de lo modelado (CC BY-SA 3.0 IGO).** Los coeficientes del modelo vienen del paquete DemoTools (*Standardize, Evaluate, and Adjust Demographic Data*, de Tim Riffe y colaboradores), que se publica con **Creative Commons Reconocimiento-CompartirIgual 3.0 IGO**; son el modelo de Wilmoth et al. (2012) ajustado con la HMD. **`datos/modelo_logcuadratico.csv` y las filas de `esperanza_vida.csv` con `fuente=logquad` o `logquad-sexo` (edades 5 a 60) se comparten con esa misma licencia**, con su atribución (Wilmoth et al., 2012; DemoTools). El resto del repositorio es MIT: quien reutilice esas filas hereda la obligación de «compartir igual», y quien necesite una licencia más abierta puede usar solo las filas de las edades 0 y 5 de ambos sexos. Si la HMD pusiera alguna condición a los coeficientes derivados de sus tablas que yo no conozco, la salida es quitar esas filas (`edades_modelo=()` en `preparar_datos.py`) y quedarse con las edades 0 y 5. El código de `modelo.py` es propio (MIT).

## Epidemias y mortalidad en Londres: cómo leerlas

- **Las epidemias son una pista, no una prueba.** Una serie de muertes en una familia en un año con epidemia es compatible con ella, y nada más. Las fechas son convencionales (las pandemias de cólera, por ejemplo, se numeran distinto según la fuente) y casi todas las cifras son órdenes de magnitud de memoria (`estado=borrador`). Las únicas cifras verificadas son las tres de cólera en Gran Bretaña (31 000 en 1831-1832, 62 000 en 1849 y 14 000 en 1866, totales por brote) y la serie de Londres.
- **El alcance es por región y, a veces, por país.** `epidemias_en(1348, pais="IT")` incluye la peste negra (Italia es de Europa); con `pais="CL"` no. Una epidemia que nombra países solo alcanza a esos. La región es gruesa: «América» incluye a todo el continente aunque una epidemia no haya llegado a todas partes, y la columna `nota` lo dice cuando se sabe.
- **Londres 1629-1902.** `muertes_londres(1665)` da 97 306 muertes en el año de la Gran Peste, y `exceso_de_muertes_londres(1665)` dice que fueron casi seis veces las de los años vecinos (la mediana de ±5 años). Sirve para ver años anormales (1665, 1636), no para estimar muertes de una persona. Es la ciudad de Londres de cada época, y no representa a Inglaterra ni a Gran Bretaña.

- **Inglaterra desde 1541.** `tasas_inglaterra(1561)` da la natalidad y la mortalidad brutas (por mil habitantes) del país; `exceso_de_mortalidad_inglaterra(1561)` compara el punto con la mediana de los dos puntos de cada lado, y `anios_de_mortalidad_anormal_inglaterra()` lista los que llegan a un 15 % de exceso (1556, 1561, 1641, 1681, 1741, 1918 y 1940). **Hasta 1861 hay un punto cada cinco años** (1541, 1546…), que resume el quinquenio: un año de peste o de gripe aparece diluido, así que la serie sirve para ver épocas y no para fechar un brote (1665, por ejemplo, no tiene punto propio; para eso está Londres). Es estadística del país: no dice de qué murió nadie.

## Revisión del glosario (sin revisión nativa prevista)

El glosario lo escribió un asistente de IA y **nadie lo ha revisado**. **Decisión de la persona a cargo (18 de noviembre de 2026): no habrá revisión por hablantes nativos.** Por eso el glosario se queda como `borrador`, y lo único que respalda cada término es su **confianza** (`alta`, `media`, `baja`), que es un juicio del propio asistente, no una verificación. Hay que leerlo como una pista para entender un registro antiguo, nunca como un dato establecido: ante un término que importe, hay que contrastarlo con un diccionario histórico o con un especialista.

Qué hay en lugar de una revisión nativa, que es poco:

- **Comprobaciones mecánicas** (`generar_tabla.py --comprobar` y `pruebas.py`): cada término debe estar escrito en la escritura de su idioma (cirílico para el ruso, griego para el griego…; un término mal etiquetado falla), sus causas y códigos CIE-10 deben existir y tener la forma correcta, no puede haber términos repetidos, y los ambiguos salen con todas sus causas y con confianza `media` o `baja`. Eso detecta errores de etiquetado, **no errores de significado**.
- **Los términos más dudosos llevan confianza `baja`** (la fiebre «a secas», «dentición», «croup»…) y los que no se conocían con seguridad no se incluyeron.

Qué se conserva: `datos/revisiones.csv` (el registro de quién ha revisado qué, vacío), su informe `revisiones.md` y la pestaña «Revisar el glosario» de `vista.html` **siguen ahí por si alguna vez alguien (nativo o no) quiere revisar algo**; no se borran. Si lo hace, el procedimiento es:

1. Abre `vista.html` en un navegador (un solo archivo, sin servidor ni conexión; nada sale de tu navegador) y entra en **«Revisar el glosario»**. Elige el idioma.
2. Para cada término marca **correcto**, **corregir** o **dudoso**. Con «corregir» o «dudoso» la nota es obligatoria (qué cambiar, o por qué dudas). Escribe tu nombre (el que quieras publicar; ver [`PRIVACIDAD.md`](PRIVACIDAD.md)) y la fecha.
3. Copia las filas que genera la página y añádelas al final de `datos/revisiones.csv` en un PR, o pásaselas a quien mantiene el proyecto; después corre `python3 generar_tabla.py` para regenerar `revisiones.md`.

**Reglas:** una fila por revisor y término (si vuelves a revisar, edita tu fila: el historial queda en git). Cada fila lleva la **huella** del término (su texto, idioma, causa y confianza), y **una revisión caduca si el dato cambia después**: un «correcto» de un término que cambió deja de contar y sale como «caducado» en `revisiones.md`. Una fila de un término que ya no existe sale como «huérfana» (no hace fallar nada). Si una persona dice «correcto» y otra «corregir» o «dudoso», sale como desacuerdo. Un «correcto» es el juicio de una persona, no una garantía, y no se comprueba quién es el revisor ni su relación con el idioma: si quieres dejar constancia (lengua materna, nivel), ponla en la nota.

## Archivos

| Archivo | Contenido |
|---|---|
| `datos/causas_modernas.csv` | Las causas modernas a las que apunta el glosario: `id`, nombre en español y en inglés, CIE-10 (separados por `;`) y grupo. |
| `datos/causas_historicas.csv` | Término, idioma (ISO 639-3), `causa_id`, `confianza`, años de uso si se conocen (`desde`, `hasta`), nota, `estado` y `fuente`. |
| `datos/epidemias.csv` | Id, nombre, agente, causa moderna, años (`hasta` vacío si siguió abierta), regiones (las cinco de `Listado-paises/`, separadas por `;`), países (ISO alfa-2 separados por `;`; vacío si cubre toda la región, y entonces solo alcanza a los países de esas regiones), mortalidad en texto, muertes mínimas y máximas si hay cifras, confianza, nota, `estado` y `fuente`. |
| `datos/paises.csv` | Código ISO alfa-2, nombre y región de cada país (208), copiados de `Listado-paises/paises.csv` por `preparar_datos.py`. Los demás CSV validan sus códigos de país contra esta tabla. |
| `datos/inglaterra_tasas.csv` | Año, natalidad y mortalidad brutas (por mil) de Inglaterra y Gales, 1541-2015 (219 puntos: uno cada cinco años hasta 1861, anuales después), `estado` y `fuente`. Se rehace con `preparar_datos.py`. |
| `datos/londres_muertes.csv` | Año, muertes totales y muertes por viruela en Londres, 1629-1902 (241 años; hay huecos: 1637-1646, 1687-1700, 1832-1837…), `estado` y `fuente`. Se rehace con `preparar_datos.py`. |
| `epidemias.md` | Las epidemias en orden cronológico (generado). |
| `datos/hereditarias.csv` | Id, nombres, herencia, OMIM, su término MONDO (con el que se verificó), dónde es mayor la frecuencia, inicio típico, nota, `estado` y `fuente`. |
| `datos/hitos_registro.csv` | País (ISO alfa-2), tipo (`civil` o `parroquial`), año desde el que hay registros, nota, `estado` y `fuente`. |
| `datos/esperanza_vida.csv` | País (ISO alfa-2) o región (`R:Europa`…), período, sexo (`M`, `F`, `T`), edad, esperanza **restante** (años que le quedan a esa edad), probabilidad de sobrevivir hasta esa edad, banda de incertidumbre (`incertidumbre_baja` e `incertidumbre_alta`: medida desde 1950, supuesta antes), calidad (`A` registro civil completo, `B` reconstrucción parcial o estimación de un organismo, `C` estimación o modelo), `estado` y `fuente`. Ver «Esperanza de vida». Se genera con `preparar_datos.py`. |
| `vista.html` | Una vista web de todo (glosario, epidemias, esperanza de vida con gráfico, Londres e Inglaterra): un solo archivo que se abre en el navegador, sin servidor ni conexión. Se genera desde `vista_plantilla.html`, con `js/mortalidad.js` y los datos incrustados. |
| `php/src/Mortalidad.php` | Port a PHP de `consultas.py` (necesita las extensiones `intl` y `mbstring`). Se prueba con `php php/pruebas/ejecutar.php` y PHPStan nivel `max`. |
| `js/mortalidad.js` | Port a JavaScript de `consultas.py`, sin dependencias: lo usa `vista.html` y sirve en Node (`node --test 'js/*.test.js'`). |
| `datos/vectores.json` | Las respuestas de `consultas.py` a un conjunto fijo de entradas (generado). Los dos ports tienen que dar exactamente lo mismo. |
| `por-idioma/<código>.md` | El glosario de cada idioma (generado), con los términos de causas delicadas (suicidio, enfermedad mental, sífilis, lepra, parto) en un desplegable cerrado aparte de la tabla (ver `PRIVACIDAD.md`, §3.7). `por-idioma/README.md` es el índice. |
| `datos/revisiones.csv` | El registro de quién ha revisado qué término del glosario (idioma, término, causa, huella, resultado, revisor, fecha y nota). Está vacío y no se prevé llenarlo: ver «Revisión del glosario». |
| `revisiones.md` | Cuánto del glosario revisó una persona, por idioma, y qué requiere atención (generado). |
| `PRIVACIDAD.md` | Qué guarda esta carpeta, qué impide el código y qué hay que decidir antes de mostrar causas de muerte en fichas de personas. |
| `consultas.py` | Consultas en Python: `causa_moderna`, `epidemias_en`, `hitos_de`, `region_de`, `esperanza_restante`, `banda_esperanza`, `supervivencia`, las de edades de muerte (`probabilidad_de_llegar_a`, `supervivientes_esperados`, `fraccion_de_muertes_antes_de`, `edad_de_muerte_percentil` y `edades_de_muerte_comunes`), `muertes_londres`, `exceso_de_muertes_londres`, `tasas_inglaterra`, `exceso_de_mortalidad_inglaterra` y `anios_de_mortalidad_anormal_inglaterra`; `epidemias_en` filtra por año, región y país. |
| `descargar_fuentes.py` | Baja las fuentes de la esperanza de vida (fijadas por commit), comprueba `fuentes.sha256` y, con `--rehacer`, corre `preparar_datos.py`. |
| `preparar_datos.py` | Arma `esperanza_vida.csv` desde esas fuentes: décadas, derivación a los 5 años, edades modeladas y cruce con los códigos de país. También escribe `londres_muertes.csv`, `inglaterra_tasas.csv`, `paises.csv` y `modelo_logcuadratico.csv`. |
| `modelo.py` | El modelo log-cuadrático de Wilmoth: una tabla de vida completa desde la mortalidad de 0 a 5 años y la esperanza al nacer. |
| `leer_rda.py` | Lector mínimo de archivos `.rda` de R (solo datos; no ejecuta nada), para extraer los coeficientes del modelo. |
| `datos/modelo_logcuadratico.csv` | Los coeficientes `ax`, `bx`, `cx` y `vx` del modelo para los 24 grupos de edad (CC BY-SA 3.0 IGO; ver «Fuentes»). |
| `fuentes.sha256` | SHA-256 de cada fuente. |
| `generar_tabla.py` | Valida los CSV y genera `por-idioma/`, `epidemias.md`, `revisiones.md`, `datos/vectores.json`, `vista.html` y el «Resumen» de este README. |
| `comun.py` | Constantes (idiomas, regiones, valores permitidos) y lectura de CSV. |
| `pruebas.py` | Pruebas del validador, de la generación y de las consultas. |

Los códigos de país y las cinco regiones son los de [`Listado-paises/`](../Listado-paises/README.md) para poder cruzar las tablas. Esta carpeta **no depende de ella** para validar, probar ni consultar (los copia en `comun.py`); solo `preparar_datos.py`, al **rehacer** los datos, lee `Listado-paises/codigos_iso.csv` (para pasar los nombres y códigos de las fuentes a ISO alfa-2) y `Listado-paises/paises.csv` (para `datos/paises.csv`).

### `estado` y `fuente`

Toda fila lleva `estado`: `borrador` (escrita sin comprobar contra una fuente) o `verificado` (sale de la fuente citada). Un dato `verificado` **debe** traer su `fuente`; el validador lo exige. `verificado` dice que la fila es lo que dice su fuente, **no que la fuente sea exacta**: eso lo dice `calidad`. Hoy son `verificado` la esperanza de vida, las muertes de Londres y las tasas de Inglaterra (las genera un script desde fuentes fijadas) y tres cifras de cólera; lo demás es `borrador`.

### Idiomas

Español, inglés, portugués, francés, italiano, alemán, latín, neerlandés, sueco, danés, polaco, ruso, ucraniano, checo, griego, turco, árabe, hebreo, chino y japonés. Los códigos son **ISO 639-3** (`spa`, `eng`, `lat`…) y están en `IDIOMAS` de `comun.py`, el único lugar que hay que tocar para cambiar la lista. Las páginas de árabe y hebreo van dentro de `<div dir="rtl">`.

## Uso

```bash
python3 generar_tabla.py              # valida y regenera por-idioma/ y el Resumen
python3 generar_tabla.py --comprobar  # valida y falla si la salida no está al día (no escribe)
python3 -m unittest -v pruebas        # pruebas

# Rehacer la esperanza de vida desde las fuentes (necesita red y Listado-paises/ al lado):
python3 descargar_fuentes.py --destino fuentes --rehacer
```

```python
from consultas import (causa_moderna, epidemias_en, hitos_de, esperanza_restante, supervivencia, banda_esperanza,
                       edades_de_muerte_comunes, probabilidad_de_llegar_a, supervivientes_esperados)

causa_moderna("Hidropesía", "spa")     # ignora mayúsculas y tildes: «hidropesia» da lo mismo
epidemias_en(1348, "Europa")           # epidemias que cubren ese año y esa región
hitos_de("CL")                         # desde cuándo hay registro civil o parroquial
esperanza_restante("SE", 1805)         # 36.2: al nacer, década 1800-1809
esperanza_restante("SE", 1805, 5)      # 47.3: años que le quedan a quien cumplió 5
esperanza_restante("SE", 1805, 45)     # 22.7: a quien cumplió 45 (de los 15 años en adelante, modelada)
banda_esperanza("SE", 1805, 45)        # (bajo, alto): antes de 1950 la banda es un supuesto
esperanza_restante("GB", 1545)         # 36.4: Reino Unido antes de 1800, solo al nacer
edades_de_muerte_comunes("CL", 1895)  # {'p10': 0.4, 'p25': 1.0, 'p50': 13.0, 'p75': 67.5, 'p90': 79.2}: edades de muerte, por década
probabilidad_de_llegar_a("SE", 1805, 15)  # 0.63: de 10 recién nacidos, unos 6 llegaban a los 15
supervivientes_esperados("SE", 1805, 6, 5)  # 4.1: de 6 nacimientos, en promedio unos 4 llegaban a los 5
supervivencia("SE", 1805)              # 0.687: probabilidad de llegar a los 5
esperanza_restante("ES", 1985, 0, "F") # por sexo, desde 1950
```

Solo biblioteca estándar; Python 3.10 o más reciente.

### En PHP y en JavaScript

Las mismas consultas, con los mismos nombres en `camelCase`:

```php
require 'php/src/Mortalidad.php';
$m = new MortalidadHistorica\Mortalidad();   // lee datos/*.csv; PHP 8.1+, con intl y mbstring
$m->causaModerna('Hidropesía', 'spa');
$m->epidemiasEn(1886, null, 'CL');
$m->esperanzaRestante('SE', 1805, 5);
```

```js
const Mortalidad = require('./js/mortalidad.js');
const m = Mortalidad.crear(tablas);   // las tablas en columnas, como las incrusta vista.html
m.causaModerna('Hidropesía', 'spa');
```

Los dos ports se prueban contra `datos/vectores.json`, que genera `generar_tabla.py` con `consultas.py`: **si cambias una consulta de Python, regenera con `python3 generar_tabla.py` y sigue el cambio en `php/src/Mortalidad.php` y `js/mortalidad.js`**; sus pruebas fallan si difieren en una sola respuesta. El port a JavaScript no tiene el plegado completo de mayúsculas de Unicode: cubre las diferencias que importan a los datos (ß y la sigma final).

## Edades de muerte y supervivencia, por país y década

Cinco consultas (en Python, PHP y JavaScript, y en la pestaña «Edades de muerte» de la vista) responden preguntas de **población**: «¿a qué edades se moría en Chile en los años 1890?» o «¿cuántos de seis niños nacidos en la Suecia de 1800 llegaban a los cinco años?». Salen de la tabla de vida que arma el modelo log-cuadrático (`modelo.py`) para ese país, esa década y ese sexo.

| Consulta | Qué devuelve |
|---|---|
| `probabilidad_de_llegar_a(país, año, edad, sexo)` | La probabilidad (0 a 1) de que un recién nacido llegue con vida a esa edad (0 a 110). |
| `supervivientes_esperados(país, año, nacidos, edad, sexo)` | `nacidos` por la probabilidad anterior: un valor esperado de la población (de 6 nacimientos, «4,1»), no lo que pasó en una familia. |
| `fraccion_de_muertes_antes_de(país, año, edad, desde_edad, sexo)` | De quienes llegaban a `desde_edad`, la fracción que moría antes de cumplir `edad`. |
| `edad_de_muerte_percentil(país, año, p, desde_edad, sexo)` | La edad por debajo de la cual había muerto la fracción `p` (con `p = 0,5`, la mediana). |
| `edades_de_muerte_comunes(país, año, desde_edad, sexo)` | `p10`, `p25`, `p50`, `p75` y `p90`: de `p10` a `p90` está el 80 % central de las muertes. |

**El contrato de privacidad** (decisión de la persona a cargo, 21 de noviembre de 2026: «solo estadística por país y década»): estas funciones **solo reciben país, año, sexo y edades o cantidades**; nunca una ficha, un nombre, un apellido ni un origen. Una prueba (`test_el_contrato_ninguna_funcion_recibe_una_persona`) lo fija: si alguien agrega un parámetro que no sea de esa lista (`PARAMETROS_PERMITIDOS` de `consultas.py`), falla. Son cifras de **toda una población**, modeladas (calidad C) y, antes de 1950, apenas estimaciones. **No sirven para decidir si algo es verosímil en una familia concreta** (la mortalidad infantil de una familia no es la de su país) **ni para inferir riesgos de salud de nadie**; la plataforma que las use debe decirlo, como lo dice la vista. No hay datos (`None`) antes de 1800, ni para países sin serie de mortalidad de 0 a 5 años; mujeres y hombres, solo desde 1950, con la mortalidad infantil de ambos sexos.

Se prueban contra los mismos vectores que las demás consultas (`datos/vectores.json`): PHP y JavaScript dan lo mismo que Python.

## Qué no hace

- **No diagnostica ni estima la causa de muerte de una persona.** `causa_moderna` devuelve las correspondencias posibles de un **término**, con su confianza.
- **No predice cuánto vivirá ni vivió una persona.** `esperanza_restante` y `supervivencia` son cifras de período de toda una población, con `None` si no hay dato (nunca inventa ni interpola entre edades).
- **No evalúa a personas ni familias.** No hay ninguna función que reciba una ficha, un apellido o un origen y devuelva un riesgo. Una pantalla que quiera mostrar causas de muerte o enfermedades de personas es otro cambio, con su revisión de privacidad (ver [`CUMPLIMIENTO-LEY-21719.md`](../CUMPLIMIENTO-LEY-21719.md)).
- **No trae datos de personas reales**; los ejemplos de las pruebas son inventados.

## Plan

1. **Fase 1 (hecha):** esqueleto, esquemas, validador, glosario semilla (20 idiomas), epidemias, hereditarias e hitos de registro civil de partida, y CI.
2. **Fase 2 (hecha):** esperanza de vida 1800-2009 por país, a los 0 y 5 años (medidas o derivadas) y a los 15, 30, 45 y 60 (modeladas), con las consultas `esperanza_restante` y `supervivencia`. **2b (hecha):** bandas de incertidumbre, mujeres y hombres a todas las edades (con la mortalidad infantil de ambos sexos) y, antes de 1800, la esperanza al nacer de Reino Unido, Suecia, Finlandia y Dinamarca. **Queda fuera:** edades mayores antes de 1800, y la mortalidad infantil por sexo (hace falta la ONU o el Banco Mundial por sexo). Reemplazar el modelo por tablas medidas de un país que las publique (el INE de Chile, por ejemplo) mejoraría las cifras donde las haya.
3. **Fase 3 (hecha, en parte):** 28 epidemias, con las de América y Chile, las muertes por año en Londres y las tasas de Inglaterra desde 1541. **Falta verificar las epidemias:** solo 3 cifras de cólera lo están (las series de Londres e Inglaterra sí); el resto es de memoria. Desde el contenedor no llega ninguna fuente de referencia (Wikipedia, OMS, SciELO…), solo GitHub, así que verificarlas y poner cifras para Chile (viruela de 1872, cólera de 1886) hay que hacerlo con acceso a esas fuentes.
4. **Fase 4 (hecha, en parte):** el glosario creció a más de 800 términos y 54 causas, los códigos OMIM están verificados y hay un registro de revisión que **no se prevé usar**: la persona a cargo decidió el 18 de noviembre de 2026 que no habrá revisión por hablantes nativos, así que el glosario sigue en `borrador` de forma permanente. **Queda:** ampliar los idiomas menos cubiertos (neerlandés, turco, griego, checo, ucraniano y árabe tienen entre 19 y 24 términos, sobre todo de las causas más comunes; las causas raras y los términos regionales o antiguos casi no están).
5. **Fase 5 (hecha):** vista HTML (`vista.html`) y los ports a PHP y a JavaScript, probados con los mismos vectores.

Fuentes previstas: Our World in Data, Gapminder, Clio-Infra y UN WPP (licencias abiertas); la Human Mortality Database no se redistribuye (solo se usa, a través del modelo de Wilmoth, su efecto en unos coeficientes). Para el glosario: las *Bills of Mortality*, la nosología de Farr, las clasificaciones de Bertillon y las revisiones de la CIE.
