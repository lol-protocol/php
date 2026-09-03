# Backoffice de actividad de usuarios

Panel de administración para revisar, acción por acción, la actividad de un usuario
como un flujo cronológico, comparando cada acción (duración, monto pagado) contra el
promedio de un "universo" de otros usuarios filtrable por país / grupo de países
(OTAN, BRICS, LATAM, países islámicos, Zona Euro, Espacio Schengen), rango de edad y
género. Requiere iniciar sesión. Interfaz oscura, con colores neón distintos por
tipo de dato mostrado, íconos grandes por acción, y cada acción muestra también la
ruta/archivo del backend que la atendió y la IP de esa sesión (con su país, hora
local y proveedor).

Sistema independiente del protocolo LoL en PHP que vive en la raíz de este repositorio.
Para usar el panel día a día, ver `MANUAL-USUARIO.md`; esto de acá es la referencia
técnica/arquitectura.

## Arquitectura

Tres componentes, cada uno en su propia carpeta, sin dependencias externas más allá
del JDK/PHP/navegador. Nombres en español simple; se mantienen en inglés los nombres
de lenguaje (php/java/css/js) y las convenciones estándar que el propio tooling
espera literalmente (`index.php`, `README.md`, `public/`→`publico/` es la excepción
que sí se tradujo, `src/`→`codigo/` también). Ningún archivo de código pasa las 100
líneas — todo está modularizado en piezas chicas y enfocadas.

```
sistema-nuevo/
├── MANUAL-USUARIO.md                Guía de uso del panel (no técnica)
│
├── datos/                          Datos semilla + generador (modularizado)
│   ├── generar-datos-semilla.php      Orquesta la generación (requiere generador/*.php)
│   ├── generador/                     Catálogos, generación de flujo/registro, saneo, escritura
│   ├── usuarios.json                  60 usuarios sintéticos (país, edad, género)
│   ├── acciones-crudas.json           Log "crudo": formatos inconsistentes a propósito
│   ├── acciones.json                  Log saneado (esquema canónico), en flujos por usuario
│   ├── acciones-planas.csv            Igual que acciones.json pero desnormalizado, para Java
│   ├── grupos-de-paises.json          Presets de grupos de países + catálogo de países
│   ├── monedas.json                   Moneda por país + cotización fija frente al USD
│   ├── esquema.sql                    Referencia: este mismo modelo como tablas SQL
│   ├── esquema-datos-ejemplo.sql      Filas de ejemplo para esquema.sql
│   └── ejemplos/                      muestra-antes-despues.json, peticiones-api.http
│
├── servicio-estadisticas-java/     Microservicio de estadísticas (Java, solo JDK)
│   ├── ServicioEstadisticas.java      main(): carga el CSV, levanta el servidor HTTP
│   ├── Accion.java                    record de una fila ya aplanada
│   ├── ManejadorEstadisticas.java     GET /stats: filtra y agrega
│   └── UtilHttp.java                  Parseo de query string, respuesta JSON
│
├── servidor-php/                   API backend (PHP)
│   ├── publico/index.php              Front controller: CORS+cookies, preflight, rutas /api/*
│   └── codigo/
│       ├── AlmacenDatos.php           Lee usuarios/acciones/grupos-de-paises (JSON)
│       ├── ClienteEstadisticas.php    Llama al servicio de estadísticas por HTTP
│       ├── autenticacion.php          Sesión simple (login/logout, un solo usuario)
│       ├── credenciales.php           Usuario demo + hash de contraseña (bcrypt)
│       ├── saneador.php               Punto de entrada del saneador (ver saneador/)
│       ├── saneador/                  primitivas, marca-temporal, accion(-monto/-campos/-ip)
│       ├── api.php                    Punto de entrada de los endpoints (ver api/)
│       └── api/                       sesion.php, usuarios.php, linea-tiempo.php, ayudantes.php
│
├── interfaz/                       Panel de administración (HTML/CSS/JS, sin frameworks)
│   ├── index.php                      Ensambla partes/*.php + enlaza los .css
│   ├── partes/                        pantalla-login.php, topbar.php, panel-principal.php
│   ├── css/                           11 archivos chicos (base, login, topbar, tarjetas...)
│   └── js/                            Módulos ES: nucleo, formato, sesion, selectores,
│                                       tarjeta-usuario, metricas, linea-tiempo, aplicacion (entry)
│
└── ejecutar.sh                     Levanta los 3 servicios de una
```

**Flujo de una request:** el navegador solo habla con el backend PHP. El backend PHP
lee el log de acciones del usuario elegido y, por cada tipo de acción distinto que
aparece en su timeline, le pregunta una vez al microservicio Java el promedio de ese
tipo de acción para el universo de comparación actual (país/grupo, edad, género —
excluyendo siempre al propio usuario). Con eso arma el timeline enriquecido con el
delta % de cada acción contra ese promedio.

## Interfaz: fondo negro, colores neón por tipo de dato

Tema oscuro (`interfaz/css/base.css`), tipografía monoespaciada, textos con glow,
íconos grandes por acción (3.6rem). Cada tipo de dato que aparece en una tarjeta
tiene su propio color neón fijo (ver la leyenda "Colores por tipo de dato" en la
barra lateral de la app):

