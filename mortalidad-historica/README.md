# Mortalidad histórica

Datos para leer con criterio las edades, las enfermedades y las causas de muerte de los antepasados en un árbol genealógico:

- **Esperanza de vida a lo largo de los siglos**, por país o región, sexo y **edad** (no solo al nacer; ver «Por qué no basta la esperanza de vida al nacer»).
- **Epidemias y pandemias** (28, de la peste de Justiniano a la gripe de 1968), con sus años, regiones y, cuando hace falta, países, para notar cuándo una serie de muertes en una familia tiene una explicación. Se leen de corrido en [`epidemias.md`](epidemias.md).
- **Las muertes por año en Londres, 1629-1902** (Bills of Mortality y los registros que las siguieron): la única serie anual de muertes de esta carpeta, con la que se ve cuánto se disparó un año como 1665.
- **Un glosario de causas de defunción históricas** («hidropesía», «alferecía», «Schwindsucht», «bexigas»…) en 20 idiomas, con la causa moderna a la que suelen corresponder y qué tan segura es esa correspondencia.
- **Enfermedades hereditarias**, como contexto médico y poblacional.
- **Cuándo empezó el registro civil** en cada país, para saber qué fiabilidad esperar de una edad o una causa.

> **Estado: fase 2 (esperanza de vida).** La esperanza de vida (≈10 000 filas) viene de fuentes abiertas fijadas por commit y se rehace con un script (`estado=verificado`: la fila es lo que dice su fuente; la calidad de la fuente es otra cosa, ver `calidad` y «Esperanza de vida: de dónde sale y cómo leerla»). También son `verificado` las muertes por año en Londres (1629-1902) y tres cifras de cólera en Gran Bretaña. Todo lo demás sigue siendo un **borrador escrito por un asistente de IA**: el glosario, casi todas las epidemias, las hereditarias y los hitos de registro civil no están verificados contra una fuente (`estado=borrador`) y **ninguna persona nativa ha revisado los términos**. Por ahora la esperanza de vida cubre solo las edades 0 y 5. Ver «Plan».

> **Antes de usar esto con datos de personas, lee [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md).** La salud y las causas de muerte son datos sensibles, y los de un antepasado alcanzan a sus descendientes vivos. Esta carpeta solo guarda **estadística y vocabulario**, nunca datos de personas reales, y no ofrece ninguna función que mire a una persona o una familia. Ver «Qué no hace».

## Resumen

Cifras que cambian con los datos. Las escribe `generar_tabla.py` y `--comprobar` falla si no están al día.

<!-- resumen: lo escribe generar_tabla.py; no editar a mano -->
- **Causas modernas:** 17; **términos históricos:** 176 en 20 de 20 idiomas (confianza alta: 139, media: 35, baja: 2).
- **Idiomas sin términos:** ninguno.
- **Causas modernas sin ningún término histórico:** ninguna.
- **Epidemias:** 28 (3 verificadas). **Enfermedades hereditarias:** 12. **Hitos de registro civil:** 6. **Países:** 208.
- **Muertes en Londres:** 241 años (1629-1902).
- **Esperanza de vida:** 10311 filas (206 países o regiones).
- **Filas verificadas contra su fuente:** 10555 de 10774.
<!-- fin del resumen -->

## Por qué no basta la esperanza de vida al nacer

Un «30 años» en 1850 no quiere decir que la gente muriera a los 30. La cifra al nacer está arrastrada hacia abajo por la mortalidad infantil: en muchas poblaciones murió antes de los 5 años una cuarta parte de los niños o más. Quien sobrevivía a la infancia solía vivir mucho más. Con los datos de esta carpeta: en Suecia, en 1800-1809, la esperanza al nacer era de 36 años, pero a quien cumplía 5 le quedaban otros 47 (llegaba a los 52 en promedio), y solo siete de cada diez niños llegaban a esa edad. Por eso `esperanza_vida.csv` guarda la **esperanza restante a más de una edad** y la **probabilidad de sobrevivir hasta esa edad**: con eso se puede juzgar si una ficha es plausible (edad al morir, hijos que llegaron a adultos, hermanos esperados) sin confundirse con el promedio. Hoy están las edades 0 y 5; las de 15 a 60 faltan (ver «Plan»).

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
- Los códigos OMIM están escritos de memoria y **pendientes de verificar** contra OMIM (todas las filas son `borrador`).

## Esperanza de vida: de dónde sale y cómo leerla

