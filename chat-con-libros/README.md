# chat-con-libros

Un chat personal para «hablar» con un libro o con la obra de un autor. Le das el texto (un
`.txt`) y responde a tus preguntas **citando los pasajes del libro**, con su capítulo. Se usa
en la terminal o en una página web local, y tiene dos modos:

- **Sin IA (por defecto):** busca en el texto los párrafos más parecidos a tu pregunta
  (TF-IDF con similitud coseno, más sinónimos) y los muestra. No hay modelo de lenguaje ni
  conexión a internet, y no inventa nada: si el libro no habla de algo, lo dice.
- **Con IA (opcional):** la misma búsqueda encuentra los pasajes y Claude, el modelo de
  Anthropic, redacta la respuesta en lenguaje natural usando solo esos pasajes.

El núcleo está en PHP sin dependencias, con un port a Java de la búsqueda que da las mismas
respuestas. La web y el modo con IA son solo PHP.

> **Lo que es y lo que no.** Sin IA no sintetiza ni razona: te muestra lo que el libro dice.
> Con IA puede resumir y explicar, pero un modelo de lenguaje puede equivocarse: el prompt le
> exige responder solo con los pasajes y citar el capítulo, y si la búsqueda no encuentra nada
> ni siquiera se le pregunta, pero conviene verificar lo importante en la cita.

## Qué puedes preguntar

| Pregunta | Qué responde |
|---|---|
| `hola` | Un saludo con el título y el autor. |
| `¿quién escribió esto?`, `¿cómo se llama?` | El autor y el título, tal como están en el archivo. |
| `¿de qué trata?`, `resumen` | Las palabras más repetidas del libro y el inicio del primer párrafo. |
| `¿qué pasa con el reloj de la plaza?` | Los pasajes que más se parecen (hasta dos), con su capítulo; con IA, una respuesta redactada. |
| `¿hay dragones?` | «No encuentro eso»: ninguna palabra de la pregunta (ni sus sinónimos) aparece en el libro. |

Las preguntas de saludo, autor, título y resumen se responden con reglas, también en el modo
con IA: no gastan una llamada.

## Formato del libro

Un archivo de texto en UTF-8:

```text
Título: Las horas de Tomás
Autor: Elena Varela

# Capítulo 1: El relojero

En el pueblo de Arenal había un solo relojero…

Los niños se burlaban de él…

# Capítulo 2: La carta

…
```

- `Título:` y `Autor:` van en cualquier línea. Si faltan, se usan «Libro sin título» y
  «autor desconocido».
- Una línea que empieza con `#` o con `Capítulo ` abre un capítulo nuevo.
- Cada párrafo es un pasaje; los párrafos se separan con una línea en blanco.

`ejemplos/muestra.txt` es un cuento corto **inventado** (de una autora ficticia) que sirve para
probar. Tus propios libros guárdalos en `mis-libros/`, que está en `.gitignore`: no los subas al
repositorio, porque la mayoría tiene derechos de autor.

## Uso en la terminal

En PHP 8.1 o superior (probado con 8.3), desde esta carpeta:

```bash
php bin/chat.php                       # usa ejemplos/muestra.txt
php bin/chat.php mis-libros/mi.txt     # tu libro
php bin/chat.php --ia mis-libros/mi.txt  # además redacta con Claude (ver «Modo con IA»)
```

Escribe una pregunta y Enter; `salir` termina. En la terminal, una pregunta corta como «¿y la
hermana?» se completa con la anterior (solo con IA).

En Java 17 o superior (solo la versión sin IA):

```bash
javac -encoding UTF-8 -d /tmp/chat-libros $(find java -name '*.java')
java -cp /tmp/chat-libros chatlibros.Chat mis-libros/mi.txt
```

## Página web

```bash
php -S 127.0.0.1:8080 -t web
```

y abre <http://127.0.0.1:8080>. Elige el libro (los `.txt` de `ejemplos/` y `mis-libros/`),
escribe tu pregunta y, si el modo con IA está disponible, marca la casilla. Cada respuesta dice
cómo se obtuvo (reglas, pasajes del libro, o IA) y, con IA, deja ver los pasajes usados.

