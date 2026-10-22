# Rutas

<!-- Generado por `php bin/rutas.php > docs/RUTAS.md` desde config/routes/*.php. No editar a mano. -->

## Gramática: la forma del primer segmento dice qué es

| Forma | Regex | Significa |
|---|---|---|
| vacío | `^$` | inicio |
| `0` | `^0$` | cuenta |
| 1 letra | `^[a-z]$` | función del sistema (carrito, pago, búsqueda) |
| 2 letras | `^[a-z]{2}$` | país (ISO 3166-1); detrás, región y ciudad de 1-3 letras |
| 3 letras | `^[a-z]{3}$` | idioma (ISO 639-2): va en el subdominio, nunca en la ruta |
| 4+ letras | `^[a-z]{4,}$` | texto libre (p. ej. lo que se busca, detrás de `/b/{tipo}/`) |
| solo dígitos | `^\d+$` | una entidad: la cantidad de dígitos dice el tipo; 1 dígito detrás de algo es una acción |
| con punto | `\.` | un archivo para buscadores (`robots.txt`, sitemaps) |

## Genealogía

### Entidades (solo dígitos)

| Dígitos | Tipo | Acciones |
|---|---|---|
| 10 | persona | 1 ascendencia · 2 descendencia · 3 vinculos · 4 cronologia · 5 viva |
| 9 | suceso | — |
| 8 | registro | 1 fuente |
| 7 | coleccion | 1 vista · 2 editar · 3 exportar |
| 6 | grupo | 1 red · 2 dispersion |
| 5 | organizacion | 1 miembros · 2 registros |

### Lugares

`/{país}/[{región}/[{ciudad}/]]` · acciones: 1 personas · 2 sucesos

### Rutas reservadas

| Ruta | Qué es | Acciones |
|---|---|---|
| `/` | `Genealogy\HomeController::index` | — |
| `/b/` | `Genealogy\HomeController::buscar` | hasta 3 segmentos más |
| `/r/` | `Genealogy\RetiroController::pedir` | hasta 1 segmentos más |
| `/0/` | `Genealogy\CuentaController::index` | 1 colecciones · 2 aportes · 3 retiros |

### Archivos

- `/robots.txt` → `Genealogy\ArchivosController::robots`
- `/sitemap.xml` → `Genealogy\ArchivosController::indice`
- `/sitemap/…` → `Genealogy\ArchivosController::parte`

## POS (Contrastocolor)

### Entidades (solo dígitos)

| Dígitos | Tipo | Acciones |
|---|---|---|
| 16 | orden | 1 invoice · 2 track · 3 devolucion |
| 8 | producto | 1 variantes · 2 atributos |
| 7 | coleccion | — |
| 6 | etiqueta | 1 productos |
| 5 | atributo | 1 productos |
| 4 | grupo | — |

### Rutas reservadas

| Ruta | Qué es | Acciones |
|---|---|---|
| `/` | `POS\HomeController::index` | — |
| `/b/` | `POS\HomeController::buscar` | hasta 3 segmentos más |
| `/0/` | `POS\CuentaController::index` | 1 perfil · 2 ordenes · 3 deseos · 4 direcciones · 5 preferencias |
| `/c/` | `POS\CartController::show` | 1 agregar · 2 actualizar · 3 quitar · 4 vaciar |
| `/p/` | `POS\CheckoutController::index` | 1 shipping · 2 payment · 3 confirm |

### Archivos

- `/robots.txt` → `POS\ArchivosController::robots`
- `/sitemap.xml` → `POS\ArchivosController::indice`
- `/sitemap/…` → `POS\ArchivosController::parte`

### Redirecciones (301)

- `/cart/` → `/c/`
- `/checkout/` → `/p/`
- `/checkout/shipping/` → `/p/1/`
- `/checkout/payment/` → `/p/2/`
- `/checkout/confirm/` → `/p/3/`
