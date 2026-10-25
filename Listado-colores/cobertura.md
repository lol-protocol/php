# Cobertura y ambigüedades de los datos

Generado por `generar-tabla.py` a partir de los CSV. No editar a mano.

Este informe no corrige nada ni hace fallar la generación: dice dónde faltan datos y qué ambigüedades tienen, para no tomar los datos por más completos o más precisos de lo que son. Nada de esto lo ha revisado un hablante nativo.

## Resumen

- Léxico básico: 38 conceptos. 14 idiomas tienen lema para todos; 16 tienen huecos (45 lemas en total).
- Categorías gramaticales: 2 idiomas (Inglés, Español); 28 sin ninguna.
- Nombres CSS repetidos con hex distinto: 29 casos en 18 idiomas.
- Lemas del léxico que sirven a colores de distinto hex: 25 casos en 17 idiomas.

## Conceptos del léxico sin lema

El generador solo exige lema en los 30 idiomas para los 11 conceptos básicos; el resto puede faltar.

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
| Rumano (`ro`) | 36 de 38 | `lime`, `scarlet` |
| Húngaro (`hu`) | 37 de 38 | `lime` |
| Griego (`el`) | 35 de 38 | `crimson`, `lime`, `scarlet` |
| Ruso (`ru`) | 37 de 38 | `cyan` |
| Ucraniano (`uk`) | 37 de 38 | `cyan` |
| Turco (`tr`) | 37 de 38 | `crimson` |
| Árabe (`ar`) | 37 de 38 | `scarlet` |
| Hebreo (`he`) | 36 de 38 | `maroon`, `navy` |
| Persa (`fa`) | 36 de 38 | `magenta`, `scarlet` |
| Hindi (`hi`) | 37 de 38 | `lime` |
| Bengalí (`bn`) | 33 de 38 | `salmon`, `lime`, `burgundy`, `scarlet`, `amber` |
| Chino simplificado (`zh`) | 38 de 38 | — |
| Japonés (`ja`) | 38 de 38 | — |
| Coreano (`ko`) | 37 de 38 | `violet` |
| Vietnamita (`vi`) | 37 de 38 | `salmon` |
| Tailandés (`th`) | 36 de 38 | `coral`, `crimson` |
| Indonesio (`id`) | 38 de 38 | — |
| Suajili (`sw`) | 18 de 38 | `turquoise`, `beige`, `violet`, `indigo`, `lavender`, `maroon`, `olive`, `navy`, `cyan`, `magenta`, `salmon`, `coral`, `crimson`, `khaki`, `lime`, `lilac`, `burgundy`, `cream`, `scarlet`, `amber` |

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
