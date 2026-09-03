# Backoffice de actividad de usuarios

Panel de administración para revisar, acción por acción, la actividad de un usuario
como un flujo cronológico, comparando cada acción (duración, monto pagado) contra el
promedio de un "universo" de otros usuarios filtrable por país / grupo de países
(OTAN, BRICS, LATAM, países islámicos, Zona Euro, Espacio Schengen), rango de edad y
género.

Sistema independiente del protocolo LoL en PHP que vive en la raíz de este repositorio.

## Arquitectura

Tres componentes, cada uno en su propia carpeta, sin dependencias externas más allá
del JDK/PHP/navegador (nada que instalar vía Composer/Maven/npm):

```
new-system/
├── data/                    Datos semilla + generador
│   ├── generate_seed_data.php   Genera todo lo de abajo (reproducible, semilla fija)
│   ├── users.json               60 usuarios sintéticos (país, edad, género)
│   ├── actions.json             ~900 acciones de log, en flujos de sesión por usuario
│   ├── actions_flat.csv         Igual que actions.json pero desnormalizado, para Java
│   └── country_groups.json      Presets de grupos de países + catálogo de países
│
├── stats-service-java/      Microservicio de estadísticas (Java, solo JDK)
│   └── StatsService.java        GET /stats → promedio de duración/monto de un
│                                 tipo de acción para un universo de comparación
│
├── backend-php/             API backend (PHP)
│   ├── public/index.php         Front controller: rutas /api/*
│   └── src/
│       ├── DataStore.php        Lee users.json / actions.json / country_groups.json
│       ├── StatsClient.php      Llama al stats-service-java por HTTP
│       └── Api.php              Lógica de los endpoints
│
├── frontend/                 Panel de administración (HTML/CSS/JS, sin frameworks)
│   ├── index.html
│   ├── css/style.css
│   └── js/app.js                Llama a la API PHP con fetch()
│
└── run.sh                    Levanta los 3 servicios de una
```

**Flujo de una request:** el navegador solo habla con el backend PHP (mismo origen
que sirve la API). El backend PHP lee el log de acciones del usuario elegido y, por
cada tipo de acción distinto que aparece en su timeline, le pregunta una vez al
microservicio Java el promedio de ese tipo de acción para el universo de comparación
actual (país/grupo, edad, género — excluyendo siempre al propio usuario). Con eso arma
el timeline enriquecido con el delta % de cada acción contra ese promedio.

Los montos están normalizados a USD (`amount_usd`) en los datos semilla para que las
comparaciones entre países sean válidas sin necesitar un módulo de conversión de
divisas, que quedó fuera de alcance.

## Cómo correrlo

Requiere PHP (probado en 8.4) y JDK (probado en 21). Nada más.

**Opción rápida** — desde `new-system/`:

```bash
./run.sh
```

Deja corriendo:
- Panel de administración: http://localhost:8082
- API backend (PHP): http://localhost:8000/api/users
- Stats service (Java): http://localhost:8081/stats?type=login

**Manual**, en 3 terminales separadas desde `new-system/`:

```bash
# 1. Datos semilla (solo hace falta una vez, o para regenerar)
php data/generate_seed_data.php

# 2. Microservicio de estadísticas (Java)
cd stats-service-java && java StatsService.java

# 3. API backend (PHP)
php -S localhost:8000 -t backend-php/public backend-php/public/index.php

# 4. Panel de administración (estático)
php -S localhost:8082 -t frontend
```

Abrir http://localhost:8082.

## API (backend PHP)

- `GET /api/users` — lista de usuarios para el selector.
- `GET /api/groups` — presets de país + catálogo de países, para el selector de universo.
- `GET /api/timeline?user_id=u001&scope=preset:latam&age_min=18&age_max=65&gender=all`
  — timeline del usuario con cada acción enriquecida con `cohort` (promedio del
  universo), `duration_delta_pct` y `amount_delta_pct`.
  - `scope`: `all` | `preset:<otan|brics|latam|islamicos|euro|schengen>` | `country:<CODE>`
  - `gender`: `all` | `M` | `F` | `O`

## Notas / alcance

- Los datos son sintéticos (generados con semilla fija) para poder probar el sistema
  sin depender de logs reales; `generate_seed_data.php` documenta cómo se arman.
- No hay autenticación: es un prototipo funcional del backoffice, no un sistema listo
  para producción.
- Si el stats-service-java no está corriendo, el backend PHP no rompe: cada acción
  queda sin comparación (`cohort: null`) y el panel lo indica con un aviso.
