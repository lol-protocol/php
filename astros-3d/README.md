# Astros en 3D

Visor WebGL de 52 astros: 12 planetas (8 planetas y 4 planetas enanos: Plutón, Ceres, Eris y Makemake), 21 lunas (de la Tierra, Marte, Júpiter, Saturno, Urano, Neptuno y Plutón) y 19 soles (el Sol y 18 estrellas de las constelaciones, entre ellas las de seis constelaciones zodiacales). Cada uno se dibuja con su textura y sus colores, generados en el navegador a partir de su tipo y, en las estrellas, de su temperatura y su color propio, y con su ficha de características reales. Es para el sistema de astrología de genealogía: ofrece la parte visual y los datos de referencia; no calcula ninguna interpretación.

> **Aviso.** La astrología no tiene respaldo científico: ningún astro influye en el carácter ni en el destino de una persona. Este proyecto muestra los astros tal como son, y sus datos son de astronomía. Las lecturas que la astrología les atribuye no están aquí.

## Contenido

| Archivo | Qué es |
|---|---|
| `index.html` | El visor. Abre el archivo en el navegador: no hace falta servidor. |
| `js/visor.js` | WebGL2 a mano, sin dependencias: matrices, geometría de esfera, anillos y halo, shaders, cámara y ficha. |
| `js/texturas.js` | Texturas procedurales: ruido con semilla, bandas, cráteres, océanos y nubes (la Tierra, además, con una capa de nubes aparte), el mapa de normales del relieve de los cuerpos rocosos y helados, y el color de cada estrella (el propio del catálogo o, si falta, el de su temperatura). Funciones puras: corren en el navegador y en Node. |
| `js/ajustes.js` | Los ajustes de comodidad: el astro empieza quieto, la velocidad de giro se limita a un rango lento y se recuerda la elección. Funciones puras, probadas en Node. |
| `js/sombras.js` | La geometría de las sombras de los anillos (qué puntos del planeta quedan a su sombra y qué parte de los anillos queda a la del planeta). Es la versión de referencia de lo que hace el shader, con casos que se comprueban a mano. Funciones puras, probadas en Node. |
| `js/orientacion.js` | El sentido de giro de cada astro. Venus, Urano y Plutón ya traen la inclinación del eje mayor de 90° (que es un giro retrógrado), así que no se invierte otra vez. Funciones puras, probadas en Node. |
| `js/datos.js` | El catálogo para el visor. **Generado**, no editar a mano. |
| `datos/astros.json` | La fuente de verdad: nombre, tipo, radio, masa, rotación, órbita, temperatura, atmósfera, anillos y la textura de cada astro. |
| `caracteristicas.md` | La tabla de características, por categoría. **Generada.** |
| `generar.js` | Lee `datos/astros.json` y genera `js/datos.js` y `caracteristicas.md`. Solo usa Node. |
| `pruebas/` | `datos.test.js` (coherencia del catálogo, incluida la física: la tercera ley de Kepler debe cuadrar para cada planeta y cada luna), `texturas.test.js` (texturas y colores), `ajustes.test.js` (comodidad del visor), `orientacion.test.js` (sentido de giro), `sombras.test.js` (geometría de las sombras) y `generar.test.js` (valores derivados, que lo generado esté al día y la línea de comandos). |

## Uso

Abre `index.html` en el navegador. Elige un astro en la lista (por categoría) y:

- arrastra para girar la vista;
- usa la rueda para acercar o alejar;
- el astro empieza **quieto**: «Girar» lo pone en movimiento, y el deslizador «Velocidad de giro» elige entre una vuelta cada 2 minutos y una cada 10 segundos (por defecto, una cada 52 s). El visor recuerda tu elección en ese navegador;
- «Restablecer vista» vuelve al ángulo inicial.

Si el navegador no tiene WebGL2, la página lo dice y la ficha de cada astro sigue disponible.

Para cambiar un valor, edita `datos/astros.json` y corre:

```
node generar.js
```

## Pruebas

```
node --test 'pruebas/*.test.js'
node generar.js --comprobar
```

La CI corre las dos. `--comprobar` falla si `js/datos.js` o `caracteristicas.md` no coinciden con el JSON. Cualquier otro argumento (por ejemplo, un `--comprobr` mal escrito) falla con código 2 en vez de escribir los archivos sin avisar.

