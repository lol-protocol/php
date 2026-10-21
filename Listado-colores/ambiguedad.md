# Ambigüedad de las formas de color

Generado por `generar-tabla.py`. No editar a mano.

Una forma es *ambigua* si en el gazetteer (`exportes/gazetteer.tsv`) remite a más de un color, es decir, a más de un hex. Los alias CSS (`gray` y `grey`, `aqua` y `cyan`) comparten hex y no cuentan. Sirve para saber qué formas no bastan por sí solas para decidir de qué color se habla, y qué traducciones usan la misma palabra para colores distintos.

Para cada caso se da la distancia máxima CIEDE2000 (ΔE) entre sus colores, con su banda: *casi iguales* (hasta 3), *parecidos* (hasta 8) y *distintos* (más de 8). Son los umbrales de cercanía del proyecto, no un estándar.

Límites: solo cubre las formas del gazetteer (los colores CSS en 30 idiomas y el léxico básico). No detecta palabras que, además de un color, son otra cosa (una flor, una fruta), ni palabras fuera del listado. Parte de las ambigüedades puede venir de la traducción, que es una propuesta sin revisión de hablantes nativos: una forma que remite a colores muy distintos en un idioma es una candidata a revisar. Las listas 3 y 4 usan la forma normalizada (ver el README).

## Resumen

| Lista | Casos | casi iguales | parecidos | distintos |
|---|---|---|---|---|
| 1. Dentro de un idioma | 132 | 11 | 33 | 88 |
| 2. Entre idiomas | 15 | 0 | 3 | 12 |
| 3. Dentro de un idioma, solo al normalizar | 0 | 0 | 0 | 0 |
| 4. Entre idiomas, solo al normalizar | 2 | 0 | 0 | 2 |

Casos de la lista 1 por idioma: `en` 1, `es` 3, `fr` 6, `pt` 3, `it` 4, `de` 3, `nl` 1, `sv` 1, `da` 3, `fi` 7, `pl` 7, `cs` 5, `ro` 5, `hu` 1, `el` 7, `ru` 12, `uk` 10, `tr` 5, `ar` 6, `he` 6, `fa` 5, `hi` 4, `bn` 1, `zh` 1, `ja` 2, `ko` 6, `vi` 5, `th` 6, `id` 2, `sw` 4.

## 1. Dentro de un idioma: una forma, varios colores

