# Mantenimiento: qué comando usar y en qué orden

Los scripts de `bin/` están descritos uno por uno en la tabla del [`README.md`](../README.md). Este documento dice **cuándo** tocar cada uno, en qué orden y qué no mezclar. Los que usan una base de datos la leen de `DB_DSN_<SITIO>` (o de `.env`), igual que la aplicación web. Los que borran o mueven datos de personas (`fusionar.php`, `persona.php`) muestran primero qué harían y solo lo hacen con `--confirmar`.

## Lo que corre solo (cron)

Se instala una vez, en los pasos 8 (pedidos de retiro y solicitudes de datos) y 11 (copias) de [`DESPLIEGUE.md`](./DESPLIEGUE.md):

| Cada | Comando | Para qué |
|---|---|---|
| Día | `php bin/pedidos-retiro.php` | Recordatorio de lo que sigue esperando al propietario, con el plazo (`RETIRO_PLAZO_HORAS`) marcado. No imprime nada si no hay nada. |
| Media hora | `php bin/pedidos-retiro.php --ocultar --solo-ocultados` | El ocultamiento automático (activado): oculta lo que pasó del plazo y manda correo solo en la corrida que ocultó algo. Sin `--solo-ocultados` repetiría el mismo listado cada media hora. |
| Semana (opcional) | `php bin/pedidos-retiro.php --depurar` | Borra el correo y el texto de los pedidos resueltos hace más de `RETIRO_CONSERVAR_MESES` (12 por omisión). |
| Día | `php bin/solicitudes.php` | Recordatorio de las solicitudes de datos que siguen esperando (número, persona, derecho pedido y cuánto llevan). No imprime nada si no hay. |
| 10 minutos (opcional) | `php bin/solicitudes.php --solo-nuevas` | Avisa **una sola vez** de cada solicitud en cuanto llega, sin esperar al recordatorio de la mañana. |
| Semana (opcional) | `php bin/solicitudes.php --depurar` | Borra el correo y el texto de las solicitudes cerradas hace más de `SOLICITUD_CONSERVAR_MESES` (12 por omisión) y las entregas con clave vencidas hace tanto. |
| Día | `php bin/copias.php --destino=… [--enviar=…]` | La copia de las dos bases, con rotación a 30 días. **No corre sola hasta que programes su cron.** |
| Día, en **otro equipo** | `php bin/copias.php --destino=… --vigilar` | Avisa si algún sitio no tiene copia reciente (más de 2 días) o la tiene vacía. Un servidor muerto no puede avisar de que dejó de copiar. |

El código de salida de `pedidos-retiro.php` es 0 si nada está vencido, 1 si hay un pedido vencido sin ocultar y 2 si falló el uso o la base. Cron manda por correo lo que el script imprime: por eso ni `pedidos-retiro.php` ni `solicitudes.php` imprimen jamás quién pidió ni qué escribió (eso solo lo muestra `solicitudes.php --ver=N`, en la terminal del propietario). `copias.php` sale con 0 sin imprimir nada si todo salió bien, con 1 si una copia, un envío o una comprobación falló, y con 2 si se usó mal.

## Cada semana, a mano

- `php bin/urls-informe.php` — qué **formas** de URL dieron 404 o 301 (nunca la URL). Un 404 que se repite es casi siempre un enlace roto, de aquí o de otro sitio. Un 301 de una redirección vieja que ya no recibe visitas se puede quitar de `config/routes/`.

## Cuando hay un duplicado

1. `php bin/fusionar.php <duplicado> <vigente>` — **sin** `--confirmar` solo muestra qué movería (hijos, vínculos, sucesos).
2. Revisa la lista. Si está bien, repite con `--confirmar`.
3. Para un apellido duplicado, lo mismo con `grupo`: `php bin/fusionar.php grupo <duplicado> <vigente>`.

El id del duplicado queda como alias: su URL responde 301 a la que queda, así que ningún enlace viejo se rompe. 
## Cuando alguien pide ver, corregir o borrar sus datos