La revisión visual (que el visor compile y pinte en Chromium) se hizo a mano con Chromium headless y WebGL por software: no está en la CI. Si cambias un shader o una textura, abre `index.html` y recorre los 52 astros.

## Decisiones

- **Escala.** Todos los astros se dibujan con radio 1 y giran a la misma velocidad. Así se ven todos, pero no están a escala: el radio, la masa y la distancia reales están en la ficha y en `caracteristicas.md`.
- **Realismo visual, todo procedural.** Los cráteres de los cuerpos rocosos y helados tienen un relieve **plano a propósito** (nada de «hoyos»: los grupos de agujeros pequeños y repetidos incomodan a quien tiene tripofobia): se dibuja la mitad de los cráteres que pide el catálogo, ninguno menor de unos 3 píxeles (a 512 de ancho), con un cuenco poco profundo y un reborde apenas insinuado, sin escalones, y la pared más inclinada no pasa de unos 31°. Una prueba fija cada uno de esos límites. El mapa de normales se calcula en la CPU con las alturas sin redondear (derivarlas en el shader daba píxeles sueltos en el borde de los cráteres), y su ruido fino es mucho menor en el hielo, para que Europa se vea lisa con sus grietas. La Tierra lleva sus nubes en una capa aparte, apenas por encima de la superficie y con su propia deriva (solo se nota con el giro activo). Los anillos echan su sombra sobre el planeta y el planeta, sobre los anillos. `textura.relieve` ajusta la fuerza del relieve astro por astro (1 = normal, 0 = liso) y `textura.capa_nubes` activa la capa de nubes.
- **Comodidad visual.** El movimiento continuo cansa, así que el astro empieza quieto, la velocidad máxima es lenta (una vuelta cada 10 s) y, si el sistema pide reducir el movimiento (`prefers-reduced-motion`), el astro empieza quieto aunque antes se hubiera dejado girando. Los colores de los planetas gaseosos son suaves a propósito, en especial los de Júpiter.
- **Planetas enanos y lunas.** Eris y Makemake no tienen inclinación de eje conocida (`null`, «—» en la tabla). Todas las lunas están en rotación sincrónica con su órbita, y una prueba lo comprueba para detectar datos mal copiados. Los asteroides y cometas no están: tienen forma irregular y el visor solo dibuja esferas.
- **Texturas procedurales.** No se descargan imágenes ni mapas: la textura se calcula en el navegador con ruido con semilla, así que es la misma en cada visita y no depende de la red. No son fotografías ni mapas científicos; los colores son aproximados.
- **Estrellas.** Cada una tiene un color propio y distinto (`textura.color` en `datos/astros.json`), y un puntito de ese color en la lista. El color es ilustrativo: la temperatura superficial fija su familia (azul, blanca, amarilla, naranja o roja) y se exagera la saturación para distinguirlas; una prueba comprueba que las calientes salgan azuladas y las frías, rojizas. Cada una trae su constelación, y el signo zodiacal cuando la constelación es zodiacal (Hamal en Aries, Aldebarán en Tauro, Pólux en Géminis, Regulus en Leo, Spica en Virgo y Antares en Escorpio). Su radio es poco preciso en cuatro de ellas (Polaris, Betelgeuse, Antares y Deneb): está marcado en la tabla. La edad queda vacía cuando no hay un dato fiable.
- **Sin dependencias.** Ni bibliotecas de WebGL ni CDN. El visor se abre sin red.
- **Datos.** Valores redondeados de la NASA (Planetary Fact Sheet y las fichas de satélites) y de la literatura para las estrellas. Las temperaturas de las lunas pequeñas y de algunas estrellas son aproximadas. La versión de los datos está en `datos/astros.json` y se sube cuando cambian los datos (hoy, 1.2.0). «Lunas conocidas» de los planetas gigantes son las cifras de NASA de abril de 2025 y siguen subiendo con los descubrimientos.

## Idioma

Todo está en español. Los nombres de los astros están en español (Ío, Ganímedes, Encélado, Júpiter) salvo las estrellas, que usan su nombre propio (Sirio A, Vega, Polaris, Betelgeuse, Antares, Aldebarán, Hamal, Pólux, Regulus, Spica, Rigel, Capella Aa, Arcturus, Procyon A, Altair, Deneb, Fomalhaut A y Próxima Centauri).
