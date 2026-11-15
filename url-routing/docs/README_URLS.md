# Esquema de URLs Abstracto — Genealogía y POS

URLs sin palabras de dominio, sin guiones ni guiones bajos. El tipo de recurso se infiere de la **forma** del primer segmento de la URL (cantidad de dígitos; 2 letras para un país, 3 para un idioma, 1 para una función del sistema). Ver [`URL_STRUCTURES.md`](./URL_STRUCTURES.md) para la gramática y los ejemplos, y [`RUTAS.md`](./RUTAS.md) para la lista completa, generada desde la configuración.

## 📁 Dónde está cada cosa

| Carpeta | Qué hay |
|---|---|
| `public/` | La única carpeta que expone el servidor web: `index.php` (detecta sitio e idioma) y `.htaccess` |
| `config/routes/` | `genealogy.php` y `pos.php`: el registro de tipos por largo de dígitos y sus acciones |
| `Support/Router.php`, `Routing/` | El despacho por forma del segmento y sus estrategias de coincidencia |
| `Controllers/`, `Repositories/`, `views/` | Un controlador, un repositorio y vistas por tipo, separados por sitio (`Genealogy/`, `POS/`) |
| `database/<sitio>/` | Migraciones SQL y datos de demostración de cada sitio |
| `bin/` | Los scripts de línea de comandos (ver el [README](../README.md)) |
| `deploy/` | El server block de nginx y la prueba de despliegue |
| `docs/` | Esta documentación; `RUTAS.md` se genera con `php bin/rutas.php` |

Todos los tipos registrados en `config/routes/*.php` tienen controlador,
repositorio y vistas conectados a la base de datos (ver
[`DATABASE.md`](./DATABASE.md)). `RouteTargetsExistTest` y `ViewsExistTest`
fallan si una ruta apunta a un controlador/método inexistente o si un
controlador referencia una vista sin archivo, así que agregar un tipo nuevo sin
completar las piezas rompe CI en vez de dar un 500 en producción.

**Pendiente:** devoluciones, preferencias y la edición de colecciones
(`/{colección}/2/`) muestran un aviso de "todavía no disponible". El carrito y la compra (`/c/`, `/p/`) funcionan: la compra crea la
orden, pero no cobra en línea (queda pendiente de pago). El proyecto no tiene cuentas de usuario (es un addon de un solo
operador, no un producto multiusuario): la página de cuenta (`0`) actúa sobre
una cuenta fija en vez de una sesión, y lo privado (colecciones con
`publica = false`, cuenta, edición y pedidos) exige ser el propietario, que se
identifica con `OWNER_TOKEN`. Ver [`ACCESO.md`](./ACCESO.md). Para ponerlo en un servidor: [`DESPLIEGUE.md`](./DESPLIEGUE.md).

## 🧠 Principio de diseño

1. **Solo dígitos** → el número de dígitos selecciona el tipo. Más dígitos = mayor cardinalidad esperada de ese catálogo. `persona` es el techo (10 dígitos) en genealogía; `producto` es el techo (8 dígitos) de los ids correlativos en POS, deliberadamente menor que persona; `orden` usa 16 dígitos porque su id es un código aleatorio, no un número que crece.
2. **Letras** → el largo dice qué es: 2 letras un país, y detrás región y ciudad (1-3 letras) — una jerarquía de códigos, no una secuencia de registros —; 3 letras un idioma (en el subdominio); 1 letra una función del sistema.
3. **Segmento reservado** → rutas de sistema fuera del esquema numérico: `0` (cuenta) y, en el POS, `c` (carrito) y `p` (pago): letras sueltas que se leen igual en varios idiomas, nunca palabras. Sus pasos son acciones numéricas, por el mismo mecanismo.
4. Un segundo segmento numérico, cuando existe, selecciona una **acción** sobre el recurso ya resuelto — su significado depende del tipo (está definido en el arreglo `actions` de cada entrada en `config/routes/*.php`). Un código de acción que no existe en ese arreglo es **404**, nunca un `show` silencioso del recurso base.
5. **Contrato de ids**: todo id numérico se genera relleno con ceros al ancho fijo de su tipo — usa siempre `enlace($tipo, $id)` (nunca concatenes el id a mano), que hace el padding automáticamente vía `Router::typeLength()`.

## 🚀 Probarlo en local

