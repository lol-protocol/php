# Signos del zodiaco y horóscopo chino

Los 12 signos del zodiaco occidental y los 12 animales del horóscopo chino, en 30 idiomas. Cada uno trae su nombre, una descripción breve y otra más larga de sus supuestas características, y sus rasgos (elemento y modalidad en el occidental; rama, elemento, polaridad y años en el chino). Además, cada uno tiene una ilustración SVG: la figura en trazo fino con las estrellas encima.

> **Aviso.** Las descripciones recogen lo que la astrología y el horóscopo chino *atribuyen* a cada signo. No hay evidencia científica de que la fecha de nacimiento influya en el carácter. Las constelaciones occidentales son figuras de la tradición, no grupos de estrellas físicamente relacionados, y los animales chinos **no tienen constelaciones**: sus figuras estelares son un dibujo hecho para este proyecto.

> **Estado de las traducciones.** Las escribió una IA y **ninguna persona nativa las ha revisado**. Antes de mostrarlas a usuarios hay que revisarlas, sobre todo en los idiomas de menos recursos (suajili, bengalí, urdu, persa, tailandés, hebreo, árabe) y los nombres de los signos en suajili.

## Contenido

| Archivo | Qué es |
|---|---|
| `datos/signos.json` | La fuente de verdad: nombres, descripciones, rasgos, fechas, términos traducidos y la figura de cada signo. |
| `generar.py` | Valida el JSON y genera lo demás. Solo usa la biblioteca estándar de Python 3. |
| `pruebas.py` | Pruebas de coherencia de los datos y de lo generado (`unittest`). |
| `constelaciones/*.svg` | Una ilustración por signo (24). Generadas, no editar a mano. |
| `por-idioma/*.md` | Una ficha por idioma (30) y un índice. Generadas, no editar a mano. |

### Idiomas

Español, inglés, portugués, francés, italiano, alemán, neerlandés, sueco, polaco, ruso, ucraniano, turco, griego, árabe, hebreo, hindi, chino (simplificado), japonés, coreano, vietnamita, tailandés, indonesio, persa, urdu, bengalí, rumano, checo, finés, suajili y danés. Los códigos son ISO 639-1 y están en `IDIOMAS` de `generar.py`.

### Zodiaco occidental

| Signo | Fechas aproximadas | Elemento | Modalidad |
|---|---|---|---|
| Aries | 21/03 – 19/04 | Fuego | Cardinal |
| Tauro | 20/04 – 20/05 | Tierra | Fijo |
| Géminis | 21/05 – 20/06 | Aire | Mutable |
| Cáncer | 21/06 – 22/07 | Agua | Cardinal |
| Leo | 23/07 – 22/08 | Fuego | Fijo |
| Virgo | 23/08 – 22/09 | Tierra | Mutable |
| Libra | 23/09 – 22/10 | Aire | Cardinal |
| Escorpio | 23/10 – 21/11 | Agua | Fijo |
| Sagitario | 22/11 – 21/12 | Fuego | Mutable |
| Capricornio | 22/12 – 19/01 | Tierra | Cardinal |
| Acuario | 20/01 – 18/02 | Aire | Fijo |
| Piscis | 19/02 – 20/03 | Agua | Mutable |

Las fechas cambian un día según el año y la hora de nacimiento.

### Horóscopo chino

Doce animales que se repiten en ciclo. El año empieza en el Año Nuevo lunar, que cae entre finales de enero y mediados de febrero, así que quien nació en enero o principios de febrero puede tener el signo del año anterior.

