# Estructuras de URLs Friendly

## 1. Sitio de Genealogía

### Perfiles / Individuos
- `/people/` - Listado de personas
- `/people/{id}/` - Perfil individual (ej: /people/juan-garcia-1925/)
- `/people/{id}/ancestors/` - Árbol ancestral
- `/people/{id}/descendants/` - Árbol descendiente
- `/people/{id}/relationships/` - Relaciones familiares
- `/people/{id}/timeline/` - Línea de tiempo de vida

### Apellidos / Familias
- `/surnames/` - Listado de apellidos
- `/surnames/{surname}/` - Apellido específico (ej: /surnames/garcia/)
- `/surnames/{surname}/tree/` - Árbol del apellido
- `/surnames/{surname}/distribution/` - Distribución geográfica
- `/families/{family-id}/` - Familia específica
- `/families/{family-id}/genealogy/` - Genealogía de la familia
- `/families/{family-id}/members/` - Miembros de la familia

### Lugares / Localizaciones
- `/places/` - Listado de lugares
- `/places/{country}/` - País (ej: /places/mexico/)
- `/places/{country}/{state}/` - Estado/Provincia (ej: /places/mexico/jalisco/)
- `/places/{country}/{state}/{city}/` - Ciudad (ej: /places/mexico/jalisco/guadalajara/)
- `/places/{place-id}/people/` - Personas de un lugar
- `/places/{place-id}/events/` - Eventos registrados en un lugar

### Organizaciones / Instituciones
- `/organizations/` - Listado de organizaciones
- `/organizations/{org-id}/` - Organización específica
- `/organizations/{org-id}/members/` - Miembros de la organización
- `/organizations/{org-id}/records/` - Registros gestionados

### Eventos
- `/events/` - Listado de eventos
- `/events/births/` - Nacimientos
- `/events/deaths/` - Muertes
- `/events/marriages/` - Matrimonios
- `/events/migrations/` - Migraciones
- `/events/{event-id}/` - Evento específico

### Registros / Documentos
- `/records/` - Listado de registros
- `/records/{type}/` - Por tipo (births, deaths, marriages, etc.)
- `/records/{record-id}/` - Registro específico
- `/records/{record-id}/source/` - Fuente del registro

### Búsqueda y Filtros
- `/search/` - Búsqueda general
- `/search/people/?q={query}` - Búsqueda de personas
- `/search/surnames/?q={query}` - Búsqueda de apellidos
- `/search/places/?q={query}` - Búsqueda de lugares

### Árbol Genealógico
- `/trees/` - Listado de árboles
- `/trees/{tree-id}/` - Árbol específico
- `/trees/{tree-id}/view/` - Vista del árbol
- `/trees/{tree-id}/edit/` - Editar árbol
- `/trees/{tree-id}/export/` - Exportar árbol (GEDCOM, PDF, etc.)

### Usuario / Cuenta
- `/profile/` - Perfil del usuario
- `/profile/trees/` - Mis árboles
- `/profile/contributions/` - Mis contribuciones
- `/profile/saved/` - Guardados
- `/settings/` - Configuración

### Reportes
- `/reports/` - Listado de reportes
- `/reports/statistics/` - Estadísticas globales
- `/reports/surnames-distribution/` - Distribución de apellidos
- `/reports/timeline/{year}/` - Eventos por año

---

## 2. Sitio de Etiquetado POS (Contrastocolor)

### Productos
- `/products/` - Catálogo de productos
- `/products/{product-id}/` - Producto específico
- `/products/{product-id}/details/` - Detalles del producto
- `/products/{product-id}/variants/` - Variantes del producto
- `/products/{product-id}/colors/` - Paleta de colores disponibles

### Categorías
- `/categories/` - Listado de categorías
- `/categories/{category-slug}/` - Categoría específica (ej: /categories/contrast-shirts/)
- `/categories/{category-slug}/subcategories/` - Subcategorías
- `/categories/{category-slug}/{subcategory-slug}/` - Subcategoría

### Colores / Contrastes
- `/colors/` - Paleta de colores disponibles
- `/colors/{color-id}/` - Color específico
- `/colors/{color-id}/products/` - Productos de ese color
- `/contrast-palettes/` - Paletas de contraste predefinidas
- `/contrast-palettes/{palette-id}/` - Paleta específica
- `/contrast-checker/` - Herramienta de verificación de contraste

