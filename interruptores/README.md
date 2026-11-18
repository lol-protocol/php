# Interruptores

Un interruptor por función y, cuando la función tiene variantes, uno por cada variante. Están agrupados en dos plataformas: **A) Genealogía** y **B) Librería y etiquetado POS**. Un panel interno permite encenderlos y apagarlos.

Es una mesa de mezclas: cada **canal** es una función, y cada canal puede tener **sub-canales** (opciones). Algunos canales dependen de otros.

| | Funciones | Opciones | Interruptores |
|---|---|---|---|
| A) Genealogía | 32 | 140 | 172 |
| B) Librería y etiquetado POS | 20 | 63 | 83 |
| **Total** | **52** | **203** | **255** |

Cifras de `FUNCIONES.md`, que es el listado completo: cada función, sus opciones, su origen y sus dependencias.

## Vocabulario

- **Función**: un canal. Tiene su propio interruptor. Ejemplo: `gen.vistas` (Modos de visualización del árbol).
- **Opción**: un sub-canal de una función. Ejemplo: `gen.vistas:abanico`. Su interruptor es independiente del de su función.
- **Referencia (ref)**: el id de una función (`gen.vistas`) o de una opción (`gen.vistas:abanico`). Es lo que usan el código, el panel y el estado.

Estado que usa el sitio:

- Una **función** está encendida si su interruptor lo está.
- Una **opción** está encendida solo si su función también lo está **y** su propio interruptor lo está. Apagar «Perritos y gatitos» apaga los dos, pero conserva lo que cada uno tenía.

## Ejemplos del catálogo

- **Modos de visualización** (`gen.vistas`): 14 opciones (circular, abanico, árbol, pedigrí…). Mínimo 1: no puede quedar el árbol sin ninguna forma de dibujarse.
- **Medios de pago** (`gen.pagos`): transferencia, tarjeta y efectivo. Mínimo 1.
- **Perritos y gatitos** (`gen.mascotas`): dos interruptores independientes.
- **Pedidos de retiro** (`gen.retiros`): depende de la opción `gen.filtro:textos`. No se puede encender mientras la opción de textos del filtro esté apagada.

## Probar

```bash
php pruebas/ejecutar.php            # 69 pruebas: catálogo, dependencias, mínimos, estado, registro y usuarios
php pruebas/panel.php               # 23 pruebas: el panel en vivo, con php -S
php bin/generar.php --comprobar     # FUNCIONES.md al día
```

No necesita Composer ni base de datos. El estado se guarda en `var/estado.json`, fuera de git (`var/.gitignore`).

## Estructura

- [`catalogo.json`](catalogo.json): la fuente de verdad. Lo que está aquí es lo que existe en el panel.
- [`FUNCIONES.md`](FUNCIONES.md): listado generado desde el catálogo. No se edita a mano.
- `src/Interruptores.php`: la clase. Lee el estado, enciende y apaga, y aplica las reglas.
- `src/Excepciones.php`: `UnknownInterruptor` (la ref no existe) y `DependenciaRota` (el cambio dejaría algo roto).
- `src/Usuarios.php`: usuarios del panel, con contraseña hasheada, rol y bloqueo tras fallos.
- `panel/index.php`: el panel interno.
- `bin/generar.php`: genera `FUNCIONES.md`.
- `bin/usuarios.php`: alta, baja, cambio de rol y de clave.
- `pruebas/ejecutar.php` y `pruebas/panel.php`: las pruebas.
- `var/`: estado (`estado.json`), registro de cambios (`cambios.jsonl`) y usuarios (`usuarios.json`). Fuera de git.

## Reglas

1. **No se enciende** una función si alguna de sus dependencias no está encendida. Las dependencias pueden ser funciones u opciones.
2. **No se apaga** una función ni una opción mientras una función encendida la necesite. Si una dependencia apunta a una función, también la protege cada una de sus opciones.
3. **Mínimo de opciones**: si la función tiene `min_opciones`, no puede quedar con menos opciones encendidas mientras esté encendida. No se puede apagar la última opción, ni encender la función sin ninguna.
4. Para apagar una cadena completa, hay que apagar primero lo que depende de ella. `apagarEnCascada()` en las pruebas muestra el orden.

## Usarlo desde un proyecto

```php
require_once __DIR__ . '/../interruptores/src/Interruptores.php';
use LolProtocol\Interruptores\Interruptores;

$int = new Interruptores(__DIR__ . '/../interruptores/catalogo.json',
                         __DIR__ . '/../interruptores/var/estado.json');

if ($int->activo('gen.vistas:abanico')) {
    // mostrar el modo abanico
}
```

