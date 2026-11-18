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
php pruebas/ejecutar.php            # 44 pruebas
php bin/generar.php --comprobar     # FUNCIONES.md al día
```

No necesita Composer ni base de datos. El estado se guarda en `var/estado.json`, fuera de git (`var/.gitignore`).

## Estructura

- [`catalogo.json`](catalogo.json): la fuente de verdad. Lo que está aquí es lo que existe en el panel.
- [`FUNCIONES.md`](FUNCIONES.md): listado generado desde el catálogo. No se edita a mano.
- `src/Interruptores.php`: la clase. Lee el estado, enciende y apaga, y aplica las reglas.
- `src/Excepciones.php`: `UnknownInterruptor` (la ref no existe) y `DependenciaRota` (el cambio dejaría algo roto).
- `panel/index.php`: el panel interno.
- `bin/generar.php`: genera `FUNCIONES.md`.
- `pruebas/ejecutar.php`: las pruebas.

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
INTERRUPTORES_CLAVE='una-clave-larga' php -S 127.0.0.1:8080 -t panel
```

Sin `INTERRUPTORES_CLAVE` no arranca. La sesión usa cookies `HttpOnly` y `SameSite=Strict`; cada acción lleva un token CSRF; la clave se compara con `hash_equals`; un intento fallido espera 0,4 s. Es una sola clave compartida.

Cada función es una tarjeta con su interruptor y, debajo, un botón por opción. Un botón bloqueado muestra el motivo: qué le falta, o qué la necesita.

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
- **Registro de cambios.** Quién apagó qué y cuándo. Hoy el estado solo guarda el valor actual.
- **Usuarios y permisos** en vez de una clave compartida.
- **A confirmar de plataforma:** `document-formats` está fuera del interruptor; no se sabe si va en A o en B.
- **Dos PR abiertos:** `chat-con-libros` (PR #603) no está en `master`, y `pos.colores-revision` depende de la decisión del PR #595.

## Convenciones

- Documentación en español, como el resto del repo.
- Sin dependencias externas: PHP 8.1 o más.
