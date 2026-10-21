# Auditoría de los nombres completos

Generado por `generar-tabla.py` a partir de `nombres-completos.csv`. No editar a mano.

Revisa automáticamente los 31.935 nombres de la lista completa (4.966 curados, 15,6 %): caracteres raros, forma Unicode, mayúsculas, frases, duplicados que solo se distinguen por un acento o un guion, y hex repetidos. **Marca casos sospechosos; no corrige nada ni decide cuáles son errores**: un hallazgo es solo un sitio donde mirar, y el generador sigue aceptando el archivo.

Lo que no hace: no compara los nombres con otra fuente (el README dice que no se verificaron uno a uno) y no distingue el idioma de cada nombre. La lista no es solo inglés: hay nombres en pinyin con tonos, en hindi transliterado, en español o en francés, como `Bái Sè White` o `Gulābī Pink`, y un etiquetador debe esperarlos.

## Comprobaciones

Las 8 primeras buscan defectos (lo normal es que no haya ninguno); las demás describen la lista. «Curados» son los que llevan una `x` en `curada`.

| Comprobación | Nombres | Curados | Ejemplos |
|---|---|---|---|
| caracteres de control | 0 | 0 |  |
| caracteres invisibles o de formato | 0 | 0 |  |
| espacios que no son el espacio normal | 0 | 0 |  |
| espacios dobles o en los extremos | 0 | 0 |  |
| forma Unicode distinta de NFC | 0 | 0 |  |
| posible mojibake (UTF-8 leído como otra codificación) | 0 | 0 |  |
| empiezan por minúscula | 0 | 0 |  |
| apóstrofo ASCII (U+0027), distinto del tipográfico de la lista | 0 | 0 |  |
| **Descripción de la lista** | | | |
| todo en mayúsculas | 8 | 1 | «BBQ», «C-3PO», «C64 NTSC», «CO₂», «G.I.» |
| empiezan por un dígito | 15 | 8 | «100 Mph», «18th Century Green», «1975 Earth Red», «1989 Miami Hotline», «20000 Leagues Under the Sea» |
| cuatro palabras o más | 364 | 109 | «20000 Leagues Under the Sea», «A Brand New Day», «A Certain Shade of Green», «A Dime a Dozen», «A Frond in Need» |
| con letras que no son ASCII (acentos, macrones…) | 295 | 49 | «5-Masted Preußen», «Adana Kebabı», «Agua Fría», «Akakō Red», «Amélie’s Tutu» |
| con apóstrofo tipográfico (U+2019) | 441 | 97 | «A Mann’s Mint», «Actor’s Star», «Akuma’s Fury», «Alexandria’s Lighthouse», «All’s Ace» |
| con guion (U+002D) | 220 | 43 | «5-Masted Preußen», «A-List Blue», «After-Party Pink», «Air-Kiss», «Anti-Flash White» |

## Duplicados al normalizar

Nombres distintos que son el mismo al pasarlos por la forma normalizada (sin mayúsculas, tildes ni diferencias de guion; se normaliza todo como inglés). Pueden ser el mismo nombre escrito de dos maneras o dos palabras distintas.

| Nombres |
|---|
| «Rose», «Rosé» |
| «Sable», «Sablé» |

## Hex repetidos

Hex distintos: 31.935 para 31.935 nombres.

No hay casos.

## Signos que no son letras

Todo carácter que no es una letra ni un espacio, con cuántos nombres lo llevan y un ejemplo. Los dígitos van juntos.

| Carácter | Nombre Unicode | Nombres | Ejemplo |
|---|---|---|---|
| `’` U+2019 | right single quotation mark | 441 | «A Mann’s Mint» |
| `-` U+002D | hyphen-minus | 220 | «5-Masted Preußen» |
| 0-9 | dígitos del 0 al 9 | 42 | «100 Mph» |
| `&` U+0026 | ampersand | 25 | «Arts & Crafts Gold» |
| `.` U+002E | full stop | 21 | «D. Darx Blue» |
| `!` U+0021 | exclamation mark | 19 | «Ahoy! Blue» |
| `?` U+003F | question mark | 3 | «Am I Blue?» |
| `₂` U+2082 | subscript two | 3 | «CO₂» |
| `…` U+2026 | horizontal ellipsis | 2 | «If a Tree Fell …» |
| `№` U+2116 | numero sign | 2 | «Potion № 9» |
| `$` U+0024 | dollar sign | 1 | «No$GMB Yellow» |
| `%` U+0025 | percent sign | 1 | «90% Cocoa» |
| `²` U+00B2 | superscript two | 1 | «Pi²» |
| `Ⅱ` U+2161 | roman numeral two | 1 | «Green Elisabeth Ⅱ» |

## Los más largos

«Supercalifragilisticexpialidocious», «Alright Then I Became a Princess», «Extraordinary Abundance of Tinge», «Thunder Mountain Longhorn Pepper», «University of Tennessee Orange».