| Dato                        | Color   |
|------------------------------|---------|
| fecha/hora                   | azul    |
| duración                     | cian    |
| monto                        | dorado  |
| comentario                   | magenta |
| ruta/archivo, endpoint+HTTP  | naranja |
| tamaño de archivo            | violeta |
| IP (verde) / IP que no coincide con el país del usuario (rojo) | verde / rojo |

Los badges de comparación (mejor/peor/≈ promedio) usan verde/rojo neón, igual que antes.

## Fechas y horas

Formato canónico `Y-m-d H:i:s` (ej. `2026-09-03 19:47:10`), siempre en UTC — mismo
orden que ISO-8601 así que sigue ordenando bien como texto aunque no lleve `T`/`Z`.
Lo define `saneador_marca_temporal()` en
`servidor-php/codigo/saneador/marca-temporal.php`; el frontend lo muestra tal cual,
sin reformatear.

## Ruta/archivo por acción

Cada tipo de acción tiene asociada una ruta de backend fija (`datos/generador/tipos-accion.php`,
p. ej. `payment` → `/app/checkout/pago.php`) que se guarda en el campo `path` de
**toda** acción, sin excepción. Las llamadas a la API (`api_call`) además muestran
su `endpoint`/`http_status` específico — es un detalle más fino sobre la misma idea
("dónde pasó esto"), por eso comparten color en la interfaz.

## IP y datos derivados de la IP

Cada sesión (no cada acción — la IP no cambia entre acciones de una misma sesión)
tiene una IP sintética, un país de IP y un proveedor ficticio
(`datos/generador/catalogo-red.php`). El país de la IP coincide con el país
declarado del usuario el 80% de las veces; el otro 20% es deliberadamente distinto,
para simular VPN/proxy/viaje — el backend lo marca (`ip_mismatch`) y la interfaz lo
resalta en rojo. La hora local (`ip_local_time`) se calcula aplicando el huso horario
del país de la IP sobre el timestamp UTC ya saneado — es un dato derivado, no algo
que venga así en el log crudo. Los husos horarios son fijos e ilustrativos (sin
horario de verano), igual que las cotizaciones de moneda.

## Autenticación

Sesión simple por cookie (PHP `session`), sin roles ni registro — pensada para un
prototipo, no para producción (sin CSRF, sin límite de intentos de login).

- Usuario demo: **admin** / **admin123** (hash bcrypt en `credenciales.php`, la
  contraseña nunca se compara ni se guarda en texto plano).
- Como la interfaz y la API corren en puertos distintos, la cookie de sesión viaja
  entre orígenes: `servidor-php/publico/index.php` responde el preflight CORS (OPTIONS)
  y refleja `http://localhost:8082` como único origen permitido con
  `Access-Control-Allow-Credentials`, en vez de usar `*` (que el navegador rechaza
  para requests con credenciales, y que sería una configuración CORS abierta).

## Montos: moneda local y USD

Cada país tiene asignada una moneda (`datos/monedas.json`) y una cotización fija e
ilustrativa frente al USD (no hay acceso a una API de cotizaciones en tiempo real).
Los pagos y reembolsos se generan y guardan en **moneda local** — como llegaría un
pago real — y el saneador deriva `amount_usd` a partir de esa cotización.

El servicio de estadísticas en Java compara **solo en USD**: promediar montos en
monedas distintas sin normalizar no tendría sentido. La interfaz muestra ambos
valores, p. ej. `₹5.412 (≈ 65,19 US$)`, pero el badge de comparación (más
caro/barato) se calcula siempre sobre el monto en USD.

## Datos sintéticos y saneamiento

Los logs de acciones se generan en dos pasos, para poder mostrar un saneador real:

1. **`acciones-crudas.json`**: 14 tipos de acción (login, password_reset, search,
   view_product, api_call, profile_update, add_to_cart, checkout_start, payment,
   refund, review_submit, support_ticket, file_upload, logout) con formatos
   deliberadamente inconsistentes:
   - Fechas en 5 formatos distintos (ISO+Z, ISO+offset, `Y-m-d H:i:s`, epoch en
     segundos, epoch en milisegundos).
   - Números a veces como texto, con símbolo de moneda o separador de miles
     (`"$1,234.56"`), o con espacios de más.
   - Mayúsculas/minúsculas y espacios inconsistentes en campos de texto.
   - Comentarios de reseñas/tickets con HTML y scripts colados (`<script>...`,
     `<img onerror=...>`, entidades HTML codificadas) para poder mostrar que el
     saneador los neutraliza.
   - IP con un `:puerto` colado, país de IP con mayúsculas/espacios inconsistentes.
   - Una fracción de registros con campos faltantes o inválidos a propósito.

