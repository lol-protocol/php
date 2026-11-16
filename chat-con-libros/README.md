# chat-con-libros

Un chat personal para «hablar» con un libro o con la obra de un autor, sin modelo de lenguaje
ni conexión a internet. Le das el texto (un `.txt`) y el sistema responde a tus preguntas
**citando los pasajes del libro**, con su capítulo. Está en PHP (sin dependencias) y tiene un
port a Java con las mismas respuestas.

> **Lo que no es.** No es una IA que escribe ni que razona: no inventa nada ni sintetiza
> varias ideas en una respuesta nueva. Busca en el texto los párrafos más parecidos a tu
> pregunta (TF-IDF con similitud coseno) y los muestra. Si el libro no habla de algo, lo dice.
> Es lo más parecido a «una IA simple» que cabe en un proyecto sin dependencias: si después
> quieres respuestas en lenguaje natural, el lugar para agregarlo es `src/Conversador.php`.

## Qué puedes preguntar

| Pregunta | Qué responde |
|---|---|
| `hola` | Un saludo con el título y el autor. |
| `¿quién escribió esto?`, `¿cómo se llama?` | El autor y el título, tal como están en el archivo. |
| `¿de qué trata?`, `resumen` | Las palabras más repetidas del libro y el inicio del primer párrafo. |
| `¿qué pasa con el reloj de la plaza?` | Hasta dos pasajes que más se parecen, con su capítulo. |
| `¿hay dragones?` | «No encuentro eso»: ninguna palabra de la pregunta aparece en el libro. |

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
probar. Cuando uses tus propios libros, guárdalos en `mis-libros/`, que está en `.gitignore`:
no los subas al repositorio, porque la mayoría tiene derechos de autor.

## Uso

En PHP 8.1 o superior (probado con 8.3), desde esta carpeta:

```bash
php bin/chat.php                      # usa ejemplos/muestra.txt
php bin/chat.php mis-libros/mi.txt    # tu libro
```

Escribe una pregunta y Enter; `salir` termina.

En Java 17 o superior:

```bash
javac -encoding UTF-8 -d /tmp/chat-libros $(find java -name '*.java')
java -cp /tmp/chat-libros chatlibros.Chat mis-libros/mi.txt
```

## Cómo funciona

1. **Normalizar** (`src/Normaliza.php`): minúsculas, sin acentos, sin palabras vacías
   («de», «la», «que»…) y con una raíz muy simple (quita «-s» y «-es»: «relojeros» → «relojero»).
2. **Índice** (`src/Indice.php`): cada pasaje es un vector de pesos TF-IDF normalizado. Una
   pregunta se convierte al mismo espacio, y el puntaje es el coseno entre los dos.
3. **Respuesta** (`src/Conversador.php`): si el puntaje de un pasaje pasa el umbral de 0,10, se
   cita (hasta dos). Si ninguno lo pasa, se dice que el libro no habla de eso. Las preguntas de
   saludo, autor, título y resumen se responden con reglas, no con el índice.

## Pruebas

```bash
php pruebas/ejecutar.php              # 36 pruebas del núcleo en PHP
php bin/vectores.php --comprobar      # que pruebas/vectores.tsv esté al día
```

`pruebas/vectores.tsv` guarda, para cada consulta de `pruebas/consultas.txt`, el pasaje que queda
primero y su puntaje con seis decimales. Lo genera `bin/vectores.php`. Si cambias las palabras
vacías, la raíz o el índice, regenéralo con `php bin/vectores.php > pruebas/vectores.tsv`. El port
de Java lee el mismo archivo y debe dar lo mismo:

```bash
javac -encoding UTF-8 -d /tmp/chat-libros $(find java -name '*.java')
java -cp /tmp/chat-libros chatlibros.EjecutarPruebas   # las mismas 36 pruebas
```

El workflow `pruebas-chat-con-libros.yml` corre las dos y comprueba que `vectores.tsv` coincida.

## Documentación de arquitectura

Diagramas en [Mermaid](https://mermaid.js.org/) (`.mmd`) en `docs/`; GitHub y la mayoría de los
editores los dibujan, y `mmdc` (mermaid-cli) los convierte a SVG o PNG.

| Diagrama | Qué muestra |
|---|---|
| [`docs/contexto.mmd`](docs/contexto.mmd) | El sistema visto desde fuera: quién lo usa y qué datos lee. |
| [`docs/arquitectura.mmd`](docs/arquitectura.mmd) | Capas y componentes de PHP y de Java, y cómo `vectores.tsv` los mantiene iguales. |
| [`docs/clases.mmd`](docs/clases.mmd) | Clases del núcleo y sus relaciones. |
| [`docs/flujo-carga-e-indexado.mmd`](docs/flujo-carga-e-indexado.mmd) | Del `.txt` al índice TF-IDF al arrancar. |
| [`docs/flujo-pregunta.mmd`](docs/flujo-pregunta.mmd) | Qué pasa desde que preguntas hasta que recibes la respuesta. |
| [`docs/pruebas-y-ci.mmd`](docs/pruebas-y-ci.mmd) | Las pruebas, la CI y la paridad entre PHP y Java. |

## Estructura

```text
src/          núcleo en PHP: Normaliza, Libro, Indice, Conversador (cargar.php es el cargador)
bin/          chat.php (la conversación en la terminal) y vectores.php (genera vectores.tsv)
java/src/     port a Java de src/, con Chat.java para la terminal
java/pruebas/ pruebas del port a Java
pruebas/      pruebas en PHP, consultas.txt y vectores.tsv
ejemplos/     muestra.txt, un cuento inventado para probar
docs/         diagramas de arquitectura en Mermaid (.mmd)
```
