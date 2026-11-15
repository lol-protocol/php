# Astros en 3D

Visor WebGL de 52 astros: 12 planetas (8 planetas y 4 planetas enanos: Plutón, Ceres, Eris y Makemake), 21 lunas (de la Tierra, Marte, Júpiter, Saturno, Urano, Neptuno y Plutón) y 19 soles (el Sol y 18 estrellas de las constelaciones, entre ellas las de seis constelaciones zodiacales). Cada uno se dibuja con su textura y sus colores, generados en el navegador a partir de su tipo y, en las estrellas, de su temperatura y su color propio, y con su ficha de características reales. Es para el sistema de astrología de genealogía: ofrece la parte visual y los datos de referencia; no calcula ninguna interpretación.

> **Aviso.** La astrología no tiene respaldo científico: ningún astro influye en el carácter ni en el destino de una persona. Este proyecto muestra los astros tal como son, y sus datos son de astronomía. Las lecturas que la astrología les atribuye no están aquí.

## Contenido

| Archivo | Qué es |
|---|---|
| `index.html` | El visor. Abre el archivo en el navegador: no hace falta servidor. |
| `js/visor.js` | WebGL2 a mano, sin dependencias: matrices, geometría de esfera, anillos y halo, shaders, cámara y ficha. |
| `js/texturas.js` | Texturas procedurales: ruido con semilla, bandas, cráteres, océanos y nubes, y el color de una estrella a partir de su temperatura. Funciones puras: corren en el navegador y en Node. |
| `js/ajustes.js` | Los ajustes de comodidad: el astro empieza quieto, la velocidad de giro se limita a un rango lento y se recuerda la elección. Funciones puras, probadas en Node. |
| `js/datos.js` | El catálogo para el visor. **Generado**, no editar a mano. |
| `datos/astros.json` | La fuente de verdad: nombre, tipo, radio, masa, rotación, órbita, temperatura, atmósfera, anillos y la textura de cada astro. |
| `caracteristicas.md` | La tabla de características, por categoría. **Generada.** |
| `generar.js` | Lee `datos/astros.json` y genera `js/datos.js` y `caracteristicas.md`. Solo usa Node. |
| `pruebas/` | `datos.test.js` (coherencia del catálogo), `texturas.test.js` (texturas y colores), `ajustes.test.js` (comodidad del visor) y `generar.test.js` (valores derivados y que lo generado esté al día). |

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

La CI corre las dos. `--comprobar` falla si `js/datos.js` o `caracteristicas.md` no coinciden con el JSON.

La revisión visual (que el visor compile y pinte en Chromium) se hizo a mano con Chromium headless y WebGL por software: no está en la CI. Si cambias un shader o una textura, abre `index.html` y recorre los 52 astros.

## Decisiones

- **Escala.** Todos los astros se dibujan con radio 1 y giran a la misma velocidad. Así se ven todos, pero no están a escala: el radio, la masa y la distancia reales están en la ficha y en `caracteristicas.md`.
- **Comodidad visual.** El movimiento continuo cansa, así que el astro empieza quieto, la velocidad máxima es lenta (una vuelta cada 10 s) y, si el sistema pide reducir el movimiento (`prefers-reduced-motion`), el astro empieza quieto aunque antes se hubiera dejado girando. Los colores de los planetas gaseosos son suaves a propósito, en especial los de Júpiter.
- **Planetas enanos y lunas.** Eris y Makemake no tienen inclinación de eje conocida (`null`, «—» en la tabla). Todas las lunas están en rotación sincrónica con su órbita, y una prueba lo comprueba para detectar datos mal copiados. Los asteroides y cometas no están: tienen forma irregular y el visor solo dibuja esferas.
- **Texturas procedurales.** No se descargan imágenes ni mapas: la textura se calcula en el navegador con ruido con semilla, así que es la misma en cada visita y no depende de la red. No son fotografías ni mapas científicos; los colores son aproximados.
- **Estrellas.** Cada una tiene un color propio y distinto (`textura.color` en `datos/astros.json`), y un puntito de ese color en la lista. El color es ilustrativo: la temperatura superficial fija su familia (azul, blanca, amarilla, naranja o roja) y se exagera la saturación para distinguirlas; una prueba comprueba que las calientes salgan azuladas y las frías, rojizas. Cada una trae su constelación, y el signo zodiacal cuando la constelación es zodiacal (Hamal en Aries, Aldebarán en Tauro, Pólux en Géminis, Regulus en Leo, Spica en Virgo y Antares en Escorpio). Su radio es poco preciso en cuatro de ellas (Polaris, Betelgeuse, Antares y Deneb): está marcado en la tabla. La edad queda vacía cuando no hay un dato fiable.
- **Sin dependencias.** Ni bibliotecas de WebGL ni CDN. El visor se abre sin red.
- **Datos.** Valores redondeados de la NASA (Planetary Fact Sheet y las fichas de satélites) y de la literatura para las estrellas. Las temperaturas de las lunas pequeñas y de algunas estrellas son aproximadas. La versión de los datos está en `datos/astros.json`.

## Idioma

Todo está en español. Los nombres de los astros están en español (Ío, Ganímedes, Encélado, Júpiter) salvo las estrellas, que usan su nombre propio (Sirio A, Vega, Polaris, Betelgeuse, Antares, Aldebarán, Hamal, Pólux, Regulus, Spica, Rigel, Capella Aa, Arcturus, Procyon A, Altair, Deneb, Fomalhaut A y Próxima Centauri).
