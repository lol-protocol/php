# Guía de revisión nativa

Para coordinar la revisión de las traducciones de [`../frases.md`](../frases.md). Las traducciones son un borrador sin revisión nativa; ver [`README.md`](README.md).

## Orden sugerido

1. **Con respaldo de producto:** `en`, `pt-BR`, `fr`, `de`, `it`. Son idiomas que `phone-directory/` ya soporta, así que es donde antes se van a usar.
2. **Mayor riesgo lingüístico:** `ar` y `he` (género, registro, escritura de derecha a izquierda), y luego `ja`, `ko`, `zh-Hans` y `hi`.
3. **Resto:** `tr`, `ru`, `pl` y `nl`.

Si un idioma no se va a publicar pronto, puede esperar: la revisión solo importa antes de usar el texto.

## Qué entregar a quien revisa

- Su archivo `frases.<código>.md`.
- `frases.en.md` como referencia (o `../frases.md`, si lee español).
- El [brief en inglés](#brief-para-quien-revisa-en-inglés) de abajo, tal cual.

No hace falta enviar las frases que quedaron fuera por depender del producto (condicionales y promesas sobre datos); ver el README.

## Decisiones abiertas por idioma

| Idioma | Decisión | Hoy |
|---|---|---|
| `fr` | tutoyer o vouvoyer | vous |
| `de` | du o Sie | Sie |
| `ru` | ты o вы | вы |
| `it` | tu o Lei | tu |
| `nl` | je o u | je |
| `pl` | ty o Państwo | ty |
| `tr` | sen o siz | sen |
| `zh-Hans` | 你 o 您; mandarín o cantonés | 你, mandarín |
| `ar` | árabe estándar o dialecto; género del imperativo | estándar, masculino |
| `he` | género de los verbos | plural neutro |
| `ja` | cortesía | です・ます |
| `ko` | cortesía | 해요체 |
| `pt-BR` | si también hace falta `pt-PT` | solo Brasil |

## Pendientes conocidos

- **Francés, tipografía.** Hay 11 espacios normales antes de `?`, `!`, `:` y `;`. Lo correcto es un espacio fino insécable. Que lo decida quien revise el francés.
- **Marca en alfabetos no latinos** (`ru`, `ar`, `he`, `ja`, `ko`, `zh-Hans`, `hi`). Cuando exista el nombre, decidir si se translitera o se deja en alfabeto latino; se configura por idioma en `marca_por_idioma` de `../config.json` (ver `../README.md`).
- **Concordancia con `[Nombre]` y `[Marca]`** en idiomas con casos o género (`ru`, `pl`, `tr`, `ar`, `hi`): comprobar que la frase funciona con cualquier nombre.
- **Hashtags.** Comprobar que existen y se usan en cada red.

## Al recibir la revisión

- El archivo debe conservar las mismas 114 líneas y las mismas secciones, con `[Nombre]` y `[Marca]` intactos.
- Revisar con cuidado cualquier cambio que altere el significado o añada una promesa (resultados, datos, seguridad); la nota «Antes de usar» de `../frases.md` manda.
- Aplicar los cambios, quitar el comentario HTML de borrador del archivo y anotar en el README quién lo revisó y cuándo.

## Brief para quien revisa (en inglés)

Copy and send as is.

> **Context.** These are marketing phrases for a family-history (genealogy) product, originally written in Spanish. Your file is a draft translation. Please review it as a native speaker.
>
> **What to do.** Read your file next to `frases.en.md` (the English reference) and fix anything that is wrong, unnatural or off-tone. Aim for warm, respectful, plain language: not salesy, no hype.
>
> **Please check:**
> 1. The meaning matches the reference, line by line (same sections, same order, same number of lines: 114).
> 2. It sounds natural to a native reader, not like a translation.
> 3. The register. We chose the one listed for your language in the table above; tell us if another fits this market better.
> 4. Gender and politeness. Avoid assuming the reader's gender where you can.
> 5. Wordplay. "Every name counts" (counts / matters / tells a story) and "legacy / inheritance" may not translate literally. Choose what reads best.
> 6. The punctuation and typography conventions of your language.
> 7. The hashtags: do they exist and read well?
>
> **Rules.** Keep `[Nombre]` and `[Marca]` exactly as written. Do not add or remove lines. Do not add promises about results, data, privacy or security. If a phrase already sounds like one, flag it instead of making it stronger. If something cannot be fixed, leave a comment rather than deleting it.
>
> **Please send back** the corrected file plus short notes: what you changed, anything culturally sensitive, and anything you would avoid.
