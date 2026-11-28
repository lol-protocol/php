# Interruptores

Un interruptor por función y, cuando la función tiene variantes, uno por cada variante. Están agrupados en dos plataformas: **A) Genealogía** y **B) Librería y etiquetado POS**. Un panel interno permite encenderlos y apagarlos.

Es una mesa de mezclas: cada **canal** es una función, y cada canal puede tener **sub-canales** (opciones). Algunos canales dependen de otros.

| | Funciones | Opciones | Interruptores |
|---|---|---|---|
| A) Genealogía | 41 | 251 | 292 |
| B) Librería y etiquetado POS | 21 | 64 | 85 |
| **Total** | **62** | **315** | **377** |

Cifras de `FUNCIONES.md`, que es el listado completo: cada función, sus opciones, su origen y sus dependencias. Ya no queda ninguna función supuesta: la persona confirmó las de búsqueda por tipo de fuente y por país.

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
- **Perritos y gatitos** (`gen.mascotas`): dos asistentes, uno por interruptor. El perrito ladra y el gatito maúlla frases motivacionales con enfoque comercial.
- **Búsqueda en diarios y revistas** (`gen.prensa`) y todo lo que busca, deduce, infiere o estima personas: depende de `gen.privacidad`, la protección de quien podría estar vivo.
- **Pasarela de pagos** (`gen.pasarela:stripe`): al apagarse se muestra como «no disponible», no se oculta. La opción `gen.pagos:tarjeta` depende de ella: es una **dependencia de opción** (`depende_de` también va en las opciones).
- **Pedidos de retiro** (`gen.retiros`): depende de la opción `gen.filtro:textos`. No se puede encender mientras la opción de textos del filtro esté apagada.

## Probar

```bash
php pruebas/ejecutar.php            # 156 pruebas: catálogo, dependencias, mínimos, estado, registros, usuarios, bloqueo, escenas y programados
php pruebas/panel.php               # 49 pruebas: el panel en vivo, con php -S
php bin/generar.php --comprobar     # FUNCIONES.md al día
```

No necesita Composer ni base de datos. El estado se guarda en `var/estado.json`, fuera de git (`var/.gitignore`).

## Estructura