| Animal | Rama | Elemento | Polaridad | Años (1996 – 2043) |
|---|---|---|---|---|
| Rata | Zi | Agua | Yang | 1996, 2008, 2020, 2032 |
| Buey | Chou | Tierra | Yin | 1997, 2009, 2021, 2033 |
| Tigre | Yin | Madera | Yang | 1998, 2010, 2022, 2034 |
| Conejo | Mao | Madera | Yin | 1999, 2011, 2023, 2035 |
| Dragón | Chen | Tierra | Yang | 2000, 2012, 2024, 2036 |
| Serpiente | Si | Fuego | Yin | 2001, 2013, 2025, 2037 |
| Caballo | Wu | Fuego | Yang | 2002, 2014, 2026, 2038 |
| Cabra | Wei | Tierra | Yin | 2003, 2015, 2027, 2039 |
| Mono | Shen | Metal | Yang | 2004, 2016, 2028, 2040 |
| Gallo | You | Metal | Yin | 2005, 2017, 2029, 2041 |
| Perro | Xu | Tierra | Yang | 2006, 2018, 2030, 2042 |
| Cerdo | Hai | Agua | Yin | 2007, 2019, 2031, 2043 |

El elemento es el fijo de cada rama terrestre. No es el elemento del año (el ciclo de 60 años, que además depende del tronco celeste) y esa combinación no está en los datos.

Algunos idiomas usan otro animal para el mismo lugar del ciclo: en vietnamita el conejo es *Mèo* (gato) y el buey es *Trâu* (búfalo), y en japonés y coreano 羊 es la oveja (*ひつじ*, *양*) donde el español dice cabra. En finés el buey y Tauro comparten la palabra *Härkä*, y en suajili Capricornio y la cabra comparten *Mbuzi*. La tabla respeta cada uso.

## Esquema de `datos/signos.json`

```jsonc
{
  "terminos": { "fuego": { "es": "Fuego", "en": "Fire", … }, … },   // elementos, modalidades y yin/yang × 30 idiomas
  "signos": [
    {
      "id": "aries",                  // único; es el nombre del SVG
      "tipo": "occidental",           // o "chino"
      "fechas": "21/03 – 19/04",      // solo occidental
      "elemento": "fuego",            // clave de "terminos"
      "modalidad": "cardinal",        // solo occidental
      // solo chino: "polaridad": "yang", "rama": "Zi", "anios": [1996, 2008, 2020, 2032]
      "nombres":              { "es": "Aries", … },     // 30 idiomas
      "descripciones":        { "es": "Valiente, …", … },
      "descripciones_largas": { "es": "Asociado a …", … },
      "constelacion": { "estrellas": [[x, y], …], "lineas": [[i, j], …] },  // viewBox 200×200
      "figura": ["M64 92 C60 66 …", …]                  // trazos SVG ("d"); los que acaban en Z se rellenan
    }
  ]
}
```

Los campos se validan en `generar.validar()`. Cualquier cambio de esquema debe tocar ahí, en las pruebas y en este README.

## Uso

```sh
python3 generar.py              # regenera constelaciones/ y por-idioma/
python3 generar.py --comprobar  # falla si lo committeado no coincide con datos/signos.json
python3 -m unittest -v pruebas  # pruebas de coherencia
```

Para cambiar un nombre, una descripción, una estrella o una figura, edita `datos/signos.json`, ejecuta `python3 generar.py` y revisa el diff de los archivos generados. `--comprobar` también falla si queda un archivo en `constelaciones/` o `por-idioma/` que ya nadie genera.

Las pruebas corren en CI (`.github/workflows/pruebas-signos-zodiacales.yml`) cuando cambia esta carpeta.

## Las ilustraciones

Son **esquemas** de 200×200 unidades dibujados a mano: la figura (en trazo dorado) y las estrellas (en blanco, unidas por líneas azules) se ven bien juntas, pero no están a escala, no tienen magnitud ni distancia real, y no son las coordenadas astronómicas de las estrellas. Cada SVG lleva `<title>` y `<desc>` para lectores de pantalla, ids internos únicos por archivo (se pueden incrustar varios en una misma página) y no usa scripts, imágenes ni enlaces. El fondo de estrellas sale de una semilla fija por signo, así que el resultado es el mismo en cada ejecución.

La etiqueta del SVG está en español y la tipografía es `Georgia, serif` del sistema. Si se necesitan etiquetas en otros idiomas, mejor ponerlas en el HTML que rodea al SVG, no dentro de él.