`datos/esperanza_vida.csv` tiene una fila por país, **década** (1800-1809 … 2000-2009), sexo y edad: ≈10 300 filas para 206 países y territorios. Se rehace con `descargar_fuentes.py` y `preparar_datos.py`; no se edita a mano.

| Qué | Edad | Sexo | Años | Cómo se obtiene |
|---|---|---|---|---|
| Esperanza de vida al nacer | 0 | T (ambos) | 1800-2009 | Directa de la fuente: Gapminder hasta 1949 y la ONU (revisión 2017) desde 1950. |
| Esperanza restante y supervivencia a los 5 años | 5 | T | 1800-2009 | **Derivada** de la esperanza al nacer y de la mortalidad de 0 a 5 años de Gapminder. |
| Esperanza de vida al nacer por sexo | 0 | M y F | 1950-2009 | Directa de Gapminder (a partir de la ONU). |

**La derivación a los 5 años.** La esperanza al nacer es el promedio de dos grupos: los niños que mueren antes de los 5 años (una fracción *q5*, que viven en promedio unos 1,0 años) y los que llegan a los 5 (que viven 5 más su esperanza restante *e5*). De `e0 = q5·a + (1−q5)·(5+e5)` se despeja `e5`. El único supuesto es `a` (la edad media de muerte de quien no llega a los 5), que se fija en 1,0 año; si en realidad fuera 0,5 o 1,5, `e5` cambia menos de 0,35 años incluso con una mortalidad de 400 por mil. Siempre sale `5 + e5 ≥ e0`, y las pruebas lo comprueban en todas las filas.

**Qué hay que saber antes de usar una cifra:**

- **Antes de 1950 es una estimación, no una medición** (`calidad` C). Gapminder junta pocos datos reales y **interpola** entre ellos: cuando varias décadas seguidas de un país tienen exactamente el mismo valor (Chile, 1800 y 1850: 32,0), es un valor supuesto, y los saltos bruscos entre dos décadas (España, esperanza a los 5 años de 47,9 en 1800 y 39,2 en 1850) suelen ser un artefacto de la interpolación. Desde 1950 es una estimación de la ONU (`calidad` B).
- **No hay bandas de incertidumbre** (`incertidumbre_baja` e `incertidumbre_alta` quedan vacías): ninguna fuente las publica, y inventarlas daría una precisión falsa. Lo único cuantificado es el efecto del supuesto `a`, arriba.
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
| `thomas-2015-owid` | Muertes por cólera en Gran Bretaña en los brotes del siglo XIX | Our World in Data, a partir de Thomas (2015) y Pearson Schools and Colleges |

`owid-larga+gapminder-0-5` es una fila derivada de `owid-larga` y `gapminder-0-5`.

**Licencias (pendiente de comprobar antes de publicar los datos):** `systema_globalis` declara en su README que Gapminder lo publica con **CC BY 4.0**. El repositorio `fasttrack` y el espejo `owid-datasets` no declaran ninguna licencia; Gapminder y Our World in Data publican sus datos con CC BY 4.0 según sus sitios, pero no pude comprobarlo desde el contenedor. En todo caso hay que **citar a Gapminder, a la ONU y a Our World in Data**. **No se usa la Human Mortality Database**: no se redistribuye. Existe una versión por edades (15 y 45) en Our World in Data, pero viene de la HMD y de sus tablas de período; no la copié por eso.

## Epidemias y mortalidad en Londres: cómo leerlas

- **Las epidemias son una pista, no una prueba.** Una serie de muertes en una familia en un año con epidemia es compatible con ella, y nada más. Las fechas son convencionales (las pandemias de cólera, por ejemplo, se numeran distinto según la fuente) y casi todas las cifras son órdenes de magnitud de memoria (`estado=borrador`). Las únicas cifras verificadas son las tres de cólera en Gran Bretaña (31 000 en 1831-1832, 62 000 en 1849 y 14 000 en 1866, totales por brote) y la serie de Londres.
- **El alcance es por región y, a veces, por país.** `epidemias_en(1348, pais="IT")` incluye la peste negra (Italia es de Europa); con `pais="CL"` no. Una epidemia que nombra países solo alcanza a esos. La región es gruesa: «América» incluye a todo el continente aunque una epidemia no haya llegado a todas partes, y la columna `nota` lo dice cuando se sabe.
- **Londres 1629-1902.** `muertes_londres(1665)` da 97 306 muertes en el año de la Gran Peste, y `exceso_de_muertes_londres(1665)` dice que fueron casi seis veces las de los años vecinos (la mediana de ±5 años). Sirve para ver años anormales (1665, 1636), no para estimar muertes de una persona. Es la ciudad de Londres de cada época, y no representa a Inglaterra ni a Gran Bretaña.

