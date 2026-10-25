# Qué pasa cuando alguien reclama

Una página de recorrido, para quien recibe un reclamo y para el abogado que lo
asesora. Cuenta **lo que hace hoy el sitio**, paso a paso, y qué tiene que hacer
una persona en cada uno. El porqué está en [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md);
lo que se promete al público, en [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md) (§5 y §6);
las preguntas abiertas para el abogado, en [`PREGUNTAS-PARA-ABOGADO.md`](PREGUNTAS-PARA-ABOGADO.md).
Lo que está marcado con **⚖️** no está decidido.

## El recorrido

| # | Qué pasa | Quién | Dónde |
|---|---|---|---|
| 1 | **Llega el pedido.** Cualquiera, sin cuenta, abre «Pedir el retiro de esta página» (al pie de toda página de persona, suceso, registro, colección, apellido u organización) y deja un motivo, qué hay que retirar y un correo. **No se oculta nada todavía**: si bastara con pedirlo, cualquiera podría bajar cualquier página. El formulario tiene frenos que no guardan quién es el visitante: 3 pedidos por visitante y por hora, 10 pendientes por página (responde 429) y un campo invisible que descarta a los bots sin avisarles. | La persona que reclama | `/r/{id}/` |
| 2 | **Alguien se entera.** El pedido espera en la cola del propietario. Un script de cron (`bin/pedidos-retiro.php`) manda por correo cuántos esperan y cuánto llevan, y marca el que pasó de 24 horas; en la cola, el texto de cada pedido pasa por el filtro de difamación y lo que encuentra («insulto», «acusación de delito»…) se muestra como aviso, sin bloquear nada (quien se queja de una acusación suele repetirla); **no dice quién pidió ni qué escribió**, porque ese correo viaja sin cifrar. Sin cron, nadie avisa: hay que mirar la cola. | Quien administra el sitio | `/0/3/`, cron |
| 3 | **Se decide**, con una nota que queda escrita: *ocultar mientras se revisa*, *retirar* o *rechazar*. Ocultar solo, al vencer el plazo, es una opción que hay que activar (`--ocultar`) y **⚖️ está por decidir si se promete**. | Quien administra el sitio | `/0/3/` |
| 4 | **Qué cambia a la vista.** La página oculta o retirada responde «Página retirada» a los visitantes, con todas sus acciones, y sale de la búsqueda y del mapa del sitio. Si es una persona, se llama «Persona privada» en las demás páginas (familia, árboles, listados, GEDCOM) y los sucesos y registros en los que participa dejan de verse. El propietario lo sigue viendo todo. Rechazar el pedido la vuelve a mostrar. | Automático | todo el sitio |
| 5 | **Se responde** a quien pidió, por correo, con la decisión y las razones. El sitio **no escribe solo**: lo hace una persona, desde el correo que se dejó, con [textos tipo en 16 idiomas](url-routing/docs/respuestas-tipo/README.md) (borrador sin revisión nativa). Plazo prometido: **[10] días hábiles ⚖️**. | Quien administra el sitio | correo |
| 6 | **Queda el registro.** Cada pedido guarda la página, el motivo, lo escrito, el correo y cada paso con fecha y nota (recibido, oculto, retirado, rechazado); solo se agregan filas, nunca se cambian. El log del sitio anota el número de pedido y la página, no quién pidió. Plazo de conservación: **[PLAZO] ⚖️**. | Automático | base de datos |
| 7 | **Después**, si corresponde, se corrigen o se borran los datos. **Retirar no es borrar**: la página queda oculta y los datos, guardados. Corregir o borrar se hace a mano en la base de datos. **⚖️** ¿Basta retirar para una solicitud de supresión? (pregunta 31). | Quien administra el sitio | base de datos |

## Si el reclamo es por algo que se escribió en el panel

Las notas del panel de administración pasan por el filtro de difamación antes de
guardarse: una con insultos o amenazas no se guarda, y una con una acusación o un
dato sensible o personal se guarda marcada para releerla. Cada guardado queda en un
registro con quién, cuándo y qué decidió el filtro (sin el texto: su largo y una
huella). Esas notas no las ve ningún visitante. Ver [`sistema-nuevo/README.md`](sistema-nuevo/README.md).

## Lo que el sitio no hace hoy

- **No avisa a quien pidió** que su pedido llegó, ni cuando se resolvió (solo se muestra un mensaje al enviarlo).
- **No borra datos** al retirar una página, ni borra los pedidos viejos con el correo de quien los hizo.
- **No verifica quién pide**: no sabe si quien dice ser el familiar de un fallecido lo es. **⚖️** Grado de parentesco y cómo acreditarlo (pregunta 16).
- **El sitio de genealogía no tiene dónde escribir**: no hay texto de usuarios que filtrar fuera de las notas del panel.

## Dónde está cada cosa

| Qué | Archivo |
|---|---|
| El formulario, la cola, el 410 y el registro | [`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md) («Pedidos de retiro») |
| Poner el cron del aviso | [`url-routing/docs/DESPLIEGUE.md`](url-routing/docs/DESPLIEGUE.md) (paso 8) |
| La tabla de pedidos y su historial | [`url-routing/database/genealogy/migrations/005_pedidos_retiro.sql`](url-routing/database/genealogy/migrations/005_pedidos_retiro.sql) |
| El filtro, lo que cubre y lo que no | [`defamatory-content-review/docs/RIESGOS.md`](defamatory-content-review/docs/RIESGOS.md) |
| El caso que dio origen a todo | [`defamatory-content-review/docs/ORIGEN.md`](defamatory-content-review/docs/ORIGEN.md) |
