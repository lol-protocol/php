# Interruptores

Un interruptor por función, agrupados en dos plataformas: **A) Genealogía** y **B) Librería y etiquetado POS**. Un panel interno permite encender y apagar cada una. Es, en esencia, una mesa de mezclas: cada canal es una función, y algunos canales necesitan que otros estén encendidos.

- [`FUNCIONES.md`](FUNCIONES.md): el listado de las 51 funciones, con su carpeta, su tipo, su valor por defecto y sus dependencias. Se genera.
- [`catalogo.json`](catalogo.json): la fuente de verdad. Lo que está aquí es lo que existe en el panel.
- `src/`: la clase `Interruptores` (leer el estado, encender, apagar, validar dependencias).
- `panel/index.php`: el panel interno, con un botón por función.
- `bin/generar.php`: genera `FUNCIONES.md` (`--comprobar` falla si no está al día).
- `pruebas/ejecutar.php`: las pruebas.

## Probar

```bash
php pruebas/ejecutar.php            # 28 pruebas
php bin/generar.php --comprobar     # FUNCIONES.md al día
```

No necesita Composer ni base de datos. El estado se guarda en `var/estado.json`, que está fuera de git (`var/.gitignore`).

## El panel

El panel **no se publica**. No pongas `panel/` detrás de un servidor público: que lo sirva solo una red interna, o un VPN, o un servidor con autenticación delante.

```bash
INTERRUPTORES_CLAVE='una-clave-larga' php -S 127.0.0.1:8080 -t panel
```

Sin `INTERRUPTORES_CLAVE` el panel no arranca. La sesión usa cookies `HttpOnly` y `SameSite=Strict`; cada acción lleva un token CSRF; la clave se compara con `hash_equals`; un intento fallido espera 0,4 s. Es una sola clave compartida: ver «Lo que falta».

## Reglas de dependencia

Cada función puede necesitar otras (`depende_de`):

- **No se enciende** una función si alguna de las que necesita está apagada. El panel muestra cuáles.
- **No se apaga** una función mientras una encendida la necesite. El panel muestra quiénes la necesitan.

Para apagar una cadena completa hay que apagar primero lo de arriba: por ejemplo, para apagar `gen.filtro-textos` hay que apagar antes `gen.retiros`, `gen.bak-notas`, `pos.mb-v-editor` y `pos.mb-h-propios`, y a su vez `pos.mb-h-app` antes de `pos.mb-h-propios`. La prueba `apagarEnCascada()` muestra el orden.

Las dependencias del catálogo son solo las que el propio interruptor debe proteger. Las dependencias de biblioteca (por ejemplo, que `defamatory-content-review/` esté en el `composer.json` de `phone-directory/`) no son interruptores: van en el código.

## Añadir una función

1. Agrega su entrada en `catalogo.json`: `id` único (`plataforma.nombre`), `plataforma`, `nombre`, `carpeta` (que debe existir en el repo, salvo que tenga `"fuera_de_master": true`), `tipo`, `descripcion`, `depende_de` y `por_defecto`.
2. Corre `php bin/generar.php` para regenerar `FUNCIONES.md`.
3. Corre `php pruebas/ejecutar.php`.

## Qué hace hoy y qué no

**Hace hoy:** guarda el estado, valida las dependencias, y el panel lo muestra y lo cambia.

**No hace todavía:** nadie lee el estado. Ninguno de los proyectos consulta `Interruptores::activo()`, así que apagar un canal en el panel **no apaga nada** en el sitio. Eso es el siguiente paso, proyecto por proyecto, cada uno en su PR (la regla del repo es un PR por carpeta).

## Lo que falta

Ver la lista completa de ideas en la conversación; estas son las que hacen falta antes de usar el panel en serio:

- **Quién lee el estado.** Un punto de integración en cada proyecto (por ejemplo, un `if (Interruptores::activo('gen.bak-notas'))`) y decidir qué pasa con una función apagada: ocultarla, responder «no disponible» o redirigir.
- **Registro de cambios.** Quién apagó qué y cuándo. Hoy el estado solo guarda el valor actual.
- **Usuarios y permisos** en vez de una clave compartida.
- **Decisiones de la persona:** qué apaga el «filtro de textos» en la práctica. Si se apaga, ¿los pedidos de retiro se aceptan sin revisión, o se rechazan? Eso es una decisión de privacidad y honra (ver `PRIVACIDAD-Y-HONRA.md`), no de código.
- **Por confirmar:** a qué plataforma sirven `cobros-ingresos-funnels`, `privacy-laws`, `copyright-laws`, `document-formats` y `Listado-paises`. Están como «transversal» hasta que alguien lo diga.
- **Dos carpetas que dependen de un PR abierto:** `chat-con-libros` (PR #603) no está en `master`, y `pos.colores-revision` depende de la decisión del PR #595.

## Convenciones

- Documentación en español, como el resto del repo.
- Sin dependencias externas: PHP 8.1 o más.