## Archivos

| Archivo | Contenido |
|---|---|
| `datos/causas_modernas.csv` | Las causas modernas a las que apunta el glosario: `id`, nombre en español y en inglés, CIE-10 (separados por `;`) y grupo. |
| `datos/causas_historicas.csv` | Término, idioma (ISO 639-3), `causa_id`, `confianza`, años de uso si se conocen (`desde`, `hasta`), nota, `estado` y `fuente`. |
| `datos/epidemias.csv` | Id, nombre, agente, causa moderna, años (`hasta` vacío si siguió abierta), regiones (las cinco de `Listado-paises/`, separadas por `;`), países (ISO alfa-2 separados por `;`; vacío si cubre toda la región, y entonces solo alcanza a los países de esas regiones), mortalidad en texto, muertes mínimas y máximas si hay cifras, confianza, nota, `estado` y `fuente`. |
| `datos/paises.csv` | Código ISO alfa-2, nombre y región de cada país (208), copiados de `Listado-paises/paises.csv` por `preparar_datos.py`. Los demás CSV validan sus códigos de país contra esta tabla. |
| `datos/londres_muertes.csv` | Año, muertes totales y muertes por viruela en Londres, 1629-1902 (241 años; hay huecos: 1637-1646, 1687-1700, 1832-1837…), `estado` y `fuente`. Se rehace con `preparar_datos.py`. |
| `epidemias.md` | Las epidemias en orden cronológico (generado). |
| `datos/hereditarias.csv` | Id, nombres, herencia, OMIM, dónde es mayor la frecuencia, inicio típico, nota, `estado` y `fuente`. |
| `datos/hitos_registro.csv` | País (ISO alfa-2), tipo (`civil` o `parroquial`), año desde el que hay registros, nota, `estado` y `fuente`. |
| `datos/esperanza_vida.csv` | País (ISO alfa-2) o región (`R:Europa`…), período, sexo (`M`, `F`, `T`), edad, esperanza **restante** (años que le quedan a esa edad), probabilidad de sobrevivir hasta esa edad, banda de incertidumbre (vacía hoy), calidad (`A` registro civil completo, `B` reconstrucción parcial o estimación de un organismo, `C` estimación o modelo), `estado` y `fuente`. Ver «Esperanza de vida». Se genera con `preparar_datos.py`. |
| `vista.html` | Una vista web de todo (glosario, epidemias, esperanza de vida con gráfico y Londres): un solo archivo que se abre en el navegador, sin servidor ni conexión. Se genera desde `vista_plantilla.html`, con `js/mortalidad.js` y los datos incrustados. |
| `php/src/Mortalidad.php` | Port a PHP de `consultas.py` (necesita las extensiones `intl` y `mbstring`). Se prueba con `php php/pruebas/ejecutar.php` y PHPStan nivel `max`. |
| `js/mortalidad.js` | Port a JavaScript de `consultas.py`, sin dependencias: lo usa `vista.html` y sirve en Node (`node --test 'js/*.test.js'`). |
| `datos/vectores.json` | Las respuestas de `consultas.py` a un conjunto fijo de entradas (generado). Los dos ports tienen que dar exactamente lo mismo. |
| `por-idioma/<código>.md` | El glosario de cada idioma (generado). `por-idioma/README.md` es el índice. |
| `consultas.py` | Consultas en Python: `causa_moderna`, `epidemias_en`, `hitos_de`, `region_de`, `esperanza_restante`, `supervivencia`, `muertes_londres` y `exceso_de_muertes_londres`; `epidemias_en` filtra por año, región y país. |
| `descargar_fuentes.py` | Baja las fuentes de la esperanza de vida (fijadas por commit), comprueba `fuentes.sha256` y, con `--rehacer`, corre `preparar_datos.py`. |
| `preparar_datos.py` | Arma `esperanza_vida.csv` desde esas fuentes: décadas, derivación a los 5 años y cruce con los códigos de país. |
| `fuentes.sha256` | SHA-256 de cada fuente. |
| `generar_tabla.py` | Valida los CSV y genera `por-idioma/` y el «Resumen» de este README. |
| `comun.py` | Constantes (idiomas, regiones, valores permitidos) y lectura de CSV. |
| `pruebas.py` | Pruebas del validador, de la generación y de las consultas. |

