# Cobertura y ambigüedades de los datos

Generado por `generar-tabla.py` a partir de los CSV. No editar a mano.

Este informe no corrige nada ni hace fallar la generación: dice dónde faltan datos y qué ambigüedades tienen, para no tomar los datos por más completos o más precisos de lo que son. Nada de esto lo ha revisado un hablante nativo. Los nombres repetidos y los lemas compartidos de abajo son casos de la lista «Dentro de un idioma» de `ambiguedad.md`, que además mide qué tan distintos son los colores; aquí se cuentan junto a lo que falta.

## Resumen

- Léxico básico: 38 conceptos. 21 idiomas tienen lema para todos; 9 tienen huecos (21 lemas en total).
- De esos huecos, 24 los llenó una propuesta de IA (rasgo `origen=ia` en `lexico-basico.csv`).
- Categorías gramaticales: 2 idiomas (Inglés, Español); 28 sin ninguna.
- Nombres CSS repetidos con hex distinto: 29 casos en 18 idiomas.
- Lemas del léxico que sirven a colores de distinto hex: 25 casos en 17 idiomas.

## Conceptos del léxico sin lema

El generador solo exige lema en los 30 idiomas para los 11 conceptos básicos; el resto puede faltar. Los huecos que quedan son conceptos sin una palabra de uso corriente en ese idioma, o cuya única candidata repetiría el lema de otro concepto: no se inventan.

| Idioma | Con lema | Faltan |
|---|---|---|
| Inglés (`en`) | 38 de 38 | — |
| Español (`es`) | 38 de 38 | — |
| Francés (`fr`) | 38 de 38 | — |
| Portugués (`pt`) | 38 de 38 | — |
| Italiano (`it`) | 38 de 38 | — |
| Alemán (`de`) | 38 de 38 | — |
| Neerlandés (`nl`) | 38 de 38 | — |
| Sueco (`sv`) | 38 de 38 | — |
| Danés (`da`) | 37 de 38 | `lilac` |
| Finés (`fi`) | 38 de 38 | — |
| Polaco (`pl`) | 38 de 38 | — |
| Checo (`cs`) | 38 de 38 | — |
| Rumano (`ro`) | 37 de 38 | `scarlet` |
| Húngaro (`hu`) | 38 de 38 | — |
| Griego (`el`) | 36 de 38 | `crimson`, `scarlet` |
| Ruso (`ru`) | 38 de 38 | — |
| Ucraniano (`uk`) | 38 de 38 | — |
| Turco (`tr`) | 37 de 38 | `crimson` |
| Árabe (`ar`) | 37 de 38 | `scarlet` |
| Hebreo (`he`) | 36 de 38 | `maroon`, `navy` |
| Persa (`fa`) | 37 de 38 | `scarlet` |
| Hindi (`hi`) | 38 de 38 | — |
| Bengalí (`bn`) | 38 de 38 | — |
| Chino simplificado (`zh`) | 38 de 38 | — |
| Japonés (`ja`) | 38 de 38 | — |
| Coreano (`ko`) | 38 de 38 | — |
| Vietnamita (`vi`) | 38 de 38 | — |
| Tailandés (`th`) | 36 de 38 | `coral`, `crimson` |
| Indonesio (`id`) | 38 de 38 | — |
| Suajili (`sw`) | 28 de 38 | `maroon`, `navy`, `cyan`, `salmon`, `crimson`, `lime`, `lilac`, `burgundy`, `cream`, `scarlet` |

## Lemas propuestos por IA

Llenan huecos del léxico; los escribió un asistente de IA (muchos son préstamos o transcripciones de la palabra internacional) y no se prevé que un hablante nativo los revise: el rasgo `origen=ia` deja constancia de su procedencia. Si alguien los revisa (en `revisiones.csv`), se quita el rasgo; si no, se corrige o se borra la fila.

| Idioma | Concepto | Lema |
|---|---|---|
| Rumano (`ro`) | `lime` | lime |
| Húngaro (`hu`) | `lime` | lime |
| Griego (`el`) | `lime` | λαχανί |
| Ruso (`ru`) | `cyan` | циан |
| Ucraniano (`uk`) | `cyan` | ціан |
| Persa (`fa`) | `magenta` | ماژنتا |
| Hindi (`hi`) | `lime` | लाइम |
| Bengalí (`bn`) | `salmon` | স্যামন |
| Bengalí (`bn`) | `lime` | লাইম |
| Bengalí (`bn`) | `burgundy` | বার্গান্ডি |
| Bengalí (`bn`) | `scarlet` | স্কারলেট |
| Bengalí (`bn`) | `amber` | অ্যাম্বার |
| Coreano (`ko`) | `violet` | 바이올렛 |
| Vietnamita (`vi`) | `salmon` | cá hồi |
| Suajili (`sw`) | `turquoise` | feruzi |
| Suajili (`sw`) | `beige` | beige |
| Suajili (`sw`) | `violet` | urujuani |
| Suajili (`sw`) | `indigo` | nili |
| Suajili (`sw`) | `lavender` | lavenda |
| Suajili (`sw`) | `olive` | zeituni |
| Suajili (`sw`) | `magenta` | magenta |
| Suajili (`sw`) | `coral` | marijani |
| Suajili (`sw`) | `khaki` | kaki |
| Suajili (`sw`) | `amber` | kaharabu |

