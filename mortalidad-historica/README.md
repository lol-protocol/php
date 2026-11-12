# Mortalidad histórica

Datos para leer con criterio las edades, las enfermedades y las causas de muerte de los antepasados en un árbol genealógico:

- **Esperanza de vida a lo largo de los siglos**, por país o región, sexo y **edad** (no solo al nacer; ver «Por qué no basta la esperanza de vida al nacer»).
- **Epidemias y pandemias**, con sus años y regiones, para notar cuándo una serie de muertes en una familia tiene una explicación.
- **Un glosario de causas de defunción históricas** («hidropesía», «alferecía», «Schwindsucht», «bexigas»…) en 20 idiomas, con la causa moderna a la que suelen corresponder y qué tan segura es esa correspondencia.
- **Enfermedades hereditarias**, como contexto médico y poblacional.
- **Cuándo empezó el registro civil** en cada país, para saber qué fiabilidad esperar de una edad o una causa.

> **Estado: fase 1 (esqueleto y datos semilla).** Los datos de este momento son un **borrador escrito por un asistente de IA**: ninguna fila está verificada contra su fuente (`estado=borrador`) y **ninguna persona nativa ha revisado los términos**. Las tablas de epidemias, hereditarias y registro civil son una semilla pequeña; la de esperanza de vida está vacía (su esquema y su validador ya funcionan). Ver «Plan».

> **Antes de usar esto con datos de personas, lee [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md).** La salud y las causas de muerte son datos sensibles, y los de un antepasado alcanzan a sus descendientes vivos. Esta carpeta solo guarda **estadística y vocabulario**, nunca datos de personas reales, y no ofrece ninguna función que mire a una persona o una familia. Ver «Qué no hace».

## Resumen

Cifras que cambian con los datos. Las escribe `generar_tabla.py` y `--comprobar` falla si no están al día.

<!-- resumen: lo escribe generar_tabla.py; no editar a mano -->
- **Causas modernas:** 13; **términos históricos:** 124 en 20 de 20 idiomas (confianza alta: 88, media: 34, baja: 2).
- **Idiomas sin términos:** ninguno.
- **Causas modernas sin ningún término histórico:** ninguna.
- **Epidemias:** 6. **Enfermedades hereditarias:** 12. **Hitos de registro civil:** 6.
- **Esperanza de vida:** 0 filas (todavía sin datos).
- **Filas verificadas contra su fuente:** 0 de 148.
<!-- fin del resumen -->

## Por qué no basta la esperanza de vida al nacer

Un «30 años» en 1850 no quiere decir que la gente muriera a los 30. La cifra al nacer está arrastrada hacia abajo por la mortalidad infantil: en muchas poblaciones murió antes de los 5 años una cuarta parte de los niños o más. Quien sobrevivía a la infancia solía vivir mucho más: una persona de 20 años podía esperar otros 35 o 40. Por eso `esperanza_vida.csv` guarda la **esperanza restante a varias edades** y, cuando se conoce, la **probabilidad de sobrevivir hasta esa edad**: con eso se puede juzgar si una ficha es plausible (edad al morir, hijos que llegaron a adultos, hermanos esperados) sin confundirse con el promedio.

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

## Archivos

| Archivo | Contenido |
|---|---|
| `datos/causas_modernas.csv` | Las causas modernas a las que apunta el glosario: `id`, nombre en español y en inglés, CIE-10 (separados por `;`) y grupo. |
| `datos/causas_historicas.csv` | Término, idioma (ISO 639-3), `causa_id`, `confianza`, años de uso si se conocen (`desde`, `hasta`), nota, `estado` y `fuente`. |
| `datos/epidemias.csv` | Id, nombre, agente, causa moderna, años (`hasta` vacío si siguió abierta), regiones (las cinco de `Listado-paises/`, separadas por `;`), mortalidad en texto, muertes mínimas y máximas si hay cifras, confianza, nota, `estado` y `fuente`. |
| `datos/hereditarias.csv` | Id, nombres, herencia, OMIM, dónde es mayor la frecuencia, inicio típico, nota, `estado` y `fuente`. |
| `datos/hitos_registro.csv` | País (ISO alfa-2), tipo (`civil` o `parroquial`), año desde el que hay registros, nota, `estado` y `fuente`. |
| `datos/esperanza_vida.csv` | País (ISO alfa-2) o región (`R:Europa`…), período, sexo (`M`, `F`, `T`), edad, esperanza restante, probabilidad de sobrevivir hasta esa edad, banda de incertidumbre, calidad (`A` registro civil completo, `B` reconstrucción parcial, `C` estimación), `estado` y `fuente`. |
| `por-idioma/<código>.md` | El glosario de cada idioma (generado). `por-idioma/README.md` es el índice. |
| `consultas.py` | Consultas en Python: `causa_moderna`, `epidemias_en`, `hitos_de`. |
| `generar_tabla.py` | Valida los CSV y genera `por-idioma/` y el «Resumen» de este README. |
| `comun.py` | Constantes (idiomas, regiones, valores permitidos) y lectura de CSV. |
| `pruebas.py` | Pruebas del validador, de la generación y de las consultas. |