| Idioma | Forma | Colores | ΔE máx. | Banda |
|---|---|---|---|---|
| `en` | «lime green» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `es` | «celeste» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `es` | «verde lima» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `es` | «verde oliva» | `olivedrab` `#6B8E23` · `olive` `#808000` | 9.8 | distintos |
| `fr` | «azur» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `fr` | «bordeaux» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `fr` | «violet» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `fr` | «violets» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `fr` | «violette» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `fr` | «violettes» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `pt` | «azul-celeste» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `pt` | «bordô» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `pt` | «verde-oliva» | `olivedrab` `#6B8E23` · `olive` `#808000` | 9.8 | distintos |
| `it` | «azzurro» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` · `azure` `#F0FFFF` | 18.6 | distintos |
| `it` | «bordeaux» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `it` | «verde lime» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `it` | «verde oliva» | `olivedrab` `#6B8E23` · `olive` `#808000` | 9.8 | distintos |
| `de` | «Dunkelgrau» | `dimgray`, `dimgrey` `#696969` · `darkgray`, `darkgrey` `#A9A9A9` | 22.9 | distintos |
| `de` | «Limettengrün» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `de` | «Violett» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `nl` | «limoengroen» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `sv` | «limegrön» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `da` | «limegrøn» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `da` | «lyserød» | `lightpink` `#FFB6C1` · `pink` `#FFC0CB` | 2.7 | casi iguales |
| `da` | «violet» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `fi` | «limetinvihreä» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `fi` | «taivaansininen» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `fi` | «tulipunainen» | `firebrick` `#B22222` · `scarlet` `#FF2400` | 17.6 | distintos |
| `fi` | «tummansininen» | `navy` `#000080` · `darkblue` `#00008B` | 1.6 | casi iguales |
| `fi` | «vaaleanpunainen» | `lightpink` `#FFB6C1` · `pink` `#FFC0CB` | 2.7 | casi iguales |
| `fi` | «violetit» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `fi` | «violetti» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `pl` | «bordowa» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `pl` | «bordowe» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `pl` | «bordowi» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `pl` | «bordowy» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `pl` | «błękitny» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `pl` | «ciemnofioletowy» | `darkviolet` `#9400D3` · `darkorchid` `#9932CC` | 4.4 | parecidos |
| `pl` | «fioletowy» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `cs` | «azurová» | `cyan` `#00FFFF` · `azure` `#F0FFFF` | 21.3 | distintos |
| `cs` | «fialová» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `cs` | «fialové» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `cs` | «fialoví» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `cs` | «fialový» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ro` | «azuriu» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `ro` | «bleu» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `ro` | «roșie» | `red` `#FF0000` · `tomato` `#FF6347` | 10.2 | distintos |
| `ro` | «verde-galben» | `yellowgreen` `#9ACD32` · `greenyellow` `#ADFF2F` | 11.2 | distintos |
| `ro` | «violet» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `hu` | «bordó» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `el` | «γαλάζια» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `el` | «γαλάζιες» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `el` | «γαλάζιο» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` · `azure` `#F0FFFF` | 18.6 | distintos |
| `el` | «γαλάζιοι» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `el` | «γαλάζιος» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `el` | «μπορντό» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `el` | «σκούρο μπλε» | `navy` `#000080` · `darkblue` `#00008B` | 1.6 | casi iguales |
| `ru` | «бордовая» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `ru` | «бордовое» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `ru` | «бордовые» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `ru` | «бордовый» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `ru` | «голубой» | `cyan` `#00FFFF` · `lightblue` `#ADD8E6` | 17.4 | distintos |
| `ru` | «лазурный» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `ru` | «темно-синий» | `navy` `#000080` · `darkblue` `#00008B` | 1.6 | casi iguales |
| `ru` | «тёмно-синий» | `navy` `#000080` · `darkblue` `#00008B` | 1.6 | casi iguales |
| `ru` | «фиолетовая» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ru` | «фиолетовое» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ru` | «фиолетовые» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ru` | «фиолетовый» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `uk` | «блакитний» | `cyan` `#00FFFF` · `lightblue` `#ADD8E6` | 17.4 | distintos |
| `uk` | «бордова» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `uk` | «бордове» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `uk` | «бордовий» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `uk` | «бордові» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `uk` | «темно-синій» | `navy` `#000080` · `darkblue` `#00008B` | 1.6 | casi iguales |
| `uk` | «фіолетова» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `uk` | «фіолетове» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `uk` | «фіолетовий» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `uk` | «фіолетові» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `tr` | «al» | `red` `#FF0000` · `scarlet` `#FF2400` | 2.0 | casi iguales |
| `tr` | «bordo» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `tr` | «gök mavisi» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `tr` | «kırmızı» | `crimson` `#DC143C` · `red` `#FF0000` | 13.8 | distintos |
| `tr` | «turkuaz» | `teal` `#008080` · `turquoise` `#40E0D0` | 28.1 | distintos |
| `ar` | «أخضر مصفر» | `yellowgreen` `#9ACD32` · `greenyellow` `#ADFF2F` | 11.2 | distintos |
| `ar` | «أرجواني» | `purple` `#800080` · `magenta` `#FF00FF` | 30.3 | distintos |
| `ar` | «بنفسجي» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ar` | «بنفسجية» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ar` | «سماوي» | `cyan` `#00FFFF` · `skyblue` `#87CEEB` | 18.1 | distintos |
| `ar` | «عنابي» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `he` | «בורדו» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `he` | «סגול» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `he` | «סגולה» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `he` | «סגולות» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `he` | «סגולים» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `he` | «תכלת» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` · `azure` `#F0FFFF` | 18.6 | distintos |
| `fa` | «بنفش» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `fa` | «شرابی» | `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| `fa` | «فیروزه‌ای» | `cyan` `#00FFFF` · `turquoise` `#40E0D0` | 8.6 | distintos |
| `fa` | «فیروزه‌ای تیره» | `darkcyan` `#008B8B` · `darkturquoise` `#00CED1` | 19.7 | distintos |
| `fa` | «قرمز» | `crimson` `#DC143C` · `red` `#FF0000` | 13.8 | distintos |
| `hi` | «नींबू हरा» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `hi` | «बैंगनी» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `hi` | «भूरा» | `gray`, `grey` `#808080` · `brown` `#A52A2A` | 30.0 | distintos |
| `hi` | «हल्का हरा» | `lightgreen` `#90EE90` · `palegreen` `#98FB98` | 2.8 | casi iguales |
| `bn` | «বেগুনি» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `zh` | «深红色» | `darkred` `#8B0000` · `crimson` `#DC143C` | 18.8 | distintos |
| `ja` | «ライムグリーン» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `ja` | «紺色» | `navy` `#000080` · `darkblue` `#00008B` | 1.6 | casi iguales |
| `ko` | «남색» | `darkblue` `#00008B` · `indigo` `#4B0082` | 7.7 | parecidos |
| `ko` | «보라색» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `ko` | «연두» | `lightgreen` `#90EE90` · `yellowgreen` `#9ACD32` | 13.7 | distintos |
| `ko` | «연두색» | `lightgreen` `#90EE90` · `greenyellow` `#ADFF2F` | 13.3 | distintos |
| `ko` | «연한 초록» | `lightgreen` `#90EE90` · `palegreen` `#98FB98` | 2.8 | casi iguales |
| `ko` | «하늘색» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `vi` | «tím» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `vi` | «vàng xanh» | `yellowgreen` `#9ACD32` · `greenyellow` `#ADFF2F` | 11.2 | distintos |
| `vi` | «xanh da trời» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `vi` | «xanh nhạt» | `lightgreen` `#90EE90` · `palegreen` `#98FB98` · `lightblue` `#ADD8E6` | 32.2 | distintos |
| `vi` | «đỏ tươi» | `magenta` `#FF00FF` · `scarlet` `#FF2400` | 43.4 | distintos |
| `th` | «ฟ้า» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `th` | «ฟ้าอ่อน» | `lightskyblue` `#87CEFA` · `lightblue` `#ADD8E6` | 9.3 | distintos |
| `th` | «ม่วง» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| `th` | «สีฟ้า» | `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 6.4 | parecidos |
| `th` | «เขียวมะนาว» | `lime` `#00FF00` · `limegreen` `#32CD32` | 11.6 | distintos |
| `th` | «แดงเข้ม» | `darkred` `#8B0000` · `crimson` `#DC143C` | 18.8 | distintos |
| `id` | «cokelat» | `brown` `#A52A2A` · `chocolate` `#D2691E` | 24.7 | distintos |
| `id` | «merah tua» | `darkred` `#8B0000` · `crimson` `#DC143C` | 18.8 | distintos |
| `sw` | «bluu ya anga» | `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| `sw` | «kijivu hafifu» | `dimgray`, `dimgrey` `#696969` · `lightgray`, `lightgrey` `#D3D3D3` | 33.2 | distintos |
| `sw` | «samawati» | `blue` `#0000FF` · `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 50.1 | distintos |
| `sw` | «zambarau» | `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |

## 2. Entre idiomas: la misma forma, otro color

| Forma | Colores por idioma | ΔE máx. | Banda |
|---|---|---|---|
| «azur» | `es`: `azure` `#F0FFFF`; `fr`: `skyblue` `#87CEEB` · `azure` `#F0FFFF` | 18.6 | distintos |
| «bleu» | `fr`: `blue` `#0000FF`; `ro`: `skyblue` `#87CEEB` · `lightblue` `#ADD8E6` | 50.1 | distintos |
| «bordeaux» | `fr`: `maroon` `#800000` · `burgundy` `#900020`; `it`: `maroon` `#800000` · `burgundy` `#900020`; `da`: `burgundy` `#900020` | 7.9 | parecidos |
| «bordo» | `ro`: `burgundy` `#900020`; `tr`: `maroon` `#800000` · `burgundy` `#900020` | 7.9 | parecidos |
| «celeste» | `es`: `skyblue` `#87CEEB` · `lightblue` `#ADD8E6`; `pt`: `skyblue` `#87CEEB`; `it`: `skyblue` `#87CEEB` | 6.4 | parecidos |
| «lila» | `es`: `lilac` `#CEA2FD`; `de`: `purple` `#800080`; `nl`: `lilac` `#CEA2FD`; `sv`: `purple` `#800080`; `fi`: `lilac` `#CEA2FD`; `ro`: `lilac` `#CEA2FD`; `hu`: `purple` `#800080`; `tr`: `lilac` `#CEA2FD` | 44.7 | distintos |
| «lilla» | `it`: `lilac` `#CEA2FD`; `da`: `purple` `#800080` | 44.7 | distintos |
| «verde lime» | `it`: `lime` `#00FF00` · `limegreen` `#32CD32`; `ro`: `limegreen` `#32CD32` | 11.6 | distintos |
| «violet» | `en`: `violet` `#EE82EE`; `fr`: `purple` `#800080` · `violet` `#EE82EE`; `nl`: `violet` `#EE82EE`; `da`: `purple` `#800080` · `violet` `#EE82EE`; `ro`: `purple` `#800080` · `violet` `#EE82EE`; `id`: `violet` `#EE82EE` | 39.9 | distintos |
| «Violett» | `de`: `purple` `#800080` · `violet` `#EE82EE`; `sv`: `violet` `#EE82EE` | 39.9 | distintos |
| «violette» | `fr`: `purple` `#800080` · `violet` `#EE82EE`; `it`: `violet` `#EE82EE`; `de`: `violet` `#EE82EE` | 39.9 | distintos |
| «violettes» | `fr`: `purple` `#800080` · `violet` `#EE82EE`; `de`: `violet` `#EE82EE` | 39.9 | distintos |
| «violetti» | `it`: `violet` `#EE82EE`; `fi`: `purple` `#800080` · `violet` `#EE82EE` | 39.9 | distintos |
| «桃色» | `zh`: `peachpuff` `#FFDAB9`; `ja`: `pink` `#FFC0CB` | 20.2 | distintos |
| «青色» | `zh`: `cyan` `#00FFFF`; `ja`: `blue` `#0000FF` | 66.5 | distintos |

## 3. Dentro de un idioma, solo al normalizar

No hay casos.

## 4. Entre idiomas, solo al normalizar

| Normalizada | Formas y colores por idioma | ΔE máx. | Banda |
|---|---|---|---|
| `grau` | `de` «Grau», «grau»: `gray`, `grey` `#808080`; `ro` «grâu»: `wheat` `#F5DEB3` | 31.4 | distintos |
| `verde lima` | `es` «verde lima»: `lime` `#00FF00` · `limegreen` `#32CD32`; `pt` «verde-lima»: `limegreen` `#32CD32` | 11.6 | distintos |
