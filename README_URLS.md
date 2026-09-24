# Esquema de URLs Abstracto — Genealogía y POS

URLs sin palabras de dominio, sin guiones ni guiones bajos. El tipo de recurso se infiere de la **forma** del primer segmento de la URL (cantidad de dígitos, o letras puras para lugares). Ver [`URL_STRUCTURES.md`](./URL_STRUCTURES.md) para la tabla completa de formatos y ejemplos.

## 📁 Estructura de Archivos

```
/php
├── index.php                          # Punto de entrada, detecta sitio + idioma
├── Router.php                          # Despacho por forma del segmento
├── .htaccess                           # Configuración Apache
├── URL_STRUCTURES.md                   # Tabla completa de formatos
├── README_URLS.md                      # Este archivo
├── routes/
│   ├── genealogy.php                  # Registro de tipos por largo de dígitos
│   └── pos.php                         # Registro de tipos + flujo transaccional
└── Controllers/
    ├── Genealogy/
    │   ├── PersonaController.php       # 10 dígitos
    │   ├── HomeController.php          # raíz, listado/búsqueda
    │   └── CuentaController.php        # ruta reservada "0"
    └── POS/
        ├── ProductoController.php      # 8 dígitos
        ├── HomeController.php
        ├── CuentaController.php
        ├── CartController.php          # /cart/
        ├── CheckoutController.php      # /checkout/...
        └── OrderController.php         # /order/{id}/...
```

## 🧠 Principio de diseño

1. **Solo dígitos** → el número de dígitos selecciona el tipo. Más dígitos = mayor cardinalidad esperada de ese catálogo. `persona` es el techo (10 dígitos) en genealogía; `producto` es el techo (8 dígitos) en POS, deliberadamente menor que persona.
2. **Solo letras** → jerarquía de lugar (país/región/ciudad), porque es una jerarquía de códigos, no una secuencia de registros.
3. **Palabra exacta reservada** → rutas de sistema fuera del esquema numérico: `cart`, `checkout`, `order` (flujo de compra, en inglés por decisión explícita) y `0` (cuenta, con sus propias sub-acciones por el mismo mecanismo).
4. Un segundo segmento numérico, cuando existe, selecciona una **acción** sobre el recurso ya resuelto — su significado depende del tipo (está definido en el arreglo `actions` de cada entrada en `routes/*.php`). Un código de acción que no existe en ese arreglo es **404**, nunca un `show` silencioso del recurso base.
5. **Contrato de ids**: todo id numérico se genera relleno con ceros al ancho fijo de su tipo — usa siempre `enlace($tipo, $id)` (nunca concatenes el id a mano), que hace el padding automáticamente vía `Router::typeLength()`.

## 🚀 Inicio Rápido

### Apache (mod_rewrite)

El `.htaccess` ya incluido redirige todo a `index.php`:

```bash
a2enmod rewrite
systemctl restart apache2
```

### Hosts locales + subdominios de idioma

```
127.0.0.1 spa.tudominio.local
127.0.0.1 eng.tudominio.local
127.0.0.1 spa.contrastocolor.local
127.0.0.1 eng.contrastocolor.local
```

El idioma se toma del subdominio de 3 letras (`determineLocale()` en `index.php`); si no hay uno válido, cae a `spa` por defecto. La ruta (path) es idéntica entre idiomas.

### Servidor local

```bash
php -S spa.tudominio.local:8000 index.php
php -S spa.contrastocolor.local:8001 index.php
```

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

### Paso 1: elegir un largo de dígitos libre en `routes/{sitio}.php`

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

### Paso 2: crear el controlador

```php
<?php

namespace App\Controllers\Genealogy;

class NuevoTipoController
{
    public function show($params = [])
    {
        // $params['id'] trae el identificador numérico completo
    }

    public function accion_uno($params = [])
    {
        // llamado en /{id-de-N-digitos}/1/
    }
}
```

### Paso 3: generar el enlace

```php
<a href="<?php echo enlace('nuevo_tipo', $id); ?>">Ver</a>
<a href="<?php echo accion('nuevo_tipo', $id, 1); ?>">Acción 1</a>
<!-- enlace() rellena $id con ceros al ancho fijo del tipo automáticamente -->
```

## 🔐 Seguridad

### Validar el id antes de consultar la base de datos

```php
public function show($params = [])
{
    $id = $params['id'];

    if (!ctype_digit($id)) {
        http_response_code(400);
        return 'Id inválido';
    }
    // ...
}
```

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

**404 en todas las rutas**: verificar `mod_rewrite` habilitado y `.htaccess` presente en la raíz con permisos `644`.

**Un id no despacha al controlador esperado**: contar los dígitos exactos del segmento — un dígito de más o de menos cae en otro tipo (o en ningún tipo, y da 404). Revisar `routes/{sitio}.php` → `by_length`.

**Una acción no se ejecuta**: el segundo segmento debe ser un entero que exista como clave en el arreglo `actions` de ese tipo; si no coincide, el router usa `show` por defecto.
