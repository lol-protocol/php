# Esquema de URLs Abstracto — Genealogía y POS

URLs sin palabras de dominio, sin guiones ni guiones bajos. El tipo de recurso se infiere de la **forma** del primer segmento de la URL (cantidad de dígitos, o letras puras para lugares). Ver [`URL_STRUCTURES.md`](./URL_STRUCTURES.md) para la tabla completa de formatos y ejemplos.

## 📁 Estructura de Archivos

```
url-routing/
├── public/                             # ÚNICA carpeta que expone el servidor web
│   ├── index.php                       # Punto de entrada, detecta sitio + idioma
│   └── .htaccess                       # Configuración Apache
├── bin/migrate.php                     # Migraciones (+ datos de demo con --seed)
├── database/<sitio>/                   # Migraciones SQL y datos de demostración
├── Repositories/                       # Consultas por tipo (SQLite y PostgreSQL)
├── deploy/nginx.conf.example           # Server block para el VPS
├── docs/
│   ├── URL_STRUCTURES.md               # Tabla completa de formatos
│   └── README_URLS.md                  # Este archivo
├── config/routes/
│   ├── genealogy.php                  # Registro de tipos por largo de dígitos
│   └── pos.php                         # Registro de tipos + flujo transaccional
├── Support/
│   └── Router.php                      # Despacho por forma del segmento
├── Routing/                            # Estrategias de matching (dígitos, lugar, literal, order, reservada)
├── views/
│   ├── _layout.php                     # Esqueleto HTML común (cabecera, buscador)
│   ├── genealogy/<tipo>/<acción>.php   # + _layout.php y _partials/ del sitio
│   └── pos/<tipo>/<acción>.php
└── Controllers/
    ├── Genealogy/                      # Persona (10), Suceso (9), Registro (8), Coleccion (7),
    │                                   # Grupo (6), Organizacion (5), Lugar, Home, Cuenta
    └── POS/                            # Producto (8), Coleccion (7), Etiqueta (6), Atributo (5),
                                        # Grupo (4), Home, Cuenta, Cart, Checkout, Order
```

Todos los tipos registrados en `config/routes/*.php` tienen controlador,
repositorio y vistas conectados a la base de datos (ver
[`DATABASE.md`](./DATABASE.md)). `RouteTargetsExistTest` y `ViewsExistTest`
fallan si una ruta apunta a un controlador/método inexistente o si un
controlador referencia una vista sin archivo, así que agregar un tipo nuevo sin
completar las piezas rompe CI en vez de dar un 500 en producción.

**Pendiente:** carrito, checkout, edición de colecciones, devoluciones y
preferencias muestran un aviso de "todavía no disponible" — son flujos de
escritura que necesitan un inicio de sesión, y el proyecto todavía no tiene
uno (las páginas de cuenta ya funcionan con `$_SESSION['user_id']`).

## 🧠 Principio de diseño

1. **Solo dígitos** → el número de dígitos selecciona el tipo. Más dígitos = mayor cardinalidad esperada de ese catálogo. `persona` es el techo (10 dígitos) en genealogía; `producto` es el techo (8 dígitos) en POS, deliberadamente menor que persona.
2. **Solo letras** → jerarquía de lugar (país/región/ciudad), porque es una jerarquía de códigos, no una secuencia de registros.
3. **Palabra exacta reservada** → rutas de sistema fuera del esquema numérico: `cart`, `checkout`, `order` (flujo de compra, en inglés por decisión explícita) y `0` (cuenta, con sus propias sub-acciones por el mismo mecanismo).
4. Un segundo segmento numérico, cuando existe, selecciona una **acción** sobre el recurso ya resuelto — su significado depende del tipo (está definido en el arreglo `actions` de cada entrada en `config/routes/*.php`). Un código de acción que no existe en ese arreglo es **404**, nunca un `show` silencioso del recurso base.
5. **Contrato de ids**: todo id numérico se genera relleno con ceros al ancho fijo de su tipo — usa siempre `enlace($tipo, $id)` (nunca concatenes el id a mano), que hace el padding automáticamente vía `Router::typeLength()`.

## 🚀 Inicio Rápido

Desde `url-routing/` (el proyecto es autocontenido: su propio `composer.json`):

