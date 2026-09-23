# Estructura de URLs Friendly - Genealogía y POS

Este proyecto implementa una arquitectura de URLs friendly para dos aplicaciones: un sitio de genealogía y un sitio de ecommerce para Contrastocolor.

## 📁 Estructura de Archivos

```
/php
├── index.php                          # Punto de entrada principal
├── Router.php                          # Motor de enrutamiento
├── .htaccess                           # Configuración Apache
├── URL_STRUCTURES.md                   # Documentación completa de URLs
├── README_URLS.md                      # Este archivo
├── routes/
│   ├── genealogy.php                  # Rutas del sitio de genealogía
│   └── pos.php                         # Rutas del sitio de ecommerce
└── Controllers/
    ├── Genealogy/
    │   └── PeopleController.php        # Controlador de personas
    └── POS/
        └── ProductsController.php      # Controlador de productos
```

## 🚀 Inicio Rápido

### 1. Configuración del Servidor

#### Apache (mod_rewrite habilitado)

El archivo `.htaccess` redirige automáticamente todas las requests a `index.php`:

```bash
# Verificar que mod_rewrite está habilitado
a2enmod rewrite

# Reiniciar Apache
systemctl restart apache2
```

#### Nginx

```nginx
server {
    listen 80;
    server_name genealogy.local;
    root /path/to/php;

    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php last;
        }
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### 2. Hosts Locales

Agregar a `/etc/hosts` (Linux/Mac) o `C:\Windows\System32\drivers\etc\hosts` (Windows):

```
127.0.0.1 genealogy.local
127.0.0.1 contrastocolor.local
127.0.0.1 pos.local
```

### 3. Ejecutar Servidor Local

```bash
# PHP built-in server (solo desarrollo)
php -S genealogy.local:8000 index.php
php -S contrastocolor.local:8001 index.php
```

## 🔍 Ejemplos de URLs

### Genealogía

```
http://genealogy.local/people/                    # Lista de personas
http://genealogy.local/people/juan-garcia-1925/   # Perfil de Juan García
http://genealogy.local/people/123/ancestors/      # Ancestros de persona ID 123
http://genealogy.local/surnames/garcia/           # Página del apellido García
http://genealogy.local/places/mexico/jalisco/     # Estado de Jalisco
http://genealogy.local/events/births/?year=1920   # Nacimientos año 1920
http://genealogy.local/search/?q=maria            # Buscar "maria"
```

### POS (Contrastocolor)

```
http://contrastocolor.local/products/              # Catálogo de productos
http://contrastocolor.local/products/blue-shirt-v1/     # Camiseta azul
http://contrastocolor.local/categories/shirts/    # Categoría de camisetas
http://contrastocolor.local/colors/navy/          # Productos color azul marino
http://contrastocolor.local/orders/                # Mis órdenes
http://contrastocolor.local/cart/                 # Carrito de compras
http://contrastocolor.local/search/?q=shirts      # Buscar "camisetas"
http://contrastocolor.local/products/filter/?category=shirts&color=blue
```

## 📝 Crear Nuevas Rutas

### Paso 1: Agregar Ruta al Archivo de Configuración

En `routes/genealogy.php` o `routes/pos.php`:

```php
'people.create' => [
    'path' => '/people/create/',
    'controller' => 'Genealogy\PeopleController@create',
    'methods' => ['GET', 'POST'],
],
```

### Paso 2: Implementar Controlador

En `Controllers/Genealogy/PeopleController.php`:

```php
public function create($params = [])
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Procesar formulario
        $data = $_POST;
        // TODO: Guardar en base de datos
        redirect('people.index');
    }

    return view('genealogy/people/create');
}
```

### Paso 3: Generar URL en Plantillas

```php
<a href="<?php echo route('people.create'); ?>">Crear Persona</a>

<!-- Con parámetros -->
<a href="<?php echo route('people.show', ['id' => 'juan-garcia-1925']); ?>">
    Ver Perfil
</a>
```

## 🔐 Seguridad

### Validación de Parámetros

```php
public function show($params = [])
{
    $id = $params['id'] ?? null;

    // Validar que el ID sea válido
    if (!$id || !preg_match('/^[a-z0-9-]+$/', $id)) {
        http_response_code(400);
        return 'Invalid ID';
    }

    // Continuar...
}
```

### Escapar Salida

```php
<!-- En plantillas PHP -->
<h1><?php echo esc($person['name']); ?></h1>

<!-- Previene XSS -->
```

### CSRF Protection (Recomendado)

```php
// En controladores con POST
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    return 'CSRF token mismatch';
}
```

## 🎯 Convenciones

### Nombres de Rutas

- Usar punto para jerarquía: `people.index`, `people.show`, `people.create`
- Usar singular para recursos: `product`, `person`, `order`
- Usar plural para colecciones: `products`, `people`, `orders`

### URLs

- ✅ Minúsculas: `/people/`, `/categories/`
- ✅ Guiones: `/user-profiles/`, `/contact-info/`
- ✅ Con barra final: `/products/`, `/help/`
- ❌ Sin extensiones: No `.php`, `.html`
- ❌ Sin mayúsculas: No `/Products/`, `/Categories/`

### Parámetros

- Usar query string para filtros: `?page=2&category=shirts`
- Usar URL path para IDs: `/people/{id}/`, `/products/{id}/`

## 📊 Debugging

### Habilitar Modo Debug

Agregar a `index.php`:

```php
define('DEBUG_MODE', getenv('DEBUG') === 'true');
```

Ejecutar con debug:

```bash
DEBUG=true php index.php
```

### Ver Rutas Registradas

```php
// Temporalmente en index.php
echo '<pre>';
print_r($router->routes);
echo '</pre>';
```

## 🔧 Personalizaciones

### Cambiar Dominios

En `index.php`, función `determineSite()`:

```php
$genealogy_domains = [
    'mi-genealogy.com',
    'genealogy.mi-sitio.com',
];

$pos_domains = [
    'tienda.contrastocolor.com',
    'shop.contrastocolor.com',
];
```

### Agregar Prefix a URLs

En `index.php`:

```php
define('APP_BASE_PATH', '/sitios/php/');
```

Luego las URLs serían:
```
http://localhost/sitios/php/people/
http://localhost/sitios/php/products/
```

## 🐛 Solución de Problemas

### URLs no funcionan (404)

1. Verificar que mod_rewrite está habilitado: `a2enmod rewrite`
2. Verificar `.htaccess` está en la raíz
3. Verificar permisos: `chmod 644 .htaccess`
4. Reiniciar servidor: `systemctl restart apache2`

### Parámetros no se capturan

1. Verificar patrón de ruta coincide con URL
2. Verificar constraints en configuración
3. Revisar logs de Apache: `tail -f /var/log/apache2/error.log`

### Controlador no encontrado

1. Verificar nombre de clase coincide exactamente
2. Verificar namespace es correcto
3. Verificar archivo existe en estructura de carpetas
4. Verificar autoloader (spl_autoload_register)

## 📚 Recursos

- [URL Structures Documentation](./URL_STRUCTURES.md) - Documentación completa
- [Router Class](./Router.php) - Código del motor de enrutamiento
- [Genealogy Routes](./routes/genealogy.php) - Todas las rutas de genealogía
- [POS Routes](./routes/pos.php) - Todas las rutas de ecommerce

## 📝 Licencia

MIT License - Libre para usar y modificar.