Está pensada para tu propio computador, no para publicarla en internet: no tiene cuentas ni
límites de uso, y el modo con IA gasta el saldo de tu clave. Por eso, la API (`src/Api.php`):

- solo atiende si el `Host` es `localhost`, `127.0.0.1` o `[::1]`, y si el pedido trae `Origin`,
  que sea el mismo sitio (para que otra página abierta en tu navegador no pueda usar tu servidor);
- exige `application/json` en el POST y no manda cabeceras CORS;
- deja elegir el libro solo de la lista de `ejemplos/` y `mis-libros/`, nunca por una ruta;
- limita el tamaño de la pregunta (500 caracteres), del historial y del pedido;
- la página no usa scripts ni estilos en línea (política `Content-Security-Policy` estricta) y
  pone todo el texto con `textContent`.

Si necesitas abrirla desde otra máquina, ponle delante un proxy con autenticación: no la expongas
tal cual.

## Modo con IA (opcional)

El SDK de Anthropic no es una dependencia del proyecto: se instala solo si quieres este modo.

```bash
composer require anthropic-ai/sdk guzzlehttp/guzzle   # Guzzle: el SDK necesita un cliente HTTP PSR-18
export ANTHROPIC_API_KEY=...                          # tu clave de https://console.anthropic.com
php bin/chat.php --ia mis-libros/mi.txt               # o marca la casilla en la web
```

- **Modelo.** Por defecto `claude-opus-5-5`. Para algo más rápido y barato:
  `export ANTHROPIC_MODEL=claude-haiku-5-5` (o `claude-sonnet-5-5`).
- **Qué se envía.** A la API de Anthropic le llegan tu pregunta, los últimos 3 turnos y los
  hasta 4 pasajes que encontró la búsqueda: **nunca el libro entero**. Aun así son fragmentos
  de tu libro: no uses este modo con textos que no puedas compartir con un tercero.
- **Cuándo se llama.** Solo si la pregunta es sobre el contenido y la búsqueda encontró
  pasajes. Un saludo, el autor o «no encuentro eso» no hacen ninguna llamada.
- **Si falla** (clave mala, sin red, límite de uso, negativa del modelo), se muestra la búsqueda
  normal con un aviso, en vez de un error.
- **Contra instrucciones escondidas en el libro.** Los pasajes van dentro de `<pasajes>`, con el
  marcado escapado, y el prompt de sistema le dice al modelo que es texto del libro y no órdenes.
  No es una garantía absoluta: un archivo que no controlas puede traer frases que intenten
  manipular al modelo.
- **Lo que no probé.** Verifiqué el modo con IA contra el SDK real apuntando a un servidor local que
  imita la API (la petición que sale, la respuesta, los errores de clave, de conexión y la negativa
  del modelo), pero **no contra la API real de Anthropic**: no tuve una clave. La primera vez, revisa
  que responda bien. Tampoco activé el reintento automático con otro modelo ante una negativa
  (`fallbacks`): el SDK de PHP lo permite, pero su forma exacta no está documentada aquí.

## Cómo funciona

1. **Normalizar** (`src/Normaliza.php`): minúsculas, sin acentos, sin palabras vacías
   («de», «la», «que»…) y con una raíz simple: quita el plural («relojeros» → «relojero») y
   terminaciones frecuentes («esperaban» → «esper», «claramente» → «clara»). No es un
   lematizador: junta las formas comunes, no todas.
2. **Sinónimos** (`src/Sinonimos.php`, `datos/sinonimos.tsv`): grupos como «carta, mensaje,
   misiva». La pregunta se amplía con los demás miembros del grupo, así «¿qué decía el mensaje?»
   encuentra la carta aunque el libro nunca diga «mensaje». Para un libro concreto, pon un
   `<libro>.sinonimos.tsv` junto al `.txt`: se suma al general.
3. **Índice** (`src/Indice.php`): cada pasaje es un vector de pesos TF-IDF normalizado. Una
   pregunta se convierte al mismo espacio, y el puntaje es el coseno entre los dos.
