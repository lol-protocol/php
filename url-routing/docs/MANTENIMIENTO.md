# Mantenimiento: qué comando usar y en qué orden

Los scripts de `bin/` están descritos uno por uno en la tabla del [`README.md`](../README.md). Este documento dice **cuándo** tocar cada uno, en qué orden y qué no mezclar. Los que usan una base de datos la leen de `DB_DSN_<SITIO>` (o de `.env`), igual que la aplicación web. Los que borran o mueven datos de personas (`fusionar.php`, `persona.php`) muestran primero qué harían y solo lo hacen con `--confirmar`.

## Lo que corre solo (cron)

Se instala una vez, en el paso 8 de [`DESPLIEGUE.md`](./DESPLIEGUE.md):

| Cada | Comando | Para qué |
|---|---|---|
| Día | `php bin/pedidos-retiro.php` | Recordatorio de lo que sigue esperando al propietario, con el plazo (`RETIRO_PLAZO_HORAS`) marcado. No imprime nada si no hay nada. |
| Media hora | `php bin/pedidos-retiro.php --ocultar --solo-ocultados` | El ocultamiento automático (activado): oculta lo que pasó del plazo y manda correo solo en la corrida que ocultó algo. Sin `--solo-ocultados` repetiría el mismo listado cada media hora. |
| Semana (opcional) | `php bin/pedidos-retiro.php --depurar` | Borra el correo y el texto de los pedidos resueltos hace más de `RETIRO_CONSERVAR_MESES` (12 por omisión). |

El código de salida de `pedidos-retiro.php` es 0 si nada está vencido, 1 si hay un pedido vencido sin ocultar y 2 si falló el uso o la base. Cron manda por correo lo que el script imprime: por eso nunca imprime quién pidió ni qué escribió.

## Cada semana, a mano

- `php bin/urls-informe.php` — qué **formas** de URL dieron 404 o 301 (nunca la URL). Un 404 que se repite es casi siempre un enlace roto, de aquí o de otro sitio. Un 301 de una redirección vieja que ya no recibe visitas se puede quitar de `config/routes/`.

## Cuando hay un duplicado

1. `php bin/fusionar.php <duplicado> <vigente>` — **sin** `--confirmar` solo muestra qué movería (hijos, vínculos, sucesos).
2. Revisa la lista. Si está bien, repite con `--confirmar`.
3. Para un apellido duplicado, lo mismo con `grupo`: `php bin/fusionar.php grupo <duplicado> <vigente>`.

El id del duplicado queda como alias: su URL responde 301 a la que queda, así que ningún enlace viejo se rompe. 
## Cuando alguien pide ver o borrar sus datos

El procedimiento completo, con lo que cubre y lo que no, está en [`ACCESO.md`](./ACCESO.md#acceso-y-supresión-de-una-persona). El orden:

1. `php bin/persona.php acceso <id>` — imprime todo lo que hay de esa persona, en JSON. Es la respuesta a «¿qué tienen de mí?».
2. `php bin/persona.php suprimir <id> --pedido=N` — **sin** `--confirmar` muestra qué quitaría. `--pedido=N` es el pedido de retiro que lo pidió: queda como `retirado`, con una nota.
3. Con `--confirmar` lo hace. **No se puede deshacer sin una copia de seguridad.**
4. Tras **restaurar una copia de seguridad**, corre `php bin/persona.php reaplicar` (y luego con `--confirmar`): vuelve a quitar a quien la copia trajo de vuelta. Sin esto, una restauración devuelve a alguien que pidió irse.

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
