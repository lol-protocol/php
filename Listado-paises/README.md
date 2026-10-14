# Listado de países

Tabla de países con sus características básicas y dos índices propios para decidir dónde
enfocar y dónde no operar:

- **Índice de genealogía (IG):** qué tanto le interesa la genealogía a cada país.
- **Índice de operabilidad (IO):** qué tan bueno es para nosotros operar ahí.

Cada fila lleva un comentario que explica el puntaje (por ejemplo, por qué se excluye a Cuba,
Camboya o Vietnam).

## Archivos

- `paises.csv`: los datos. Es la fuente de verdad, una fila por país.
- `tabla.md`: la tabla legible, generada desde `paises.csv` y ordenada por prioridad.
- `generar_tabla.py`: valida `paises.csv` y regenera `tabla.md`. Solo usa la biblioteca estándar de Python 3.

Para regenerar la tabla después de editar el CSV:

```bash
python3 generar_tabla.py
```

El script se detiene con un mensaje si una fila está mal (encabezado distinto, índice fuera de
1–5, código repetido, número mal formado).

## Alcance

193 Estados miembros de la ONU, más Taiwán, Palestina, Kosovo, Hong Kong, Macao y Puerto Rico
(marcados como `territorio` o `entidad disputada`). No incluye la Ciudad del Vaticano ni otros
territorios menores.

## Columnas de `paises.csv`

| Columna | Qué es |
|---|---|
| `codigo` | Código ISO 3166-1 alfa-2 (como en el catálogo de `cobros-ingresos-funnels/`). Kosovo usa `XK`, que no es ISO oficial. |
| `pais` | Nombre en español. |
| `tipo` | `estado`, `territorio` o `entidad disputada`. |
| `region` | África, América, Asia, Europa u Oceanía. |
| `poblacion_millones` | Población actual, en millones. **Sin contrastar** (ver «Estado de los datos»). |
| `poblacion_historica_acumulada` | Personas que han nacido o vivido en el país a lo largo de la historia. **Vacía: sin dato** (ver «Pendientes»). |
| `idh` | Índice de Desarrollo Humano. **Sin contrastar.** |
| `pib_per_capita_usd` | PIB per cápita nominal en dólares. **Sin contrastar.** |
| `indice_genealogia` | IG, de 1 a 5 (rúbrica abajo). |
| `indice_operabilidad` | IO, de 1 a 5 (rúbrica abajo). |
| `comentarios` | Motivo de los puntajes. |

Las columnas `prioridad` y `excluido` no están en el CSV: `generar_tabla.py` las calcula.

## Índices

**Índice de genealogía (IG, 1–5).** Mide la demanda y la oferta: cuánto interesa el linaje en el
país (tradición y diáspora que busca sus raíces) y qué tan buenos son sus registros (civiles y
parroquiales, digitalizados y accesibles).

- 5: demanda alta y registros excelentes y accesibles en línea.
- 4: demanda alta, o registros muy buenos con acceso parcial.
- 3: interés medio, o registros buenos pero dispersos.
- 2: interés bajo, o registros escasos o restringidos.
- 1: prácticamente nulo, o sin acceso a los registros.

**Índice de operabilidad (IO, 1–5).** Mide qué tan bueno es operar ahí: estabilidad política,
sanciones, pagos e internet, marco legal de datos personales e idioma.

- 5: estable, sin sanciones, con pagos y datos sin fricción.
- 4: operable con fricciones menores (burocracia, regulación de datos exigente).
- 3: operable con cuidado (riesgo político o cambiario, pagos limitados).
- 2: difícil (sanciones parciales, control estatal de internet, inestabilidad).
- 1: inviable (sanciones amplias, guerra o control total).

**Prioridad** = IG × IO, de 1 a 25. Sirve para ordenar la tabla; no es un tercer índice.

**Excluido** = IO ≤ 2. El umbral está en `UMBRAL_EXCLUSION` de `generar_tabla.py`. Con
el umbral actual quedan excluidos 55 países, entre ellos Cuba, Camboya y Vietnam.

Los dos índices son juicio propio, no datos oficiales. Conviene que la persona que decide los revise.

## Estado de los datos

Léelo antes de usar la tabla para decidir.

- **Población actual, IDH y PIB per cápita: son referencias cargadas de memoria, no consultadas.**
  En la sesión donde se generó la tabla no se pudo abrir ninguna fuente oficial: los dominios del
  Banco Mundial, el PNUD y la ONU no resolvían. Las cifras son aproximadas y redondeadas. Hay que
  contrastarlas antes de decidir con ellas, con estas fuentes:
  - Banco Mundial: `SP.POP.TOTL` (población) y `NY.GDP.PCAP.CD` (PIB per cápita).
  - ONU, *World Population Prospects* (población).
  - PNUD, *Informe sobre Desarrollo Humano* (IDH).
  - FMI, *World Economic Outlook* (PIB per cápita).
- **Celdas vacías (sin dato):** cuando no hay una cifra fiable se deja vacía. Por ejemplo, IDH de
  Corea del Norte, Palestina, Siria o Nauru, o PIB per cápita de Cuba o Corea del Norte.
- **Población histórica acumulada: no hay dato.** No existe una fuente pública por país. La única
  cifra que encontré es la estimación mundial del Population Reference Bureau (PRB), de unos
  117 000 millones de personas nacidas desde la prehistoria, sin desglose por país.

## Pendientes

1. **Población histórica acumulada.** Hay que definir un método. Una opción es sumar los
   nacimientos anuales de cada país desde 1800, con la población histórica y la tasa de natalidad
   de series como Maddison o HYDE, y tratar lo anterior a 1800 aparte. Sin eso, la columna queda vacía.
2. **Contrastar población, IDH y PIB per cápita** con las fuentes oficiales y actualizar `paises.csv`.
3. **Revisar IG e IO** con la persona que decide. Son juicios, y la rúbrica es el criterio para cambiarlos.
4. **Decidir el umbral de exclusión.** Con IO ≤ 2 quedan excluidos 55 países. Si es demasiado,
   se puede bajar a IO ≤ 1 y excluir a Cuba, Camboya y Vietnam a mano.
5. **Agregar este proyecto a la tabla de `README.md` de la raíz.** Ese archivo es compartido, así
   que el cambio va en un PR aparte.
