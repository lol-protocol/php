# Signos del zodiaco y horóscopo chino

Los 12 signos del zodiaco occidental y los 12 animales del horóscopo chino, cada uno con su nombre y una descripción breve de sus supuestas características, en 30 idiomas. Además, un esquema SVG de la constelación de cada signo occidental.

> **Aviso.** Las descripciones recogen lo que la astrología y el horóscopo chino *atribuyen* a cada signo. No hay evidencia científica de que la fecha de nacimiento influya en el carácter. Las constelaciones son figuras de la tradición, no grupos de estrellas físicamente relacionados.

## Contenido

| Archivo | Qué es |
|---|---|
| `datos/signos.json` | La fuente de verdad: nombres, descripciones, fechas y forma de cada constelación. |
| `generar.py` | Genera lo demás a partir del JSON. Solo usa la biblioteca estándar de Python 3. |
| `constelaciones/*.svg` | Un SVG por signo occidental. Generados, no editar a mano. |
| `signos-por-idioma.md` | La tabla completa, una sección por idioma. Generada, no editar a mano. |

### Idiomas

Español, inglés, portugués, francés, italiano, alemán, neerlandés, sueco, polaco, ruso, ucraniano, turco, griego, árabe, hebreo, hindi, chino (simplificado), japonés, coreano, vietnamita, tailandés, indonesio, persa, urdu, bengalí, rumano, checo, finés, suajili y danés.

### Zodiaco occidental

| Signo | Fechas aproximadas |
|---|---|
| Aries | 21/03 – 19/04 |
| Tauro | 20/04 – 20/05 |
| Géminis | 21/05 – 20/06 |
| Cáncer | 21/06 – 22/07 |
| Leo | 23/07 – 22/08 |
| Virgo | 23/08 – 22/09 |
| Libra | 23/09 – 22/10 |
| Escorpio | 23/10 – 21/11 |
| Sagitario | 22/11 – 21/12 |
| Capricornio | 22/12 – 19/01 |
| Acuario | 20/01 – 18/02 |
| Piscis | 19/02 – 20/03 |

Las fechas cambian un día según el año y la hora de nacimiento.

### Horóscopo chino

Doce animales que se repiten en ciclo. El año empieza en el Año Nuevo lunar, que cae entre finales de enero y mediados de febrero, así que quien nació en enero o principios de febrero puede tener el signo del año anterior.

Rata, buey, tigre, conejo, dragón, serpiente, caballo, cabra, mono, gallo, perro y cerdo.

Algunos idiomas usan otro animal para el mismo lugar del ciclo: en vietnamita el conejo es *Mèo* (gato) y el buey es *Trâu* (búfalo), y en japonés la cabra es *ひつじ* (oveja). La tabla respeta cada uso.

## Uso

```sh
python3 generar.py              # regenera constelaciones/ y signos-por-idioma.md
python3 generar.py --comprobar  # falla si lo committeado no coincide con datos/signos.json
```

Para cambiar un nombre, una descripción o una estrella, edita `datos/signos.json`, ejecuta `python3 generar.py` y revisa el diff de los archivos generados.

`generar.py` comprueba antes de escribir que cada signo tenga los 30 idiomas, que las líneas de cada constelación apunten a estrellas que existan y que los SVG sean XML válido.

## Las constelaciones

Son **esquemas** de 200×200 unidades: las posiciones de las estrellas están dibujadas a mano para reconocer la forma de la figura. No están a escala, no tienen magnitud ni distancia real, y no son las coordenadas astronómicas exactas de las estrellas.
