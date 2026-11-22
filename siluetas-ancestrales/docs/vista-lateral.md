# Plan: vista lateral (de perfil)

Estado: **propuesta, sin código**. Es la única vista que falta (hoy hay de frente y de espalda). Este documento dice qué hay que construir, en qué orden, cuánto es y qué hay que decidir antes de empezar. Las líneas son **estimaciones** hechas a partir del tamaño del código actual (`src/cuerpo.js` 496 líneas, `src/cabeza.js` 407, `src/svg.js` 285, `src/prendas/` unas 900 en 17 archivos).

## Por qué no sale gratis

La **espalda** fue barata (`src/espalda.js` tiene 30 líneas) porque la silueta de un cuerpo visto por detrás es la del frente reflejada: bastó reflejar la figura y quitar lo que solo se ve por delante. De perfil **no hay nada que reflejar**: todo el modelo está pensado en anchos (qué tan ancho es el cuerpo a cada altura) y la vista lateral necesita **profundidades** (qué tan grueso es de adelante hacia atrás) y una dirección hacia la que mira.

| Pieza | Hoy (de frente) | De perfil |
|---|---|---|
| Cuerpo | anchos por altura (`w`), simétrico | profundidad por altura, curva de la columna, pecho, vientre y glúteos; ambos brazos y piernas se superponen y hay que decidir cuál va delante |
| Cabeza | óvalo con mandíbula; rasgos opcionales | cráneo, frente, nariz, labios, mentón y cuello vistos de lado; la oreja en el centro |
| Pelo, barba, tocado | 26 peinados, 8 barbas y 44 tocados dibujados de frente | los mismos 78 elementos con su perfil: flequillo, melena que cae por la espalda, barba por delante, ala del sombrero |
| Prendas | 16 plantillas ancladas a los anchos del cuerpo | las 16 ancladas a la profundidad: dónde cae el escote, la manga vista de lado, el vuelo de una falda, un manto que cuelga |
| Pie | de frente, apenas se ve el largo | de perfil el pie y el calzado muestran todo su largo: es la parte más visible de la ropa |
| Pose y saludo | brazo levantado a un lado | el brazo que saluda sale hacia adelante; el otro se esconde detrás del cuerpo |

## Qué se reutiliza

- La **estructura de capas** de `src/svg.js` (piel, ropa por ranura, pelo, sombras con `<use>`), el degradado con la luz desde la izquierda, la deformación por pose y el prefijo de ids por figura.
- El **catálogo** de prendas, atuendos, colores y etnias, sin cambios: la vista no cambia *qué* viste una persona sino *cómo se ve*.
- El patrón que ya probó la espalda: una opción `vista`, validación en `src/validacion.js`, reflejo con `transform`, un módulo propio (`src/lateral.js`) que adapta las prendas, y pruebas que recorren las 485 prendas.
- Las **medidas por edad, sexo y complexión** de `cuerpo.js` (altura, anchos): la profundidad se deriva de ellas con una razón profundidad/ancho por zona.

## Etapas

Cada etapa se puede fusionar sola. Mientras una prenda no tenga su perfil, **se dibuja como un tubo simple de su largo y su color y `persona.avisos` lo dice**, así la vista es usable desde la etapa 1 y mejora PR a PR.

