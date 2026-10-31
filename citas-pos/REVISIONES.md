# Registro de revisiones

[`revisiones.csv`](revisiones.csv) anota quién ha revisado qué. Es el mismo formato que el de [`Listado-colores/`](../Listado-colores/README.md) (sección «Revisión por hablantes nativos»), para que quien ya revisó allí sepa cómo hacerlo aquí, con las citas en lugar de los colores. **Empieza vacío: nadie ha revisado nada todavía.** Hoy el único revisor previsto es la persona que encarga el trabajo, y solo para `spa`; no habrá revisores nativos de otros idiomas por ahora.

Para revisar en español, sigue la [guía de revisión](GUIA-REVISION.md): qué se mira, las convenciones de etiquetado y cómo dejar tus filas.

Una cita no se publica en un idioma hasta que su traducción y sus etiquetas tengan una revisión `correcta` de una persona que hable ese idioma (ver `README.md`, reglas 2 y 3).

## Columnas

| Columna | Qué es |
|---|---|
| `idioma` | Código de 3 letras, de [`datos/idiomas.json`](datos/idiomas.json) (`spa`, `eng`, `deu`…). |
| `alcance` | Qué se revisa: `cita` (que el texto coincida con la fuente y la edición citadas), `traduccion` (la traducción de la cita) o `etiquetas` (la categoría de cada palabra). |
| `elemento` | El id de la cita (o de la frase de prueba del prototipo). Empieza por el código del idioma, por ejemplo `spa-1`. |
| `veredicto` | `correcto` (no hay nada que cambiar), `corregir` (hay un cambio; va en la nota), `corregido` (ya aplicado por quien mantiene el proyecto) o `dudoso` (hay una duda; va en la nota). |
| `revisor` | Nombre o alias de quien revisa. Si quieres dejar constancia de tu relación con el idioma (lengua materna, nivel), ponla aquí o en la nota. |
| `fecha` | `AAAA-MM-DD`. |
| `nota` | Obligatoria con `corregir` y `dudoso`: qué cambiar, o de qué dudas. |

## Reglas

- Idioma, alcance, elemento y veredicto tienen que existir; revisor y fecha son obligatorios.
- Una fila por revisor, idioma, alcance y elemento: si vuelves a revisar, edita tu fila (el historial queda en git).
- El orden de las filas no importa.
- Un elemento de un idioma solo se puede revisar con el código de ese idioma: `spa-1` va con `spa`.
- Quien mantiene el proyecto aplica las correcciones a los datos y cambia esas filas de `corregir` a `corregido`.

## Cómo añadir una revisión

1. Añade tus filas al final de `revisiones.csv`, en un PR o pasándoselas a quien mantiene el proyecto.
2. Comprueba el registro:

   ```bash
   php citas-pos/bin/comprobar-revisiones.php
   ```

   La CI lo corre en cada PR que toque esta carpeta.

Hoy lo único revisable son las **etiquetas de las frases de prueba** del prototipo (`spa-1`, `spa-2`, `eng-1`, `eng-2`, `deu-1`, `deu-2`, con alcance `etiquetas`). Sin revisores de otros idiomas, solo tienen quién las revise las de `spa`; las de `eng` y `deu` esperan a que aparezca alguien. Las 6 citas candidatas (`spa-c1` a `spa-c6`, en `datos/citas.json`) ya tienen id, pero **todavía no tienen texto**, así que no hay nada que revisar en ellas; con alcance `cita` se podrán revisar cuando estén verificadas.

## Qué no hace

- No comprueba quién es el revisor ni su relación con el idioma. Un `correcto` es el juicio de una persona, no una garantía.
- No hay un visor para revisar en el navegador. `Listado-colores/` tiene uno (`visor-revision.html`, generado desde `visor-plantilla.html`) que se podría adaptar si el número de revisores lo justifica.
- Con 30 idiomas, el cuello de botella es contar con quien hable cada uno (ver «Riesgos» en [`PLAN.md`](PLAN.md)). Este registro solo deja constancia de lo que ya se revisó.
