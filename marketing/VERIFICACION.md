# Qué respalda el producto, frase por frase

Las notas de [`frases.md`](frases.md) piden verificar contra el producto toda frase que prometa algo. Este documento deja el resultado de esa revisión, para que quien decida qué publicar no tenga que repetirla.

**Fecha y alcance.** 17 de octubre de 2026. Se miró este repositorio: el sitio de genealogía de `url-routing/`, la librería `defamatory-content-review/` y [`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md). **No se pudo mirar ningún producto que viva fuera de este repositorio**: si crear árboles, invitar o guardar existen en otro sistema, las filas «no hay respaldo aquí» pueden estar equivocadas. Quien conozca ese producto debe confirmarlas.

## Lo que hace hoy el sitio de genealogía de este repositorio

- Es de **solo lectura**: no tiene formularios de genealogía. Los datos entran por semillas e importaciones.
- No hay cuentas por persona ni invitaciones: hay un único propietario, identificado con un secreto (`OWNER_TOKEN`).
- Se pueden ver personas, árboles (ascendencia, descendencia, vínculos), sucesos, registros, lugares, apellidos (con su red y su dispersión), organizaciones y colecciones, y exportar una colección a GEDCOM.
- A quien no es el propietario: las **personas que podrían estar vivas** aparecen como «Persona viva» (sin nombre, fechas ni lugar), no salen en las búsquedas y sus sucesos y registros no existen; las **colecciones privadas** responden 404, y quien solo figura en colecciones privadas se muestra como «Persona privada». Es una deducción por fechas, con los límites que explica [`url-routing/docs/ACCESO.md`](../url-routing/docs/ACCESO.md).
- El filtro de insultos y acusaciones (`defamatory-content-review/`) **existe como librería, pero `url-routing` no la usa todavía**, porque no tiene dónde escribir texto.
- Faltan, según `PRIVACIDAD-Y-HONRA.md`: un botón para pedir el retiro de un contenido y un registro de quién publicó qué. Los términos de uso existen solo como **borrador** ([`TERMINOS-DE-USO.md`](../TERMINOS-DE-USO.md), 17 de octubre): falta que los revise un abogado, y sus secciones de retiro y registro prometen justamente lo que todavía no existe.

## Frase por frase

| Frase (en `frases.md`) | Qué respalda el repositorio | Estado |
|---|---|---|
| «Un espacio seguro donde cada nombre se escribe con dignidad.» | Sin formularios no se escribe nada; el filtro no está conectado; no hay retiro, y los términos de uso son un borrador sin revisión legal. «Seguro» es una promesa absoluta | **Apartada** (ya no está en `frases.md`). Se puede recuperar cuando el filtro esté conectado, exista el retiro y los términos estén publicados |
| «Cuidamos tus datos con respeto; tu legado es de tu familia.» y el pie de correo «Tus datos familiares son tuyos. Los tratamos con respeto y cuidado.» | Hay protección de personas vivas y de colecciones privadas, con límites. No hay borrado ni exportación para cada usuario, ni copias de seguridad probadas. «Son tuyos» sugiere un control que hoy no existe | **Sin decidir.** Siguen marcadas «Verificar contra el producto». Ver «Frases que sí se podrían afirmar» |
| «Tu historia familiar, tratada con respeto y cuidado.» | Es un valor, no una función; no afirma nada que se pueda contradecir | Se puede publicar |
| «Empieza tu árbol genealógico hoy.», «Empezar mi árbol», «Continuar mi árbol», «Descubrir mi historia» | El sitio de este repositorio no permite crear ni continuar un árbol | **No hay respaldo aquí** |
| «Invita a tu familia…», «Invitar a mi familia» | No hay invitaciones ni cuentas | **No hay respaldo aquí** |
| «Nosotros las guardamos», «Nosotros la guardamos», «Reúne a tu familia y recopila sus historias» | No se pueden aportar historias; las copias de seguridad no están probadas | **No hay respaldo aquí** |
| «Esa foto vieja del cajón es un tesoro. Compártela con tu familia.» | No hay subida de fotos. Es una invitación a compartir fuera del producto | Se puede publicar |
| «Encontramos nuevas pistas sobre tus antepasados», «Hay un familiar que aún no conoces», «Ver mis hallazgos» (condicionales) | No hay pistas, hallazgos ni coincidencias. Lo más cercano, `phone-directory/RecordLinker`, enlaza guías telefónicas y `PRIVACIDAD-Y-HONRA.md` lo señala como riesgo para la privacidad | **No hay respaldo aquí.** Solo valen con datos reales de esa persona |
| «Tu árbol puede tener pistas por descubrir», «Quizás hay un familiar que aún no conoces» | Mismo caso, pero están en la lista **no** condicional, contra lo que dice «Antes de usar». Van hedged («puede», «quizás») | **Decisión pendiente:** moverlas a condicionales o dejarlas |
| «Tu apellido tiene un origen. Descúbrelo hoy.» | La página de cada apellido muestra su origen (un código de país, por ejemplo «es») y, si la tiene, una descripción, además de su red y su dispersión por lugar de nacimiento. Los datos de demostración lo ilustran; los reales dependen de lo que se cargue | Respaldada en parte |
| «Llevas en ti a cientos de antepasados.» | Aritmética: 8 generaciones son 256 personas; es una afirmación general, no sobre su árbol | Se puede publicar |
| Lemas, eslóganes, manifiesto, legado, «llama a un familiar», hashtags | No afirman nada del producto | Se pueden publicar |

## Frases que sí se podrían afirmar

Estas están respaldadas hoy por este repositorio, con los límites de [`ACCESO.md`](../url-routing/docs/ACCESO.md). **No se añadieron a `frases.md`**: cada una habría que traducirla a los 15 idiomas y revisarla con alguien nativo, y la decisión de publicarlas es de quien las usa.

- «Las personas que podrían estar vivas no se muestran a quien visita tu árbol.» (es una deducción por fechas, no una certeza: no cubre a quien vive pero no tiene fechas)
- «Tus colecciones privadas solo las ves tú.»
- «Puedes llevarte tu árbol: exporta a GEDCOM.» (hoy, a partir de una colección que el propietario puede abrir)

Lo que **no** se puede decir con lo que hay: «tus datos están seguros», «nadie puede ver…» sin matices, «puedes borrar tu información cuando quieras» (no hay borrado) ni nada sobre moderación mientras el filtro no esté conectado.