### Órdenes / Transacciones
- `/orders/` - Mis órdenes
- `/orders/{order-id}/` - Orden específica
- `/orders/{order-id}/invoice/` - Factura
- `/orders/{order-id}/tracking/` - Seguimiento
- `/orders/{order-id}/returns/` - Devoluciones

### Carrito de Compras
- `/cart/` - Carrito
- `/checkout/` - Proceso de compra
- `/checkout/shipping/` - Envío
- `/checkout/payment/` - Pago
- `/checkout/confirmation/` - Confirmación

### Etiquetado / Tagging
- `/tags/` - Listado de etiquetas
- `/tags/{tag-slug}/` - Etiqueta específica
- `/tags/{tag-slug}/products/` - Productos con etiqueta

### Búsqueda
- `/search/` - Búsqueda general
- `/search/?q={query}` - Búsqueda por término
- `/search/?q={query}&category={category}` - Búsqueda filtrada
- `/search/?q={query}&color={color}` - Búsqueda por color
- `/search/?q={query}&price-min={min}&price-max={max}` - Búsqueda por precio

### Filtros Avanzados
- `/products/filter/?category={cat}&color={color}&size={size}` - Filtrado múltiple
- `/products/filter/?contrast-level={level}` - Por nivel de contraste
- `/products/filter/?accessible=true` - Productos accesibles

### Colecciones / Campañas
- `/collections/` - Colecciones especiales
- `/collections/{collection-id}/` - Colección específica
- `/campaigns/` - Campañas promocionales
- `/campaigns/{campaign-id}/` - Campaña específica

### Usuario / Cuenta
- `/account/` - Mi cuenta
- `/account/profile/` - Perfil
- `/account/orders/` - Historial de órdenes
- `/account/wishlist/` - Lista de deseos
- `/account/addresses/` - Direcciones guardadas
- `/account/settings/` - Configuración
- `/account/preferences/` - Preferencias

### Promociones
- `/promotions/` - Ofertas y descuentos
- `/promotions/{promo-id}/` - Promoción específica
- `/deals/` - Ofertas del día
- `/seasonal/` - Colecciones estacionales

### Soporte
- `/help/` - Centro de ayuda
- `/help/faq/` - Preguntas frecuentes
- `/help/shipping/` - Envíos
- `/help/returns/` - Política de devoluciones
- `/help/size-guide/` - Guía de tallas
- `/contact/` - Contacto

### Admin / Gestión (si aplica)
- `/admin/` - Panel de administración
- `/admin/products/` - Gestión de productos
- `/admin/inventory/` - Inventario
- `/admin/orders/` - Gestión de órdenes
- `/admin/reports/` - Reportes
- `/admin/colors/` - Gestión de colores
- `/admin/tags/` - Gestión de etiquetas

---

## Convenciones de URL

### Formato General
- **Minúsculas**: Todas las URLs en minúsculas
- **Guiones**: Separar palabras con guiones (kebab-case)
- **Sin extensiones**: No incluir extensiones de archivo (.php, .html)
- **Con barra final**: Terminan en `/`
- **Parámetros de query**: Para filtros, búsquedas y opcionales

### Ejemplos Correctos
✓ `/categories/contrasted-shirts/`
✓ `/people/maria-garcia-1980/`
✓ `/search/?q=blue-shirts&color=navy`
✓ `/places/mexico/jalisco/`

### Ejemplos Incorrectos
✗ `/Categories/Contrasted-Shirts`
✗ `/people/mariaCasillas/`
✗ `/search.php?query=`
✗ `/Places/Mexico/Jalisco`

---

## Enrutamiento en PHP

### Estructura Recomendada

```
routes/
├── genealogy.php
├── pos.php
├── api.php
└── web.php

controllers/
├── Genealogy/
│   ├── PeopleController.php
│   ├── SurnamesController.php
│   ├── PlacesController.php
│   ├── OrganizationsController.php
│   ├── EventsController.php
│   └── TreesController.php
└── POS/
    ├── ProductsController.php
    ├── CategoriesController.php
    ├── OrdersController.php
    ├── CartController.php
    └── SearchController.php
```

### Middleware Recomendado
- URL rewriting (mod_rewrite en Apache)
- Slug validation
- 404 handling
- Locale detection (si es multiidioma)
- Canonical URLs