4. **Respuesta** (`src/Conversador.php`, `src/Dialogo.php`): si el puntaje de un pasaje pasa el
   umbral de 0,10, se cita (hasta dos; con IA, hasta cuatro se le dan al modelo). Si ninguno lo
   pasa, se dice que el libro no habla de eso.
5. **Redacción con IA** (`src/GeneradorClaude.php`): solo en el modo con IA.

## Documentación de arquitectura

Diagramas en [Mermaid](https://mermaid.js.org/) (`.mmd`) en `docs/`; GitHub y la mayoría de los
editores los dibujan, y `mmdc` (mermaid-cli) los convierte a SVG o PNG.

| Diagrama | Qué muestra |
|---|---|
| [`docs/contexto.mmd`](docs/contexto.mmd) | El sistema visto desde fuera: quién lo usa y qué datos lee. |
| [`docs/arquitectura.mmd`](docs/arquitectura.mmd) | Capas y componentes de PHP y de Java, y cómo `vectores.tsv` los mantiene iguales. |
| [`docs/clases.mmd`](docs/clases.mmd) | Clases del núcleo y sus relaciones. |
| [`docs/flujo-carga-e-indexado.mmd`](docs/flujo-carga-e-indexado.mmd) | Del `.txt` al índice TF-IDF al arrancar. |
| [`docs/flujo-pregunta.mmd`](docs/flujo-pregunta.mmd) | Qué pasa desde que preguntas hasta que recibes la respuesta, con y sin IA. |
| [`docs/pruebas-y-ci.mmd`](docs/pruebas-y-ci.mmd) | Las pruebas, la CI y la paridad entre PHP y Java. |

## Pruebas

```bash
php pruebas/ejecutar.php              # 91 pruebas del núcleo, la API web y el modo con IA (con un cliente falso)
php bin/vectores.php --comprobar      # que pruebas/vectores.tsv esté al día
```

No necesitan el SDK ni red. `pruebas/vectores.tsv` guarda, para cada consulta de
`pruebas/consultas.txt`, el pasaje que queda primero y su puntaje con seis decimales (con los
sinónimos de `datos/sinonimos.tsv`). Lo genera `bin/vectores.php`; si cambias las palabras vacías,
la raíz, los sinónimos o el índice, regenéralo con `php bin/vectores.php > pruebas/vectores.tsv`.
El port de Java lee el mismo archivo y debe dar lo mismo:

```bash
javac -encoding UTF-8 -d /tmp/chat-libros $(find java -name '*.java')
java -cp /tmp/chat-libros chatlibros.EjecutarPruebas   # 54 pruebas del port (la parte sin IA)
```

`pruebas/evaluacion.tsv` tiene preguntas de ejemplo con los pasajes que las responden. Las pruebas
miden en cuántas acierta la búsqueda con y sin sinónimos (hoy 8 de 8 contra 4 de 8). Es una
evaluación pequeña, sobre un solo texto inventado: sirve para notar si un cambio empeora la
búsqueda, no como medida general de calidad. Para tus libros, escribe tus propias preguntas.

El workflow `pruebas-chat-con-libros.yml` corre las dos y comprueba que `vectores.tsv` coincida.

## Estructura

```text
src/          núcleo en PHP: Normaliza, Sinonimos, Libro, Indice, Conversador, Dialogo, Api,
              Generador (interfaz), GeneradorClaude (modo con IA) y GeneradorError
bin/          chat.php (la conversación en la terminal) y vectores.php (genera vectores.tsv)
web/          la página (index.html, estilo.css, app.js) y api.php, la entrada de src/Api.php
datos/        sinonimos.tsv
java/src/     port a Java de la búsqueda, con Chat.java para la terminal
java/pruebas/ pruebas del port a Java
pruebas/      pruebas en PHP, consultas.txt, vectores.tsv y evaluacion.tsv
ejemplos/     muestra.txt, un cuento inventado para probar
docs/         diagramas de arquitectura en Mermaid (.mmd)
```