Los códigos de país y las cinco regiones son los de [`Listado-paises/`](../Listado-paises/README.md) para poder cruzar las tablas. Esta carpeta **no depende de ella** para validar, probar ni consultar (los copia en `comun.py`); solo `preparar_datos.py`, al **rehacer** los datos, lee `Listado-paises/codigos_iso.csv` (para pasar los nombres y códigos de las fuentes a ISO alfa-2) y `Listado-paises/paises.csv` (para `datos/paises.csv`).

### `estado` y `fuente`

Toda fila lleva `estado`: `borrador` (escrita sin comprobar contra una fuente) o `verificado` (sale de la fuente citada). Un dato `verificado` **debe** traer su `fuente`; el validador lo exige. `verificado` dice que la fila es lo que dice su fuente, **no que la fuente sea exacta**: eso lo dice `calidad`. Hoy solo la esperanza de vida es `verificado` (la genera un script desde fuentes fijadas); lo demás es `borrador`.

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
from consultas import causa_moderna, epidemias_en, hitos_de, esperanza_restante, supervivencia

causa_moderna("Hidropesía", "spa")     # ignora mayúsculas y tildes: «hidropesia» da lo mismo
epidemias_en(1348, "Europa")           # epidemias que cubren ese año y esa región
hitos_de("CL")                         # desde cuándo hay registro civil o parroquial
esperanza_restante("SE", 1805)         # 36.2: al nacer, década 1800-1809
esperanza_restante("SE", 1805, 5)      # 47.3: años que le quedan a quien cumplió 5
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

## Qué no hace

- **No diagnostica ni estima la causa de muerte de una persona.** `causa_moderna` devuelve las correspondencias posibles de un **término**, con su confianza.
- **No predice cuánto vivirá ni vivió una persona.** `esperanza_restante` y `supervivencia` son cifras de período de toda una población, con `None` si no hay dato (nunca inventa ni interpola entre edades).
- **No evalúa a personas ni familias.** No hay ninguna función que reciba una ficha, un apellido o un origen y devuelva un riesgo. Una pantalla que quiera mostrar causas de muerte o enfermedades de personas es otro cambio, con su revisión de privacidad (ver [`CUMPLIMIENTO-LEY-21719.md`](../CUMPLIMIENTO-LEY-21719.md)).
- **No trae datos de personas reales**; los ejemplos de las pruebas son inventados.

## Plan

1. **Fase 1 (hecha):** esqueleto, esquemas, validador, glosario semilla (20 idiomas), epidemias, hereditarias e hitos de registro civil de partida, y CI.
2. **Fase 2 (hecha, en parte):** esperanza de vida 1800-2009 por país, a los 0 y 5 años, con las consultas `esperanza_restante` y `supervivencia`. **Falta (fase 2b):** las edades de 15 a 60, que son las que más sirven para juzgar una vida adulta, y los períodos anteriores a 1800. No hay una fuente abierta por país y edad que se pueda redistribuir (la HMD no lo permite); las opciones son pedirle permiso a la HMD, usar tablas modelo (Coale-Demeny o similares) con su incertidumbre explícita, o cargar tablas de un país que sí publique las suyas (INE de Chile, por ejemplo).
3. **Fase 3 (hecha, en parte):** 28 epidemias, con las de América y Chile, y las muertes por año en Londres. **Falta verificarlas:** solo 3 cifras de cólera y la serie de Londres lo están; el resto es de memoria. Desde el contenedor no llega ninguna fuente de referencia (Wikipedia, OMS, SciELO…), solo GitHub, así que verificarlas y poner cifras para Chile (viruela de 1872, cólera de 1886) hay que hacerlo con acceso a esas fuentes.
4. **Fase 4:** ampliar y verificar el glosario por idioma, y un registro de revisión por hablantes nativos.
5. **Fase 5 (hecha):** vista HTML (`vista.html`) y los ports a PHP y a JavaScript, probados con los mismos vectores.

Fuentes previstas: Our World in Data, Gapminder, Clio-Infra y UN WPP (licencias abiertas); la Human Mortality Database no se redistribuye y solo se citaría. Para el glosario: las *Bills of Mortality*, la nosología de Farr, las clasificaciones de Bertillon y las revisiones de la CIE.
