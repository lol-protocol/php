# Panel de Cobros, Ingresos y Funnel

Pequeño sistema en PHP (sin framework) para analizar:

- **Ingresos** devengados (facturación) vs. **cobros** reales (caja).
- **Cartera** pendiente, con antigüedad de saldo (aging: al día / 1-30 / 31-60 / 61+ días).
- **Pagos**: por mes y por método (transferencia, tarjeta, efectivo).
- **Funnel de conversión**: visitante → registrado → lead → cliente, con tasas por etapa y por canal de adquisición.

## Requisitos

- PHP >= 8.1 con `pdo_sqlite`
- Composer (solo se usa para el autoload PSR-4, no hay dependencias externas)

## Instalación

```bash
composer install
php database/seed.php      # crea database/database.sqlite y carga datos de ejemplo
php -S localhost:8000 -t public
```

Abrí `http://localhost:8000` en el navegador.

Volver a correr `php database/seed.php` en cualquier momento reconstruye el esquema
y regenera los datos de ejemplo desde cero (es reproducible: usa una semilla fija).

## Estructura

```
public/            front controller (index.php) + CSS
src/
  Controllers/      un controlador por sección (dashboard, cobros, pagos, funnel)
  Repositories/      consultas y agregaciones SQL por entidad
  Database.php        conexión PDO a SQLite (singleton)
  Router.php, View.php, Filtros.php, Config.php, helpers.php
database/
  schema.sql          esquema de la base
  seed.php             generador de datos de ejemplo
views/                plantillas PHP (una carpeta por sección)
```

## Modelo de datos

- `clientes`: clientes ya convertidos (vía funnel o cartera preexistente).
- `usuarios_funnel`: cada visitante que entra al funnel, con la fecha en que alcanzó
  cada etapa (`fecha_visita`, `fecha_registro`, `fecha_lead`, `fecha_conversion`) y
  el canal de adquisición.
- `facturas`: ingresos devengados (monto, emisión, vencimiento).
- `pagos`: cobros reales, opcionalmente ligados a una factura (`factura_id` puede
  ser `NULL` para anticipos/pagos sueltos).

El estado de cada factura (pagada / parcial / pendiente / vencida) se calcula
dinámicamente a partir de sus pagos y la fecha de vencimiento, no se guarda en la
base — así nunca queda desincronizado.

## Notas de diseño

- Sin JavaScript ni librerías de gráficos externas: los charts son barras CSS con
  tooltip nativo (`data-tooltip` + `:hover`/`:focus`), pensado para funcionar sin
  build step ni conexión a internet.
- Sigue una paleta validada para accesibilidad (contraste y daltonismo): colores
  categóricos fijos para series (ingresos/cobros), rampa secuencial para las etapas
  del funnel, y colores de estado reservados para la antigüedad de cartera.
- Modo oscuro automático vía `prefers-color-scheme`.