2. **`saneador/`** limpia cada registro: normaliza fechas, parsea números con
   formato inconsistente, valida tipo de acción/moneda/código HTTP/IP/país de IP
   contra catálogos conocidos, decodifica entidades HTML y **elimina cualquier
   etiqueta** (`strip_tags`) de los campos de texto libre antes de guardarlos, y
   descarta el registro completo si le faltan campos esenciales. El resultado es
   `acciones.json`, lo único que lee el backend en vivo — el saneamiento corre una
   sola vez al generar los datos. La lógica está modularizada: `primitivas.php`
   (validadores sueltos), `marca-temporal.php` (fechas), `accion-monto.php`,
   `accion-campos.php`, `accion-ip.php` (uno por grupo de campos) y `accion.php`
   (orquesta todo).

   Se puede verificar que funcionó: `acciones-crudas.json` sí contiene fragmentos
   como `<script>` o `<img onerror=`; en `acciones.json` no queda ninguno. Un
   puñado de ejemplos ya comparados, antes y después, está en
   `datos/ejemplos/muestra-antes-despues.json`.

## Si esto fuera una base de datos relacional

El sistema real usa archivos JSON/CSV (ver arriba), pero `datos/esquema.sql` tiene
una propuesta de cómo se vería el mismo modelo como tablas SQL (MySQL/MariaDB):
`paises`, `monedas`, `grupos_paises`/`grupo_pais`, `tipos_accion`, `usuarios`,
`administradores` (cuentas del backoffice, separadas de `usuarios`) y `acciones`
(con sus columnas nulables según el tipo: `monto_local`/`moneda_codigo`/`monto_usd`
solo en pagos, `comentario` solo en reseñas, etc.). `datos/esquema-datos-ejemplo.sql`
tiene un puñado de INSERT de ejemplo sobre ese esquema. Es solo una referencia — no
hay ninguna base de datos corriendo ni conectada a este sistema.

## Ejemplos

`datos/ejemplos/`:
- `muestra-antes-despues.json`: 7 registros reales del log crudo, elegidos a mano
  para mostrar cada tipo de inconsistencia (fecha en epoch, monto con símbolo,
  usuario vacío, tipo desconocido, HTML inseguro en comentario, IP con puerto)
  junto a cómo queda cada uno después del saneador (o `null` si se descarta).
- `peticiones-api.http`: ejemplos de todos los endpoints, en formato `.http`
  (extensión "REST Client" de VS Code, o el cliente HTTP de JetBrains) — login,
  timeline con distintos `scope`, logout.

## Cómo correrlo

Requiere PHP (probado en 8.4) y JDK (probado en 21). Nada más.

**Opción rápida** — desde `sistema-nuevo/`:

```bash
./ejecutar.sh
```

Deja corriendo:
- Panel de administración: http://localhost:8082 (usuario demo: `admin` / `admin123`)
- API backend (PHP): http://localhost:8000/api/session
- Stats service (Java): http://localhost:8081/stats?type=login

**Manual**, en 3 terminales separadas desde `sistema-nuevo/`:

```bash
# 1. Datos semilla (solo hace falta una vez, o para regenerar)
php datos/generar-datos-semilla.php

# 2. Microservicio de estadísticas (Java) — son 4 archivos, hay que compilarlos juntos
cd servicio-estadisticas-java && javac *.java && java ServicioEstadisticas

# 3. API backend (PHP)
php -S localhost:8000 -t servidor-php/publico servidor-php/publico/index.php

# 4. Panel de administración
php -S localhost:8082 -t interfaz
```

Abrir http://localhost:8082.

## API (backend PHP)

- `POST /api/login` — `{"username": "...", "password": "..."}` → inicia sesión.
- `POST /api/logout` — cierra sesión.
- `GET /api/session` — `{"authenticated": bool, "username": ?string}` (pública, no requiere login).
- `GET /api/users` — lista de usuarios para el selector. **Requiere sesión.**
- `GET /api/groups` — presets de país + catálogo de países. **Requiere sesión.**
- `GET /api/timeline?user_id=u001&scope=preset:latam&age_min=18&age_max=65&gender=all`
  — timeline del usuario con cada acción enriquecida con `cohort` (promedio del
  universo), `duration_delta_pct`, `amount_delta_pct` e `ip_mismatch` (bool: la IP
  de esa acción no coincide con el país declarado del usuario). **Requiere sesión.**
  - `scope`: `all` | `preset:<otan|brics|latam|islamicos|euro|schengen>` | `country:<CODE>`
  - `gender`: `all` | `M` | `F` | `O`

## Notas / alcance

- Los datos son sintéticos (generados con semilla fija) para poder probar el sistema
  sin depender de logs reales; `generar-datos-semilla.php` documenta cómo se arman.
- Autenticación de un solo usuario, sin roles: alcanza para el prototipo, no para un
  despliegue real (ver "Autenticación" arriba).
- Si el servicio de estadísticas en Java no está corriendo, el backend PHP no rompe:
  cada acción queda sin comparación (`cohort: null`) y el panel lo indica con un aviso.
- Las cotizaciones de moneda y los husos horarios son fijos e ilustrativos, no de
  mercado/geolocalización en vivo.
- La generación usa una fecha ancla fija (no `time()`) para que `datos/*.json` salga
  byte a byte igual entre corridas con la misma semilla — no depende de cuándo se
  ejecute `generar-datos-semilla.php`.