Los códigos de país y las cinco regiones son los de [`Listado-paises/`](../Listado-paises/README.md) para poder cruzar las tablas, pero esta carpeta **no depende de ella**: los copia en `comun.py`.

### `estado` y `fuente`

Toda fila lleva `estado`: `borrador` (escrita sin comprobar contra una fuente) o `verificado`. Un dato `verificado` **debe** traer su `fuente`; el validador lo exige. Nada es `verificado` todavía.

### Idiomas

Español, inglés, portugués, francés, italiano, alemán, latín, neerlandés, sueco, danés, polaco, ruso, ucraniano, checo, griego, turco, árabe, hebreo, chino y japonés. Los códigos son **ISO 639-3** (`spa`, `eng`, `lat`…) y están en `IDIOMAS` de `comun.py`, el único lugar que hay que tocar para cambiar la lista. Las páginas de árabe y hebreo van dentro de `<div dir="rtl">`.

## Uso

```bash
python3 generar_tabla.py              # valida y regenera por-idioma/ y el Resumen
python3 generar_tabla.py --comprobar  # valida y falla si la salida no está al día (no escribe)
python3 -m unittest -v pruebas        # pruebas
```

```python
from consultas import causa_moderna, epidemias_en, hitos_de

causa_moderna("Hidropesía", "spa")     # ignora mayúsculas y tildes: «hidropesia» da lo mismo
epidemias_en(1348, "Europa")           # epidemias que cubren ese año y esa región
hitos_de("CL")                         # desde cuándo hay registro civil o parroquial
```

Solo biblioteca estándar; Python 3.10 o más reciente.

## Qué no hace

- **No diagnostica ni estima la causa de muerte de una persona.** `causa_moderna` devuelve las correspondencias posibles de un **término**, con su confianza.
- **No evalúa a personas ni familias.** No hay ninguna función que reciba una ficha, un apellido o un origen y devuelva un riesgo. Una pantalla que quiera mostrar causas de muerte o enfermedades de personas es otro cambio, con su revisión de privacidad (ver [`CUMPLIMIENTO-LEY-21719.md`](../CUMPLIMIENTO-LEY-21719.md)).
- **No trae datos de personas reales**; los ejemplos de las pruebas son inventados.

## Plan

1. **Fase 1 (esta):** esqueleto, esquemas, validador, glosario semilla (20 idiomas), epidemias, hereditarias e hitos de registro civil de partida, y CI.
2. **Fase 2:** esperanza de vida (≈1800 a hoy con las fuentes abiertas; antes, por regiones y con bandas de incertidumbre) y las consultas `esperanza_restante` y `supervivencia`.
3. **Fase 3:** más epidemias, con cifras y fuentes verificadas, y las de América y Chile.
4. **Fase 4:** ampliar y verificar el glosario por idioma, y un registro de revisión por hablantes nativos.
5. **Fase 5:** vista HTML y un port a PHP probado con los mismos vectores.

Fuentes previstas: Our World in Data, Gapminder, Clio-Infra y UN WPP (licencias abiertas); la Human Mortality Database no se redistribuye y solo se citaría. Para el glosario: las *Bills of Mortality*, la nosología de Farr, las clasificaciones de Bertillon y las revisiones de la CIE.