## Idiomas sin categorías gramaticales

`fr`, `pt`, `it`, `de`, `nl`, `sv`, `da`, `fi`, `pl`, `cs`, `ro`, `hu`, `el`, `ru`, `uk`, `tr`, `ar`, `he`, `fa`, `hi`, `bn`, `zh`, `ja`, `ko`, `vi`, `th`, `id`, `sw`

## Nombres CSS repetidos con hex distinto

Un mismo nombre para dos colores que no son el mismo: quien busque por nombre no sabe cuál es.

| Idioma | Nombre | Colores CSS |
|---|---|---|
| Portugués (`pt`) | azul-celeste | `azure`, `skyblue` |
| Alemán (`de`) | Dunkelgrau | `darkgray`, `darkgrey`, `dimgray`, `dimgrey` |
| Finés (`fi`) | taivaansininen | `azure`, `skyblue` |
| Finés (`fi`) | vaaleanpunainen | `lightpink`, `pink` |
| Polaco (`pl`) | ciemnofioletowy | `darkorchid`, `darkviolet` |
| Checo (`cs`) | purpurová | `magenta`, `purple` |
| Rumano (`ro`) | verde-galben | `greenyellow`, `yellowgreen` |
| Ruso (`ru`) | тёмно-синий | `darkblue`, `navy` |
| Ucraniano (`uk`) | темно-синій | `darkblue`, `navy` |
| Turco (`tr`) | gök mavisi | `azure`, `skyblue` |
| Turco (`tr`) | kırmızı | `crimson`, `red` |
| Turco (`tr`) | turkuaz | `teal`, `turquoise` |
| Árabe (`ar`) | أخضر مصفر | `greenyellow`, `yellowgreen` |
| Hebreo (`he`) | סגול | `purple`, `violet` |
| Persa (`fa`) | بنفش | `purple`, `violet` |
| Persa (`fa`) | فیروزه‌ای | `cyan`, `turquoise` |
| Persa (`fa`) | فیروزه‌ای تیره | `darkcyan`, `darkturquoise` |
| Persa (`fa`) | قرمز | `crimson`, `red` |
| Hindi (`hi`) | नींबू हरा | `lime`, `limegreen` |
| Hindi (`hi`) | हल्का हरा | `lightgreen`, `palegreen` |
| Bengalí (`bn`) | বেগুনি | `purple`, `violet` |
| Vietnamita (`vi`) | vàng xanh | `greenyellow`, `yellowgreen` |
| Vietnamita (`vi`) | xanh da trời | `azure`, `skyblue` |
| Tailandés (`th`) | ฟ้าอ่อน | `lightblue`, `lightskyblue` |
| Tailandés (`th`) | แดงเข้ม | `crimson`, `darkred` |
| Indonesio (`id`) | cokelat | `brown`, `chocolate` |
| Suajili (`sw`) | bluu ya anga | `azure`, `skyblue` |
| Suajili (`sw`) | kijivu hafifu | `dimgray`, `dimgrey`, `lightgray`, `lightgrey` |
| Suajili (`sw`) | zambarau | `purple`, `violet` |

## Lemas que sirven a colores de distinto hex

Un lema del léxico asignado a dos conceptos que no son alias (por ejemplo, `violet` en francés es `purple` y `violet`); puede ser un idioma que no distingue los dos colores.

| Idioma | Lema | Conceptos |
|---|---|---|
| Francés (`fr`) | bordeaux | `maroon`, `burgundy` |
| Francés (`fr`) | violet | `purple`, `violet` |
| Finés (`fi`) | violetti | `purple`, `violet` |
| Polaco (`pl`) | bordowy | `maroon`, `burgundy` |
| Checo (`cs`) | fialový | `purple`, `violet` |
| Griego (`el`) | γαλάζιος | `lightblue`, `skyblue` |
| Griego (`el`) | μπορντό | `maroon`, `burgundy` |
| Ruso (`ru`) | бордовый | `maroon`, `burgundy` |
| Ruso (`ru`) | тёмно-синий | `darkblue`, `navy` |
| Ruso (`ru`) | фиолетовый | `purple`, `violet` |
| Ucraniano (`uk`) | бордовий | `maroon`, `burgundy` |
| Ucraniano (`uk`) | темно-синій | `darkblue`, `navy` |
| Ucraniano (`uk`) | фіолетовий | `purple`, `violet` |
| Turco (`tr`) | bordo | `maroon`, `burgundy` |
| Árabe (`ar`) | بنفسجي | `purple`, `violet` |
| Hebreo (`he`) | סגול | `purple`, `violet` |
| Hebreo (`he`) | תכלת | `lightblue`, `skyblue` |
| Persa (`fa`) | بنفش | `purple`, `violet` |
| Hindi (`hi`) | बैंगनी | `purple`, `violet` |
| Bengalí (`bn`) | বেগুনি | `purple`, `violet` |
| Coreano (`ko`) | 하늘색 | `lightblue`, `skyblue` |
| Vietnamita (`vi`) | tím | `purple`, `violet` |
| Tailandés (`th`) | ฟ้า | `lightblue`, `skyblue` |
| Tailandés (`th`) | ม่วง | `purple`, `violet` |
| Suajili (`sw`) | samawati | `lightblue`, `skyblue` |