| # | Etapa | Qué trae | Líneas (código + pruebas) | Criterio para darla por terminada |
|---|---|---|---|---|
| 0 | Decisiones y prueba de concepto | Resolver las preguntas de abajo y dibujar un cuerpo de perfil de una sola pieza (sin cabeza ni ropa) | 100 a 200 | Se ve bien en tres edades y dos complexiones |
| 1 | Cuerpo y cabeza de perfil, sin ropa | Tablas de profundidad, columna, brazos y piernas superpuestos, pie, cabeza calva con nariz, labios y mentón, `vista: 'perfil'` en la validación, el SVG y la CLI | 700 a 900 + 250 | Cualquier edad, sexo y complexión se dibuja sin NaN y dentro del lienzo; la figura reflejada (mira a la izquierda o a la derecha) funciona |
| 2 | Ropa básica | Túnica, pantalón, falda y calzado de perfil, más el relleno genérico para las demás | 500 a 700 + 250 | Todo atuendo del catálogo se dibuja en perfil (las 485 prendas, 3 cuerpos, 2 sexos), con aviso donde se simplificó |
| 3 | Pelo, barba y tocado | Perfil de los 26 peinados, 8 barbas y 44 tocados, agrupados en unas pocas formas base con variantes | 500 a 700 + 150 | Cada peinado, barba y tocado tiene perfil o cae en una forma base reconocible |
| 4 | Resto de plantillas | Capa, poncho, drapeado, chaqueta, delantal, faja, bandolera, chal, cuello, accesorio, mono y taparrabo | 500 a 600 + 150 | Ninguna prenda queda como relleno genérico |
| 5 | Pose, integración y documentación | Saludo hacia adelante, brazo libre, `generarFamilia` y galería con la vista, ejemplos, README y guía | 250 a 350 + 100 | Galería, CLI, árbol y ejemplos al día |

**Total estimado: 2.600 a 3.600 líneas de código y unas 900 de pruebas**, repartidas en unos 6 PR. Es el mismo orden de magnitud que todo `src/` hoy.

## Decisiones que hay que tomar antes de empezar

1. **¿Dos direcciones o una?** Mirar a la izquierda y a la derecha es un reflejo; la complicación es qué lado ve el espectador (qué brazo queda delante). Propuesta: una sola dirección dibujada y la otra reflejada, y `vista: 'perfil-izquierda' | 'perfil-derecha'`.
2. **¿Qué brazo y qué pierna quedan delante?** Propuesta: la pierna que no carga el peso y el brazo del saludo.
3. **Rostro de perfil.** Es lo más delicado: la nariz, los labios y el mentón dependen de rasgos que hoy el catálogo no describe y que **no deben deducirse de la etnia** (sería estereotipar). Propuesta: un perfil neutro y levemente variable con la semilla, sin relación con la etnia.
4. **Profundidades.** No hay una tabla antropométrica en el proyecto. Propuesta: razones aproximadas por zona (pecho, cintura, cadera, muslo…), marcadas como aproximadas igual que el resto de las proporciones, y revisadas a ojo con la galería.
5. **¿Y el tres cuartos?** Queda fuera de este plan: exigiría reproyectar todo el modelo.

## Riesgos

- **Calidad del perfil de la cara.** Sin contornos y con degradados, un perfil mal resuelto se ve peor que el óvalo de frente. Se mitiga con la etapa 0 (probar antes de comprometerse) y con rasgos mínimos.
- **Ropa que cuelga** (mantos, ponchos, faldas largas): de perfil se ve cómo cae por delante y por detrás, y el modelo actual solo conoce el ancho.
- **Superposición de brazos y piernas**: cuál va delante cambia con cada pose; hay que ordenar las capas.
- **Tamaño**: 78 perfiles de pelo, barba y tocado son mucho trabajo manual; la agrupación en formas base es lo que lo hace abordable.
- **Simetría de detalles**: botones, cierres y bolsillos que se ven de frente no existen o cambian de lado de perfil (como en la espalda, hay que decidir qué se quita).

## Cómo se prueba

- Todas las prendas, para tres cuerpos (niño, adulto, anciano corpulento) y los dos sexos, sin NaN y dentro del lienzo (la misma comprobación que ya existe para la espalda y las poses).
- Que la figura de perfil mide lo mismo de alto que de frente.
- Que los ids de una figura de frente, de espalda y de perfil no se repiten en una misma página.
- Que `persona.avisos` nombra toda prenda dibujada con el relleno genérico.
- Miradas a ojo en Chromium para cada etapa (el criterio de «se ve bien» no se puede automatizar).

## Qué no incluye

Sentados, caminando o en movimiento, perspectiva, rotaciones intermedias y animación. Tampoco cambia el catálogo ni las estimaciones por etnia.