- [`catalogo.json`](catalogo.json): la fuente de verdad. Lo que está aquí es lo que existe en el panel.
- [`FUNCIONES.md`](FUNCIONES.md): listado generado desde el catálogo. No se edita a mano.
- `src/Interruptores.php`: la clase. Lee el estado, enciende y apaga, y aplica las reglas.
- `src/Excepciones.php`: `UnknownInterruptor` (la ref no existe) y `DependenciaRota` (el cambio dejaría algo roto).
- `src/Usuarios.php`: usuarios del panel, con contraseña hasheada, rol y bloqueo tras fallos.
- `src/Escenas.php` y `escenas.json`: conjuntos de cambios con nombre y su «deshacer».
- `src/Programados.php`: cambios a una hora dada.
- `panel/index.php`: el panel interno.
- `bin/generar.php`: genera `FUNCIONES.md`.
- `bin/estado.php`: vuelca el estado efectivo en JSON para aplicaciones que no son PHP. El contrato para quien lo consume está en [`CONTRATO.md`](CONTRATO.md).
- `bin/usuarios.php`: alta, baja, cambio de rol y de clave.
- `bin/programados.php`: agendar, listar, cancelar y `ejecutar` (el del cron).
- `pruebas/ejecutar.php` y `pruebas/panel.php`: las pruebas.
- `var/`: estado (`estado.json`), registros (`cambios.jsonl` y `accesos.jsonl`), usuarios, la última escena y los cambios programados. Fuera de git.

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
} elseif ($int->alApagar('gen.vistas:abanico') === 'no_disponible') {
    // mostrar «no disponible» en vez de ocultarlo
}
```

- `activo(ref)`: lo que el sitio debe usar. Una opción solo está activa si su función también lo está.
- `alApagar(ref)`: qué hacer cuando está apagada. `ocultar` (por defecto) o `no_disponible`. Una opción usa la política de su función. Cada función la define en `catalogo.json` con el campo `al_apagar`. Con `no_disponible` hoy: `gen.pagos`, `gen.pasarela`, `pos.pasarela`, `gen.exportar` y `gen.retiros` (confirmada por la persona: ocultar un pedido de retiro de datos sería peor que avisar que no está disponible).
- Crea una instancia por petición: el estado es un archivo pequeño y se lee en cada consulta. Para recorrer muchos interruptores seguidos (un panel, un volcado) llama antes a `congelar()`: lee el estado una sola vez (bajó de 7,3 s a 0,35 s con todo el catálogo) y no ve cambios de otro proceso hasta `descongelar()`. `cambiar()` siempre trabaja con el estado real y descongela.

**Consumidores hoy** (todos opcionales: sin su opción no leen nada de fuera, y cada uno es su propio PR):

| Plataforma | Carpeta | Cómo se activa | Qué apaga |
|---|---|---|---|
| POS | `portadas-svg/` | `--interruptores=../interruptores` | las piezas de `pos.portadas`: portada, contratapa, lomo, cubierta completa |
| POS | `citas-pos/` | `render.php --interruptores=… --salida=<otra carpeta>` | los canales de `pos.citas`: sitio, correo, juego |
| POS | `marcalibros-plataforma-pos-brand-identity/app/` | `MARCALIBROS_INTERRUPTORES=../../interruptores` | la aplicación entera (`pos.mb-h-app`) y cada una de sus 27 categorías (`pos.mb-h-movimientos:<slug>`, `pos.mb-h-tipos-texto:<slug>`) |
| Genealogía | `sistema-nuevo/` | `BACKOFFICE_INTERRUPTORES=../interruptores` | rutas de la API: `gen.backoffice` (todo), `flujo`, `kpis`, `alertas`, `filtros` y `gen.bak-notas` |

La aplicación de genealogía y POS vive en otro repositorio que no se comparte: para ella está [`CONTRATO.md`](CONTRATO.md) (archivo de estado efectivo, `bin/estado.php`, y reglas de consumo). Las vistas del árbol, los medios de pago y los asistentes (vistas del árbol, medios de pago, asistentes…) todavía no lo usan: tienen que llamar a `activo()` como en el ejemplo de arriba.

## Escenas, apagado de emergencia y cambios programados

- **Escenas** (`escenas.json`, clase `Escenas`): conjuntos de cambios con nombre. Una escena puede `apagar` o `encender` refs, `apagar_tipos` (todas las funciones de un tipo) y `mantener` lo que no debe apagarse. El panel las muestra con un botón «Aplicar»; las que llevan `confirmar` piden marcar una casilla. Hay cuatro de ejemplo: `mantenimiento`, `emergencia`, `silencio` y `campana`. **Son una propuesta, no una decisión.**
- **Apagado de emergencia** es la escena `emergencia`: apaga todo lo de producto, interno, datos, visual y contenido, salvo la protección de personas vivas, el filtro de contenido y los pedidos de retiro.
- **Deshacer:** se recuerda lo que cambió la **última** escena (`var/ultima-escena.json`) y «Deshacer la última escena» lo restaura. Solo la última.
- **Aplicar es atómico:** `Interruptores::aplicar()` valida el **estado final** completo y, si rompería algo (una función encendida sin lo que necesita, o sin su mínimo de opciones), no cambia nada. Por eso una escena puede apagar una cadena de dependencias de golpe, cosa que uno por uno no se podría. Cada cambio queda en el registro con su motivo (`escena:silencio`).
- **Cambios programados** (clase `Programados`, `var/programados.json`): encender o apagar una ref, o aplicar una escena, a una hora **UTC**. Se programan desde el panel (escenas) o con `php bin/programados.php agendar …`. Nada se ejecuta solo: `php bin/programados.php ejecutar` aplica lo vencido y va en un cron, cada minuto. Cada cambio pasa a `hecho`, `error` (con el motivo; un error no se reintenta) o `cancelado`.

## Registros

- `var/cambios.jsonl`: cada cambio de un interruptor (`ts`, `quien`, `ref`, `antes`, `despues`).
- `var/accesos.jsonl`: cada acceso al panel (`ts`, `evento`, `usuario`, `origen`). Eventos: `entrar`, `entrar_fallido`, `bloqueado`, `salir`, `caducada`, `usuario_borrado`. Nunca lleva claves. La IP va enmascarada: IPv4 sin el último octeto, IPv6 con el prefijo /64.
- `php bin/registro.php cambios|accesos [n]` los muestra sin abrir el panel.
- `RegistroJsonl` los escribe: cuando el archivo pasa de 5 MB se aparta con la fecha (`cambios-20261118T201500Z-1a2b.jsonl`) y empieza uno nuevo. `ultimas(n)` lee desde el final, así que su coste no depende del tamaño.

## Panel

El panel **no se publica**. Que lo sirva solo una red interna, o un VPN, o un servidor con autenticación delante.

```bash
php bin/usuarios.php crear ana editor     # pide la clave dos veces… y no la muestra al escribir
php bin/usuarios.php crear luis lector
php -S 127.0.0.1:8080 -t panel
```

Sin usuarios, el panel no entra y lo dice. Dos roles:

- **editor**: ve todo y enciende o apaga.
- **lector**: ve todo, pero no cambia nada. Los botones aparecen bloqueados, y si alguien fuerza el envío, el servidor lo rechaza igual.

Seguridad del acceso:

- La clave se guarda con `password_hash`; el archivo no contiene la clave en claro.
- **Bloqueo por usuario y origen.** Tras 5 claves incorrectas seguidas desde una misma IP, esa IP queda bloqueada 15 minutos para ese usuario. Otra red sigue pudiendo entrar, así que quien falla desde su propia red no bloquea a la persona real. Los fallos cuentan solo dentro de una ventana de 15 minutos.
- Un usuario que no existe responde igual que uno que sí existe (mismo tiempo, mismo mensaje).
- **Sesión vigente en cada petición.** La sesión caduca tras `INTERRUPTORES_CADUCIDAD` segundos sin actividad (1800 por defecto). Si borras a alguien, su sesión abierta se cierra en su siguiente acción; si le bajas el rol, el cambio se aplica en la siguiente acción, sin volver a entrar.
- Cada acción lleva un token CSRF, también cerrar sesión. Las cookies son `HttpOnly` y `SameSite=Strict`; la página envía `Content-Security-Policy` sin scripts.
- El rol se comprueba en el servidor, no solo en el formulario.
- Si el panel está detrás de un proxy, la IP que ve es la del proxy: todos los que entran por él comparten el mismo bloqueo. Habrá que leer la IP real del proxy de confianza.

Otros comandos de `bin/usuarios.php`: `listar`, `rol <nombre> <rol>`, `clave <nombre>` y `borrar <nombre>`. No se puede dejar el sistema sin ningún editor. Para usar tubería (por ejemplo, desde un gestor de secretos): `printf '%s' "$CLAVE" | php bin/usuarios.php crear ana editor`.

Los cambios se hacen con un bloqueo exclusivo sobre el estado: dos personas que cambian a la vez no se pisan ni se saltan una dependencia. El panel muestra los últimos 20 cambios.

## Añadir una función o una opción

1. Edita `catalogo.json`. Una función lleva `id` (`plataforma.nombre`), `plataforma`, `nombre`, `tipo`, `origen`, `carpetas`, `descripcion`, `depende_de`, `por_defecto`, y opcionalmente `min_opciones` y `opciones`.
2. `origen`:
   - `repo`: su código está en una carpeta de este repositorio; las pruebas comprueban que la carpeta exista.
   - `fuera_del_repo`: vive en la aplicación principal, que no está aquí.
   - `pr_abierto`: su carpeta todavía está en un PR sin fusionar (hoy ninguna función lo usa).
3. Corre `php bin/generar.php` y `php pruebas/ejecutar.php`.

Las pruebas rechazan un catálogo con ids repetidos, con `:` en un id, con una dependencia a algo que no existe o a sí mismo, con un mínimo mayor que el número de opciones, o con una plataforma que no existe.

## Lo que falta

- **Confirmar las escenas.** Las cuatro de `escenas.json` (mantenimiento, emergencia, silencio comercial y campaña) son una propuesta: qué apaga cada una lo decide la persona.
- **Un cron para los cambios programados.** Nada se ejecuta solo: hay que poner `php bin/programados.php ejecutar` en un cron (cada minuto).
- **Límites conocidos.** El bloqueo de archivo sirve con un solo servidor; con varios hace falta una base de datos. Un proxy comparte la IP entre sus usuarios. `sistema-nuevo/` lee el estado en cada petición. Los registros apartados al rotar quedan en `var/` sin borrarse.
- **A confirmar de plataforma:** `document-formats` está fuera del interruptor; no se sabe si va en A o en B.
- **Funciones que podrían sumarse:** `newsletter-plugin/` (diseño del boletín) y `personas-especiales/` (registro de exclusiones) ya están en `master` y no tienen interruptor. Hace falta decidir si lo merecen y qué opciones tendrían.

## Convenciones

- Documentación en español, como el resto del repo.
- Sin dependencias externas: PHP 8.1 o más.
