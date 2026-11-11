# Verificación de las filas de EE. UU. contra el texto de sus fuentes — 2026-11-06

Primera pasada con el workflow `laws-textos-oficiales.yml` (corrida 42253336713, módulo `privacy-laws`, carpeta
`us`): un runner de GitHub Actions descargó el texto de la página de referencia de cada una de las 27 filas
de EE. UU. y lo publicó en la rama de datos `datos/leyes-textos-privacy-laws` (se lee con
`git fetch origin datos/leyes-textos-privacy-laws`; esa rama no se fusiona).

**Resultado: no se cambió ningún dato.** Donde el texto habla de un campo, coincide con la fila; no hay ninguna
contradicción. Tampoco se subió ningún `confidence` ni se marcó nada `verified`: muchas de las páginas son guías
de despachos o blogs, no el texto oficial (regla de `CONTRIBUTING.md`: `verified` solo tras leer el texto oficial).

## 1. El texto confirma el campo (18 fuentes descargadas)

| Fila | Campo comprobado | Texto | Tipo de página |
|---|---|---|---|
| Alabama | sanción: hasta 15 000 USD por infracción | 15 000 USD | guía (clym.io) |
| Indiana | sanción 7 500 USD; vigencia 2026-01-01 | igual | mapa de leyes (privacylawmap.com) |
| Maryland (MODPA) | 10 000 USD y 25 000 USD por infracción repetida | igual | guía de la Fiscalía de Maryland |
| Nebraska | 7 500 USD; vigencia 2025-01-01 | igual | despacho (whitecase.com) |
| Nueva Jersey | 10 000 USD por (primera) infracción | igual (no cita los 20 000 de las siguientes; la fila los trae) | blog de despacho (galalaw.com) |
| Oregón | 7 500 USD; vigencia 2024-07-01 | igual | guía del Departamento de Justicia de Oregón |
| Rhode Island | 10 000 USD; vigencia 2026-01-01 | igual | guía (datagrail.io) |
| Texas | 7 500 USD | igual | blog (privacyworld.blog) |
| Delaware | vigencia 2025-01-01; hasta 10 000 USD por infracción | igual (la fila ya lo dice como «reportado») | despacho (bakerdonelson.com) |
| Luisiana | vigencia 2027-01-01; 5 000 USD por infracción (LUTPA, con intención de defraudar) | igual | mapa de leyes (privacylawmap.com), no el texto de la ley |
| Iowa | vigencia 2025-01-01 | igual | texto de la ley (legis.iowa.gov) |
| Minnesota | vigencia 2025-07-31 | igual | estatuto (revisor.mn.gov) |
| My Health My Data (WA) | 7 500 USD (RCW 19.86.140) | igual | despacho (bakerlaw.com) |

Connecticut: el texto habla de «July 1, 2026», que es la fecha de las **enmiendas** (Public Act 25-113); la fila
conserva 2023-07-01 como vigencia de las obligaciones principales, y su nombre ya cita la enmienda. No es una
contradicción.

## 2. La página se descargó pero no dice el campo (no se puede comprobar con ella)

Florida, Nuevo Hampshire, Vermont y Virginia (páginas del estatuto o del proyecto que no incluyen la sanción ni la
vigencia en el texto extraído), y la sanción de Connecticut, Iowa y Minnesota. Para esas filas hay que abrir la
sección concreta del estatuto, que la `website_url` de hoy no apunta.

## 3. No se pudo descargar (9 fuentes)

| Fila | Motivo |
|---|---|
| COPPA (`www.ftc.gov`) | HTTP 403: el sitio rechaza al runner |
| CCPA/CPRA (`oag.ca.gov`) | tiempo de espera agotado |
| BIPA (`ilga.gov`) | error de certificado SSL |
| Montana (`dojmt.gov`) | HTTP 403 |
| Tennessee (`publications.tnsosfiles.com`) | HTTP 403 |
| Colorado (`dataguidance.com`), Oklahoma (`secureprivacy.ai`) | la página necesita JavaScript |
| Kentucky (`apps.legislature.ky.gov`), Utah (`le.utah.gov`) | PDF: `cache-texts.php` no los convierte |

Las dos sanciones que más se desactualizan con los años, la de COPPA (la fila trae 43 280 USD) y la de la CCPA, **no
pudieron comprobarse**: sus fuentes oficiales son justo las que rechazan al runner. Requieren una persona con acceso
a esas páginas.

## Qué sirve para la próxima pasada

- Sitios oficiales que rechazan al runner (`ftc.gov`, `oag.ca.gov`, `dojmt.gov`, `tnsosfiles.com`, `ilga.gov`): si se
  repite en otras corridas, candidatos a `leyes-comunes/hosts-que-rechazan-runners.txt`.
- PDF y páginas con JavaScript: lo cubriría una mejora de `cache-texts.php` (conversión de PDF, o un navegador).
