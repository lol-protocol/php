# Música infinita

Aplicación personal de música que no se acaba, hecha con las melodías de tus propios MIDI, y con visualizaciones al estilo de Winamp. Funciona en el navegador, sin servidor, sin dependencias y sin cuentas. Lo que cargues (MIDI y SoundFont) se queda en tu navegador.

- **Música infinita.** Aprende de las canciones que marques y compone sin parar. Va por secciones con su tonalidad, su tempo, su estilo y una forma (A A' B A…). Cita frases reales de tus canciones, llevadas a la tonalidad del momento y a veces adornadas, y las mezcla con frases nuevas al estilo de esas mismas canciones. Al pasar de una sección a otra modula: a la relativa, a la dominante, un tono más arriba…
- **Buen sonido.** Trae instrumentos propios sintetizados en el navegador: piano aditivo, piano eléctrico FM, cuerdas pulsadas Karplus-Strong, campanas, cuerdas frotadas, pads, coro, vientos y batería. Si cargas una **SoundFont** (`.sf2`), suena con muestras de instrumentos reales. La mezcla pasa por reverberación de convolución, eco sincronizado con el tempo, compresor y limitador.
- **Visualizaciones.** Están la **MilkDrop** (WebGL2: cada cuadro se dibuja sobre el anterior, acercado, girado y deformado, con la onda encima y ajustes que cambian solos), el **espectro** clásico de barras con los picos que caen, el **osciloscopio** (con Lissajous) y una **lluvia de notas** que muestra qué toca cada voz y lo que viene.
- **Calidad sin pérdida.** Puedes grabar lo que suena en **FLAC** de 16 o 24 bits, o exportar los minutos que quieras en FLAC, WAV o MIDI. La exportación se calcula más rápido que el tiempo real y siempre da la misma música con la misma semilla.

## Uso

Abre `index.html` en el navegador (Chrome, Edge o Firefox; no hace falta servidor) y pulsa **Empezar**. Suena primero con siete melodías de ejemplo de dominio público. Para que suene con las tuyas:

1. Abre el panel con **☰**.
2. En «Biblioteca», usa **Cargar MIDI** o **Cargar carpeta**, o arrastra los `.mid` a la ventana.
3. Marca las canciones que quieras en la mezcla. Cuando hay MIDI propios, los ejemplos empiezan desmarcados.

Cada canción muestra la tonalidad, el tempo, el compás y la cantidad de frases que se detectaron. **▶ tal cual** toca el MIDI sin cambiarlo, con el mismo sintetizador y las mismas visualizaciones. Cuando termina, sigue la música infinita.

| Ajuste | Qué hace |
|---|---|
| Fidelidad | Cuántos bloques son citas de tus canciones. Con 1 suena casi a las canciones tal cual; con 0, todo es música nueva con su estilo. |
| Variación | Cuánto se transforman las citas: notas de paso, ritmo con puntillo, octava, otro final, inversión. |
| Estilo | Ambiente, Pop, Orquestal, Chiptune, Lo-fi o Cajita de música (cambian los instrumentos y los patrones del acompañamiento). En «Automático» cambia de vez en cuando. |
| Tempo | Multiplica el tempo de las canciones. |
| Semilla | La misma semilla da la misma música. Vacía, sale una al azar, y la pantallita muestra cuál fue. |

Atajos de teclado: **espacio** reproduce o pausa, **N** pasa a otra sección (o sale del modo «tal cual»), **V** cambia de visualización, **P** cambia el ajuste de la MilkDrop, **F** pone la pantalla completa, **R** graba y **H** oculta el panel. El panel también se esconde solo cuando no mueves el ratón.

### Mejor sonido: una SoundFont

Los instrumentos propios suenan bien, pero una SoundFont con muestras grabadas suena mucho mejor. Algunas libres y buenas (búscalas por su nombre):

- **GeneralUser GS**: los 128 instrumentos General MIDI y la batería, en unos 30 MB. Es la más equilibrada.
- **FluidR3_GM** (licencia MIT): General MIDI completa, unos 140 MB.
- **MuseScore_General** (licencia MIT): la de MuseScore, General MIDI completa.
- **Salamander Grand Piano**, en versión `.sf2`: un piano de cola muy bueno, solo piano.

Cárgala con **Cargar SoundFont (.sf2)** o arrastrándola a la ventana. Queda guardada en el navegador para la próxima vez. Si a la SoundFont le falta un instrumento, ese instrumento suena con el sintetizador propio. Del formato se usan los rangos, el bucle, la afinación, la atenuación, el paneo, la envolvente de volumen, el filtro, la reverberación y la clase exclusiva. Los moduladores, los LFO y la envolvente de modulación no se usan, así que el sonido queda un poco más estático que en FluidSynth, aunque las muestras son las mismas.

### Grabar y exportar

- **● Grabar** guarda lo que suena, tal cual, en FLAC (16 o 24 bits, en «Guardar»). Al detenerla se descarga el archivo.
- **Exportar** calcula de 1 a 120 minutos desde el principio, con la semilla y los ajustes del momento, en FLAC (16 o 24 bits), WAV o MIDI. El audio se calcula por tramos de dos minutos, así que una hora no necesita una hora de audio en memoria. Cada tramo arranca 12 segundos antes, para que las notas largas y la reverberación del tramo anterior empalmen sin costura. El MIDI va a 120 bpm fijos, con un pulso por cada medio segundo, así que sus tiempos son exactos; los cambios de instrumento van dentro de cada pista.

El codificador FLAC es propio (`js/flac.js`): usa predictores fijos con código de Rice y decorrelación estéreo, como el modo rápido de la herramienta `flac`. No usa LPC, así que comprime en torno a un 5-10 % menos, pero las muestras son exactamente las mismas que en un WAV. El archivo lleva su MD5, así que `flac -t` puede verificarlo.

## Privacidad y derechos

- No se sube nada. Los MIDI y la SoundFont se guardan en el IndexedDB de tu navegador. Puedes quitarlos de la biblioteca con ✕, o borrarlos todos borrando los datos del sitio.
- Las melodías de ejemplo (`js/ejemplos.js`) son de dominio público: tradicionales o de compositores muertos hace más de 70 años, y escritas a mano para este proyecto.
- El `.gitignore` de esta carpeta ignora `.mid`, `.sf2`, `.flac` y `.wav`, para que lo tuyo no termine en el repositorio por accidente.
- La aplicación es para uso personal: lo que genera se basa en las melodías de tus MIDI, y si esas canciones tienen dueño, las grabaciones también llevan su música.

## Cómo compone

1. **Análisis** (`js/analisis.js`). En cada MIDI busca la pista más melódica: la más aguda, la menos polifónica y la que tenga un nombre como «melody» o «voz». De esa pista toma la línea superior y la cuantiza a semicorcheas o tresillos. La tonalidad sale por Krumhansl-Schmuckler, ayudado por la nota del bajo al principio y al final. La melodía se guarda en **grados de la escala**, no en semitonos, y se corta en frases en los silencios, en las notas largas o cada cuatro compases. Los acordes se detectan cada medio compás y se guardan relativos a la tónica.
2. **Modelos** (`js/generador.js`). Una cadena de Markov de orden 1 a 3 aprende los intervalos y ritmos de todas las melodías marcadas. Otra, de orden 1 a 2, aprende las progresiones de acordes, por separado en mayor y en menor.
3. **Secciones.** Cada sección se ancla en una de las canciones, de la que toma el modo, el compás y el tempo. Además elige estilo, instrumentos y una forma. Cada bloque de la forma es una **cita**, que sigue la canción frase a frase y toca sus acordes, o una **frase libre**, que sale de las cadenas y cae en notas del acorde en los tiempos fuertes. Las letras repetidas de la forma (A, A') son la misma idea con variaciones, y eso es lo que hace que suene a música y no a notas al azar.
4. **Arreglo.** La melodía, los acordes (con conducción de voces: cada acorde queda cerca del anterior), el bajo, el arpegio y la batería salen de patrones por estilo. Cada nota lleva un poco de humanización en el tiempo y en la fuerza, y en lo-fi hay swing.

Todo es determinista con la semilla: el mismo generador da la misma música en vivo, en la exportación y en las pruebas.

## Contenido

| Archivo | Qué es |
|---|---|
| `index.html`, `css/estilo.css` | La aplicación. |
| `js/midi.js` | Lee MIDI estándar (formatos 0, 1 y 2, running status, mapa de tempos, RMID) y lo escribe. |
| `js/teoria.js` | Escalas, grados, tonalidad, acordes y cifrado romano. |
| `js/analisis.js` | Melodía, frases y acordes de una canción. |
| `js/ejemplos.js` | Las siete melodías de dominio público y su conversión a MIDI. |
| `js/generador.js` | El compositor infinito: modelos, secciones, citas, variaciones y arreglo. |
| `js/sf2.js` | Lector de SoundFont 2. |
| `js/flac.js` | Codificador FLAC, MD5 y WAV. |
| `js/sintetizador.js` | Motor de Web Audio: instrumentos propios, SoundFont y mezcla. Solo navegador, salvo sus recetas, que también corren en Node. |
| `js/visualizador.js` | Las visualizaciones. Solo navegador. |
| `js/app.js` | Interfaz, biblioteca en IndexedDB, planificación, grabación y exportación. Solo navegador. |
| `pruebas/` | Pruebas en Node (`*.test.js`) y en Chromium (`navegador.js`). `ayudas/` arma una SoundFont mínima y trae un decodificador FLAC de prueba. |

Los módulos de lógica (`midi`, `teoria`, `analisis`, `ejemplos`, `generador`, `sf2` y `flac`) son funciones puras. Corren igual en el navegador, cargados como `<script>`, y en Node, con `require`.

## Pruebas

```
node --test 'pruebas/*.test.js'
node pruebas/navegador.js
```

La primera tanda prueba el MIDI (ida y vuelta, tempos, running status), la teoría, el análisis de los ejemplos, el generador (determinismo, continuidad, fidelidad, escala, estilos), el lector de SoundFont, el FLAC y las recetas del sintetizador. Para el FLAC hay tres comprobaciones: la ida y vuelta exacta con un decodificador propio que verifica los CRC y el MD5, la lectura bit a bit con ffmpeg (si está instalado) y la afinación de las recetas. La segunda abre la aplicación en Chromium: arranca y suena, recorre las cuatro visualizaciones, carga un MIDI y una SoundFont, toca el MIDI tal cual y exporta un FLAC que después se verifica. Necesita el paquete `playwright`; en las sesiones en la nube ya está instalado globalmente: `NODE_PATH=$(npm root -g) node pruebas/navegador.js`.

`.github/workflows/pruebas-musica-infinita.yml` corre las dos tandas cuando cambia esta carpeta.

## Límites conocidos

- La detección de melodía es una heurística. Si un MIDI tiene la melodía en una pista grave o repartida entre varias, puede elegir otra pista. La tonalidad y los acordes también pueden salir errados en música muy cromática o modal.
- Las citas solo toman la melodía y la armonía de la canción, no su arreglo: el acompañamiento lo pone el estilo.
- La MilkDrop necesita WebGL2; sin WebGL2 se ve una versión más simple en canvas 2D.
- «Cargar carpeta» depende de `webkitdirectory`, que no está en todos los navegadores. Siempre se puede arrastrar la carpeta o elegir varios archivos.