El estado se lee del archivo en cada llamada. Es sencillo, y para un sitio con mucho tráfico conviene cachearlo.

**Todavía nadie lo usa.** Apagar un canal en el panel no apaga nada en el sitio. Enchufar cada proyecto es el siguiente paso, un PR por carpeta.

## Panel

El panel **no se publica**. Que lo sirva solo una red interna, o un VPN, o un servidor con autenticación delante.

```bash
php bin/usuarios.php crear ana editor     # pide la clave dos veces… y no la muestra al escribir
php bin/usuarios.php crear luis lector
php -S 127.0.0.1:8080 -t panel
```

Sin usuarios, el panel no entra y lo dice. Hay dos roles:

- **editor**: ve todo y enciende o apaga.
- **lector**: ve todo, pero no cambia nada. Los botones aparecen bloqueados, y si alguien fuerza el envío, el servidor lo rechaza igual.

Seguridad del acceso:

- La clave se guarda con `password_hash`; el archivo no contiene la clave en claro.
- Tras 5 claves incorrectas seguidas, el usuario queda bloqueado 15 minutos. Un nombre que no existe responde igual que uno que sí existe.
- Cada acción lleva un token CSRF; las cookies son `HttpOnly` y `SameSite=Strict`; la página envía `Content-Security-Policy` sin scripts.
- El rol se comprueba en el servidor, no solo en el formulario.

Otros comandos de `bin/usuarios.php`: `listar`, `rol <nombre> <rol>`, `clave <nombre>` y `borrar <nombre>`. No se puede dejar el sistema sin ningún editor. Para usar tubería (por ejemplo, desde un gestor de secretos): `printf '%s' "$CLAVE" | php bin/usuarios.php crear ana editor`.

**Registro de cambios.** Cada cambio queda en `var/cambios.jsonl` con la hora (UTC), quién lo hizo, el interruptor y el valor antes y después. El panel muestra los últimos 20. Los cambios se hacen con un bloqueo exclusivo sobre el estado: dos personas que cambian a la vez no se pisan ni se saltan una dependencia.
## Añadir una función o una opción

1. Edita `catalogo.json`. Una función lleva `id` (`plataforma.nombre`), `plataforma`, `nombre`, `tipo`, `origen`, `carpetas`, `descripcion`, `depende_de`, `por_defecto`, y opcionalmente `min_opciones` y `opciones`.
2. `origen`:
   - `repo`: su código está en una carpeta de este repositorio; las pruebas comprueban que la carpeta exista.
   - `fuera_del_repo`: vive en la aplicación principal, que no está aquí.
   - `pr_abierto`: su carpeta todavía está en un PR sin fusionar.
3. Corre `php bin/generar.php` y `php pruebas/ejecutar.php`.

Las pruebas rechazan un catálogo con ids repetidos, con `:` en un id, con una dependencia a algo que no existe o a sí mismo, con un mínimo mayor que el número de opciones, o con una plataforma que no existe.

## Lo que falta

- **Listado final.** El listado actual sale de lo que hay en el repositorio. Genealogía tiene aquí 32 funciones y no las ~40 que se mencionaron, y Biblioteca POS tiene 20. Hay que contrastarlo con la lista real de funciones y añadir las que falten.
- **Qué hace «Perritos y gatitos».** Está como dos interruptores, pero su efecto en el sitio no está definido.
- **Decisión de privacidad.** ¿Qué pasa con la protección de personas que podrían estar vivas al importar fuentes externas o directorios? El catálogo no las ata a ninguna función; eso es una decisión de la persona (ver `PRIVACIDAD-Y-HONRA.md`).
- **Quién lee el estado.** Un punto de integración en cada proyecto y una regla para lo que ve el usuario cuando algo está apagado: ocultarlo, mostrar «no disponible» o redirigir.
- **Caducidad de sesión y auditoría de accesos.** El panel anota los cambios, pero no los inicios de sesión ni los intentos fallidos fuera de los contadores de cada usuario.
- **Escala del registro.** `historial()` lee el archivo entero. Con millones de cambios habrá que rotarlo o pasarlo a una base de datos.
- **A confirmar de plataforma:** `document-formats` está fuera del interruptor; no se sabe si va en A o en B.
- **Candidatos desde PR abiertos:** `newsletter-plugin` (#613, documentación de diseño) y `personas-especiales` (#614, registro de exclusiones) podrían ser funciones nuevas cuando se fusionen. `chat-con-libros` (#603) no está en `master`, y `pos.colores-revision` depende de la decisión del PR #595.

## Convenciones

- Documentación en español, como el resto del repo.
- Sin dependencias externas: PHP 8.1 o más.
