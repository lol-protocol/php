# Qué MIDI funcionan mejor

La aplicación aprende de tus MIDI: busca la melodía de cada uno, su tonalidad, sus frases y sus acordes. Cuanto más claros estén esos cuatro datos, más reconocibles salen las citas. Esta guía explica qué mira y cómo preparar un MIDI para que lo entienda bien.

## Cómo elige la melodía

Para cada MIDI agrupa las notas por pista y canal (sin contar la batería, que es el canal 10) y le pone un puntaje a cada grupo:

- **Pocas notas a la vez.** Una melodía suena de a una nota. Un grupo con muchas notas solapadas (acordes, arpegios largos) puntúa bajo.
- **Registro medio o agudo.** Un grupo cuya nota promedio está por debajo del do2 (nota 48) se descarta como bajo.
- **Que tenga bastantes notas** (al menos 6; el máximo de puntaje lo da con unas 64).
- **Un nombre de pista con sentido.** Si la pista se llama como *melody*, *lead*, *vocal*, *voz*, *canto*, *solo*, *tema*, *theme*, *right* o *derecha*, suma bastante.
- **Un instrumento de melodía.** Los efectos y la percusión melódica rara (programas General MIDI 113 a 128) restan, y también los pads y efectos de sintetizador (89 a 104).

Gana el grupo con más puntaje. De esa pista toma la línea superior: si hay acordes, se queda con la nota más aguda de cada ataque.

Si acierta, no hay nada que hacer. Si no, **elige la pista a mano**: en la fila de la canción hay un selector con cada pista (su canal, sus notas y su registro) y una opción «todas las pistas juntas» (ver más abajo).

## Lo que funciona bien

- **Una pista de melodía limpia y con nombre.** Es lo que más ayuda: renombra la pista a *Melody* en cualquier editor y listo.
- **Música tonal con una tonalidad clara.** Canciones populares, tradicionales, clásicas y de videojuegos.
- **Un tempo y un compás que no cambian** (o que cambian pocas veces). El análisis usa el primer tempo y el primer compás del archivo.
- **Archivos de formato 1** con las pistas separadas. Los de formato 0 (todo en una pista) también sirven si cada instrumento tiene su canal.
- **Con acompañamiento.** Los acordes se detectan del conjunto de las notas, así que un MIDI con solo la melodía queda sin armonía propia (el generador la inventa).

## Lo que funciona peor

- **Melodías repartidas entre varias pistas** (por ejemplo, una frase en el violín y la siguiente en la flauta). Con la selección automática solo se toma una pista; elige «todas las pistas juntas» en el selector de la canción.
- **Melodía en una pista grave** (un cello cantando solo, por ejemplo): la automática puede tomarla por bajo y elegir otra; elígela a mano en el selector.
- **MIDI de piano a dos manos** sin pistas separadas: la línea superior suele ser la melodía, pero con mucha ornamentación o notas del acompañamiento en el registro agudo se cuela de más.
- **Música muy cromática o atonal, o con modulaciones constantes.** La tonalidad detectada será la que más pese en todo el archivo, y los grados de la melodía, aproximados.
- **Archivos de batería sola** o de efectos: no tienen notas tonales, y la biblioteca los marca como «sin melodía». Se pueden tocar «tal cual».
- **Arreglos muy largos** (más de 10 minutos): funcionan, pero las frases se cortan por silencios y notas largas, y salen muchas.

## Cómo revisar lo que entendió

En el panel de la biblioteca, cada canción muestra la tonalidad, el tempo, el compás y la cantidad de frases que se detectaron.

- **La tonalidad.** Si dice *do mayor* y la canción es en la menor, la melodía queda con los grados corridos (siguen sonando bien entre sí, pero cambian de color al citarla en otras tonalidades). Una buena señal es que coincida con lo que sabes de la canción.
- **El tempo y el compás.** Si el compás dice 4/4 y la canción va en 3/4, el MIDI tiene el compás mal escrito. Las citas salen igual (van por pulsos), pero la sección toma el compás equivocado.
- **Las frases.** Un número muy bajo (1 o 2 en una canción larga) quiere decir que la melodía casi no tiene silencios: se corta cada cuatro compases. Un número enorme, que la melodía tiene muchos silencios cortos.
- **«▶ tal cual».** Toca el MIDI completo con el mismo sintetizador. Si suena bien y la cita no, el problema está en el análisis y no en el archivo.

## Cómo corregir un MIDI

Lo más rápido es **elegir la pista en el selector de la fila de la canción**: no toca el archivo, se guarda en tu navegador y se puede deshacer. Las opciones son cada pista que tenga notas y, al final, «todas las pistas juntas» (la línea superior del conjunto), pensada para una melodía que pasa de un instrumento a otro. Con eso se arreglan los tres casos de arriba: la melodía en un registro grave, la repartida entre varias pistas y el arpegio continuo que se toma por melodía.

Si prefieres arreglar el archivo en sí, cualquier editor de MIDI (MuseScore, Reaper, Cakewalk, MidiEditor, Anvil Studio…) sirve para:

1. **Renombrar la pista de melodía** a *Melody*.
2. **Separar la melodía** en su propia pista si venía mezclada con el acompañamiento.
3. **Escribir bien el compás y el tempo** al principio del archivo.
4. **Quitar las pistas que sobran** (pads, efectos), que además restarían puntaje a otras.

## Una cosa más

Esta aplicación es de uso personal: aprende de **tus** MIDI y te devuelve música basada en ellos. Si una canción tiene dueño, las citas que salgan de ella también llevan su música. Úsala en privado; no publiques ni distribuyas lo que se grabe o exporte a partir de material con derechos de otros.