El recorrido completo, con lo que cubre y lo que no, está en [`SOLICITUDES.md`](./SOLICITUDES.md); las herramientas, en [`ACCESO.md`](./ACCESO.md#acceso-y-supresión-de-una-persona). **Recibir una solicitud no entrega, cambia ni borra nada**: espera hasta que el propietario la atiende. El orden:

1. **Ver qué espera.** `php bin/solicitudes.php`, o el panel del propietario `/0/4/`. `php bin/solicitudes.php --ver=N` muestra una completa, con el correo y el texto de quien pidió: solo en tu terminal.
2. **Comprobar que quien pide es la persona** (o un familiar, si ya falleció) **antes** de mandar o cambiar nada. Cómo se comprueba está en la sección «Comprobar quién pide» de [`SOLICITUDES.md`](./SOLICITUDES.md), que marca con ⚖️ lo que la ley todavía puede exigir.
3. **Acceso.** `php bin/persona.php acceso <id>` imprime todo lo que hay de esa persona, en JSON, **incluido lo que el sitio oculta a un visitante**: por eso no viaja por correo. Para contestar:
   - `php bin/persona.php resumen <id>` da un **borrador legible** (lo que ve un visitante y cuántos datos hay de lo demás), que se ajusta a mano; o
   - `php bin/persona.php entrega <id> [--dias=7]` hace un **enlace y una clave** para leer lo guardado, parte oculta incluida. Se imprimen una sola vez y **deben ir por dos medios distintos**; solo se guardan sus hashes. `entrega <id> --revocar` termina las que sigan vigentes. Desde el panel (`/0/4/`) también se crea.
4. **Rectificar.** `php bin/persona.php rectificar <id> <tabla.columna>=<valor>` **sin** `--confirmar` solo dice qué haría y si cambia lo que ve un visitante; con `--confirmar` lo hace. El panel tiene «Simular una corrección». Para un cambio hecho a mano, `rectificacion <id> <tabla.columna>` lo deja registrado.
5. **Suprimir.** `php bin/persona.php suprimir <id> [--pedido=N]` — **sin** `--confirmar` muestra qué quitaría; con él lo hace. **No se puede deshacer sin una copia de seguridad.** `--pedido=N` es el pedido de retiro que lo pidió: queda como `retirado`, con una nota.
6. **Cerrar la solicitud**, con una nota que diga qué se hizo **sin copiar los datos de la persona**: `php bin/solicitudes.php --atender=N [--nota=TEXTO]`, o `--rechazar=N` si no se pudo comprobar quién pedía. También con los botones del panel.
7. Tras **restaurar una copia de seguridad**, corre `php bin/persona.php reaplicar` (y luego con `--confirmar`): vuelve a quitar a quien la copia trajo de vuelta. Sin esto, una restauración devuelve a alguien que pidió irse.

## Copias de seguridad

Una copia trae **todo lo que el sitio oculta** (personas que pueden estar vivas, pedidos de retiro, el correo de quienes escribieron): trátala como el dato más delicado que tienes. El detalle está en el paso 11 de [`DESPLIEGUE.md`](./DESPLIEGUE.md#11-copias-de-seguridad).

- **Hacer la copia:** `php bin/copias.php --destino=/var/backups/url-routing --enviar='rsync -a {archivo} usuario@host:/copias/'`. `--enviar` la saca del servidor (una copia que vive solo en el servidor no sirve si el servidor se pierde). Cada copia se comprueba antes de tomar su nombre, y las de más de 30 días se borran **solo después** de que la nueva salió bien.
- **Cifrarla (opcional):** `--cifrar-para=age1…` con la clave **pública** de un par generado en otro equipo. El servidor no puede descifrar sus propias copias, y a cambio **sin la clave privada no hay manera de restaurarlas**: guárdala fuera del servidor, en dos lugares.
- **Vigilar que lleguen:** `--vigilar`, en otro cron y mejor en el equipo que las recibe.
- **Verificar que se pueden restaurar:** `php bin/copias.php --verificar=genealogy-AAAA-MM-DD.dump.age --identidad=clave.txt` restaura una copia de verdad (en una base temporal que borra) y cuenta las filas de cada tabla. Una copia que «pasó la comprobación» al hacerse no prueba que se pueda restaurar; córrelo de vez en cuando, **donde está la clave**.
- **Después de restaurar**, el paso 7 de la sección anterior: `persona.php reaplicar`.

## Órdenes del sitio POS

`php bin/orden.php <id> <estado> [--detalle="Texto"] [--guia=NUMERO]` avanza el estado y lo anota en el historial que ve el cliente. Cualquier estado puede seguir a cualquier otro, y **no devuelve el stock** de una orden cancelada: eso se corrige aparte.

## Ids

`php bin/id.php completar <número> [<ancho>]` agrega el dígito de verificación a un id nuevo y `php bin/id.php verificar <id>` comprueba uno (sale 0 si está bien). Solo importa si `IDS_DIGITO_VERIFICADOR=true`; el script funciona igual con el interruptor apagado, porque es la herramienta, no el interruptor.

## Al desplegar una versión nueva

El paso a paso está en el paso 9 de [`DESPLIEGUE.md`](./DESPLIEGUE.md): `git pull`, `composer install --no-dev --optimize-autoloader`, las migraciones de los dos sitios como `www-data` (sin `--seed`: los datos de demostración son solo para desarrollo), recargar PHP-FPM y comprobar que `/ready` dé `"migrations":"applied"`.

Si el cambio toca `config/routes/`, regenera la lista antes de empujar: `php bin/rutas.php > docs/RUTAS.md` (una prueba falla si no coincide). `deploy/smoke-test.sh` repite la comprobación completa contra un servidor real.

## Lo que no hay que hacer

- No edites `docs/RUTAS.md` a mano: se genera.
- No pongas nada en `public/` que no deba ser público: es lo único que el servidor web sirve.
- No mezcles un `DB_DSN` compartido: cada sitio tiene el suyo (ver [`DATABASE.md`](./DATABASE.md)).
- No pongas la URL, el texto de un pedido ni el correo de nadie en un registro, un correo de cron ni un informe.