```bash
composer install
php bin/migrate.php genealogy --seed     # crea var/genealogy.sqlite con datos de demo
php bin/migrate.php pos --seed           # crea var/pos.sqlite con datos de demo
php -S spa.tudominio.local:8000 -t public public/index.php
php -S spa.contrastocolor.local:8001 -t public public/index.php
```

Sin configurar nada, cada sitio usa un SQLite local en `var/`. Para
PostgreSQL y el criterio de qué motor usar en cada sitio, ver
[`DATABASE.md`](./DATABASE.md).

### Despliegue

**La raíz web debe ser `public/`**, nunca la carpeta del proyecto: fuera de
`public/` están `.env` (contraseñas), `var/` (las bases SQLite), `bin/` y el
código, que ninguna URL debe alcanzar.

- **Nginx** (el VPS): ver [`deploy/nginx.conf.example`](../deploy/nginx.conf.example),
  con `root /var/www/url-routing/public`. Ojo: la plantilla genérica de
  `vps-setup/06_A-setup-php-app.sh` usa la carpeta de la app como `root`; para
  este proyecto hay que apuntarla a `public/`.
- **Apache**: `DocumentRoot /var/www/url-routing/public` con `mod_rewrite`
  habilitado; el `.htaccess` incluido en `public/` redirige todo a `index.php`
  y asume que la app vive en la raíz del dominio (`RewriteBase /`).

### Hosts locales + subdominios de idioma

```
127.0.0.1 spa.tudominio.local
127.0.0.1 eng.tudominio.local
127.0.0.1 spa.contrastocolor.local
127.0.0.1 eng.contrastocolor.local
```

El idioma se toma del subdominio de 3 letras; si no hay uno válido, cae a
`spa` por defecto. La ruta (path) es idéntica entre idiomas.

### Tests

```bash
./vendor/bin/phpunit
```

Con `TEST_PG_DSN` definida corren además contra PostgreSQL (ver `DATABASE.md`).

## 🔍 Ejemplos de URLs

### Genealogía

```
spa.tudominio.local:8000/6128473190/       persona
spa.tudominio.local:8000/6128473190/1/     ascendencia
spa.tudominio.local:8000/1048293/          colección (árbol)
spa.tudominio.local:8000/582317/           grupo (apellido)
spa.tudominio.local:8000/mx/jal/gdl/       lugar (país/región/ciudad)
spa.tudominio.local:8000/?t=10&q=maria     búsqueda de personas
spa.tudominio.local:8000/0/                cuenta
```

### POS (Contrastocolor)

```
spa.contrastocolor.local:8001/81372047/       producto
spa.contrastocolor.local:8001/81372047/1/     variantes
spa.contrastocolor.local:8001/48213/          atributo (color)
spa.contrastocolor.local:8001/cart/
spa.contrastocolor.local:8001/checkout/payment/
spa.contrastocolor.local:8001/order/8137204719000/2/   seguimiento
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

Una colección privada o una orden ajena responden **404, no 403**: los ids son
secuenciales y un 403 confirmaría cuáles existen.

### Escapar salida

```php
<h1><?php echo esc($persona['nombre']); ?></h1>
```

## 🎯 Convenciones

- ✅ Sin guiones ni guiones bajos en ningún segmento.
- ✅ Solo dígitos, o solo letras — nunca mezclados en un mismo segmento (excepto las rutas literales reservadas).
- ✅ Con barra final.
- ❌ Sin extensiones de archivo (`.php`, `.html`).
- ❌ Sin palabras de dominio en la URL (ni en español ni en inglés), salvo el flujo transaccional de POS.

## 🐛 Solución de Problemas

**404 en todas las rutas**: verificar `mod_rewrite` habilitado, que el `DocumentRoot` sea `public/` y que `public/.htaccess` tenga permisos `644`.

**Un id no despacha al controlador esperado**: contar los dígitos exactos del segmento — un dígito de más o de menos cae en otro tipo (o en ningún tipo, y da 404). Revisar `config/routes/{sitio}.php` → `by_length`.

**Una acción no se ejecuta**: el segundo segmento debe ser un entero que exista como clave en el arreglo `actions` de ese tipo; si no coincide, el router usa `show` por defecto.