Los comandos para instalar, migrar con datos de demostración y levantar los dos sitios en local están en el [README](../README.md#probarlo-en-local); el despliegue en un servidor, en [`DESPLIEGUE.md`](./DESPLIEGUE.md). El idioma se toma del subdominio de 3 letras (`spa.`, `eng.`…); si no hay uno válido, cae a `spa`. La ruta (path) es idéntica entre idiomas. Los ejemplos de abajo usan los hosts locales de ese README.

## 🔍 Ejemplos de URLs

### Genealogía

```
spa.tudominio.local:8000/6128473190/       persona
spa.tudominio.local:8000/6128473190/1/     ascendencia
spa.tudominio.local:8000/1048293/          colección (árbol)
spa.tudominio.local:8000/582317/           grupo (apellido)
spa.tudominio.local:8000/mx/jal/gdl/       lugar (país/región/ciudad)
spa.tudominio.local:8000/b/10/maria/       búsqueda de personas
spa.tudominio.local:8000/0/                cuenta
```

### POS (Contrastocolor)

```
spa.contrastocolor.local:8001/81372047/       producto
spa.contrastocolor.local:8001/81372047/1/     variantes
spa.contrastocolor.local:8001/48213/          atributo (color)
spa.contrastocolor.local:8001/c/               carrito
spa.contrastocolor.local:8001/p/2/             pago
spa.contrastocolor.local:8001/4821937065182734/2/   seguimiento de una orden
```

## 📝 Agregar un tipo de recurso nuevo

### Paso 1: elegir un largo de dígitos libre en `config/routes/{sitio}.php`

```php
'by_length' => [
    // ...
    3 => [
        'type' => 'nuevo_tipo',
        'controller' => 'Genealogy\NuevoTipoController',
        'actions' => [
            1 => 'accion_uno',
        ],
    ],
],
```

El largo debe ser único dentro del mismo sitio (mismo dominio); no hace falta que coincida entre genealogía y POS, ya que viven en dominios distintos.

### Paso 2: migración, repositorio y controlador

1. Una migración nueva `database/<sitio>/migrations/NNN_nuevo_tipo.sql` con la
   tabla (llave primaria = id de la URL, con `CHECK` del ancho de dígitos).
2. Un repositorio en `Repositories/<Sitio>/` con `find()` y una consulta por acción.
3. El controlador, con `renderFound()`: valida el id (400), carga el registro
   (404 si no existe) y renderiza la vista.

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\NuevoTipoRepository;

class NuevoTipoController extends BaseController
{
    public function show(array $params = []): string
    {
        $repo = new NuevoTipoRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/nuevo_tipo/show', 'nuevo_tipo');
    }

    public function accion_uno(array $params = []): string
    {
        // llamado en /{id-de-N-digitos}/1/
        $repo = new NuevoTipoRepository($this->db());
        return $this->renderFound($params, $repo->find(...), 'genealogy/nuevo_tipo/accion_uno', 'nuevo_tipo',
            fn(int $id) => ['detalle' => $repo->detalle($id)]);
    }
}
```

4. Una vista por método en `views/genealogy/nuevo_tipo/`, con el mismo patrón
   que las existentes (`ob_start()` … `$content = ob_get_clean(); include
   __DIR__ . '/../_layout.php';`).

### Paso 3: generar el enlace

```php
<a href="<?php echo enlace('nuevo_tipo', $id); ?>">Ver</a>
<a href="<?php echo accion('nuevo_tipo', $id, 1); ?>">Acción 1</a>
<!-- enlace() rellena $id con ceros al ancho fijo del tipo automáticamente -->
```

## 🔐 Seguridad

### Validar el id antes de consultar la base de datos

`renderFound()` (en `BaseController`) ya lo hace: responde 400 si el id no es
numérico y 404 si no existe. Las consultas usan siempre parámetros enlazados;
los nombres de tabla y columna que no pueden enlazarse se validan en
`Database::insert/update/delete`.

### Recursos privados

Una colección privada o un pedido responden **404, no 403**, a quien no es el
propietario: los ids son secuenciales y un 403 confirmaría cuáles existen. Lo
decide `AccessPolicy` (ver [`ACCESO.md`](./ACCESO.md), que también explica qué
**no** protege). Las personas que solo están en colecciones privadas se
ocultan igual que las que podrían estar vivas (`Privacidad`); basta una
colección pública para que una persona se vea.

### Escapar salida

```php
<h1><?php echo esc($persona['nombre']); ?></h1>
```

## 🎯 Convenciones

- ✅ Sin guiones ni guiones bajos en ningún segmento.
- ✅ Solo dígitos, o solo letras — nunca mezclados en un mismo segmento.
- ✅ En minúsculas y con barra final: cualquier otra forma recibe un 301 a la canónica.
- ❌ Sin extensiones de archivo (`.php`, `.html`), salvo los archivos para buscadores (`robots.txt`, sitemaps).
- ❌ Sin palabras en la URL, ni en español ni en inglés.

## 🐛 Solución de Problemas

**404 en todas las rutas**: verificar `mod_rewrite` habilitado, que el `DocumentRoot` sea `public/` y que `public/.htaccess` tenga permisos `644`.

**Un id no despacha al controlador esperado**: contar los dígitos exactos del segmento — un dígito de más o de menos cae en otro tipo (o en ningún tipo, y da 404). Revisar `config/routes/{sitio}.php` → `by_length`.

**Una acción no se ejecuta**: el segundo segmento debe ser un entero que exista como clave en el arreglo `actions` de ese tipo; si no coincide, la respuesta es 404 (nunca un `show` silencioso).
