# Revisión por hablantes nativos

Generado por `generar-tabla.py` a partir de `revisiones.csv`. No editar a mano.

Casi todo el contenido de este proyecto (traducciones de nombres, significados, léxico y categorías gramaticales) lo escribió un asistente de IA y ningún hablante nativo lo ha revisado. `revisiones.csv` es el registro de quién ha revisado qué; este informe lo resume.

**Estado: ningún elemento revisado todavía (0 de 10068).**

## Cómo revisar

1. Abre `visor-revision.html` en un navegador (no necesita servidor ni conexión), elige tu idioma y lo que vas a revisar.
2. Marca cada elemento: `correcto`, `corregir` (con la corrección propuesta en la nota) o `dudoso`. Escribe tu nombre o alias y la fecha.
3. Copia las filas que genera el visor y añádelas al final de `revisiones.csv` (en un PR o pásalas a quien mantiene el proyecto). El orden de las filas no importa; el generador las valida.
4. Quien mantiene el proyecto aplica las correcciones a los CSV y cambia esas filas de `corregir` a `corregido`.

Alcances (qué es un elemento):

- `nombres`: el nombre del color en el idioma (colores.csv).
- `significados`: los significados psicológico y místico en el idioma (colores.csv y significados-idiomas.csv).
- `lexico`: las formas del léxico básico de un concepto en el idioma (lexico-basico.csv).
- `categorias`: las categorías gramaticales de un concepto en el idioma (categorias.csv).

Veredictos:

- `correcto`: la revisión no encontró nada que cambiar.
- `corregir`: hay una corrección propuesta en `nota`, todavía sin aplicar a los CSV.
- `corregido`: la corrección propuesta ya está aplicada a los CSV.
- `dudoso`: el revisor no está seguro y deja constancia en `nota`.

Una fila por revisor y elemento: si vuelves a revisar algo, edita tu fila. El historial queda en git.

## Cobertura por idioma

Elementos con al menos una fila, sobre el total de cada alcance (`—` si el idioma no tiene datos de ese alcance).

| Idioma | `nombres` | `significados` | `lexico` | `categorias` |
|---|---|---|---|---|
| Inglés (`en`) | 0 de 148 | 0 de 148 | 0 de 38 | 0 de 61 |
| Español (`es`) | 0 de 148 | 0 de 148 | 0 de 38 | 0 de 32 |
| Francés (`fr`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Portugués (`pt`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Italiano (`it`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Alemán (`de`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Neerlandés (`nl`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Sueco (`sv`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Danés (`da`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Finés (`fi`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Polaco (`pl`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Checo (`cs`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Rumano (`ro`) | 0 de 148 | 0 de 148 | 0 de 36 | — |
| Húngaro (`hu`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Griego (`el`) | 0 de 148 | 0 de 148 | 0 de 35 | — |
| Ruso (`ru`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Ucraniano (`uk`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Turco (`tr`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Árabe (`ar`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Hebreo (`he`) | 0 de 148 | 0 de 148 | 0 de 36 | — |
| Persa (`fa`) | 0 de 148 | 0 de 148 | 0 de 36 | — |
| Hindi (`hi`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Bengalí (`bn`) | 0 de 148 | 0 de 148 | 0 de 33 | — |
| Chino simplificado (`zh`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Japonés (`ja`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Coreano (`ko`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Vietnamita (`vi`) | 0 de 148 | 0 de 148 | 0 de 37 | — |
| Tailandés (`th`) | 0 de 148 | 0 de 148 | 0 de 36 | — |
| Indonesio (`id`) | 0 de 148 | 0 de 148 | 0 de 38 | — |
| Suajili (`sw`) | 0 de 148 | 0 de 148 | 0 de 18 | — |

## Filas por veredicto

| Veredicto | Filas |
|---|---|
| `correcto` | 0 |
| `corregir` | 0 |
| `corregido` | 0 |
| `dudoso` | 0 |

## Pendientes

No hay correcciones propuestas ni dudas por atender.
