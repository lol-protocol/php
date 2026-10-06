# Backoffice de actividad de usuarios

Panel de administración para revisar, acción por acción, la actividad de un usuario
como un flujo cronológico, comparando cada acción (duración, monto pagado) contra el
promedio de un "universo" de otros usuarios filtrable por país / grupo de países
(OTAN, BRICS, LATAM, países islámicos, Zona Euro, Espacio Schengen), rango de edad,
género y tipo de acción. Requiere iniciar sesión. Interfaz oscura, con colores neón
distintos por tipo de dato mostrado, íconos grandes por acción, gráfico de evolución
temporal, un panel de alertas proactivas (IPs que no coinciden con el país declarado)
y selector de idioma ES/EN. Cada acción muestra también la ruta/archivo del backend
que la atendió y la IP de esa sesión (con su país, hora local y proveedor). Los
nombres de usuario se generan en varios alfabetos (latino, japonés, árabe, hebreo,
chino, coreano, cirílico) y se leen bien sin importar el idioma elegido para la
interfaz.

Sistema independiente del protocolo LoL en PHP que vive en la raíz de este repositorio.
Para usar el panel día a día, ver `manual_usuario.md`; esto de acá es la referencia
técnica/arquitectura.

## Arquitectura

Tres componentes, cada uno en su propia carpeta, sin dependencias externas más allá
del JDK/PHP/PostgreSQL/navegador (las pruebas automatizadas usan además Node, ya
instalado, sin sumar paquetes nuevos — ver "Pruebas automatizadas" más abajo).
Nombres en español simple; se mantienen en inglés los nombres de lenguaje
(php/java/css/js) y las convenciones estándar que el propio tooling espera
literalmente (`index.php`, `README.md`, `public/`→`publico/` es la excepción que sí
se tradujo, `src/`→`codigo/` también). La mayoría de los archivos de código no pasan
las 100 líneas — el objetivo es que cada uno sea una pieza chica y enfocada, no un
número exacto. Los pocos que sí las pasan son casos donde partirlos sería peor:
un diccionario i18n plano (`es.js`/`en.js`), hojas de estilo de un solo componente
(`alertas.css`, `topbar.css`), un cliente HTTP cohesivo con un solo estado interno
compartido (`ClienteEstadisticas.php`), las dos reglas de alerta con sus
consultas (`AlmacenAlertas.php`) o una suite e2e ya acotada a un tema
(`panel-nuevas-features.e2e.cjs`) — fragmentarlos solo para bajar el número
cambiaría "piezas enfocadas" por "piezas dispersas".

```
sistema-nuevo/
├── manual_usuario.md                Guía de uso del panel (no técnica)
│
├── datos/                          Datos semilla + generador (modularizado)
│   ├── generar-datos-semilla.php      Orquesta la generación (requiere generador/*.php);
│   │                                   recrea la base desde cero (borra también notas y filtros)
│   ├── sembrar-si-falta.php           Lo mismo, pero solo si la base no está sembrada
│   ├── generador/                     Catálogos, generación de flujo/registro, saneo,
│   │                                   escritura de archivos y carga a PostgreSQL
│   ├── usuarios.json                  60 usuarios sintéticos (país, edad, género, nombre)
│   ├── acciones-crudas.json           Log "crudo": formatos inconsistentes a propósito
│   ├── acciones.json                  Log saneado (esquema canónico), en flujos por usuario
│   ├── acciones-planas.csv            Igual que acciones.json pero desnormalizado, para Java
│   ├── grupos-de-paises.json          Presets de grupos de países + catálogo de países
│   ├── monedas.json                   Moneda por país + cotización fija frente al USD
│   ├── esquema.sql                    DDL PostgreSQL: catálogos (monedas, países, grupos, tipos)
│   ├── esquema-nucleo.sql             DDL PostgreSQL: usuarios, administradores, acciones
│   ├── esquema-datos-ejemplo.sql      Filas de ejemplo, van después de esquema*.sql (no las carga el sistema)
│   └── ejemplos/                      muestra-antes-despues.json, peticiones-api.http
│
├── preparar-postgres.sh            Deja PostgreSQL listo (servicio, rol, base) — idempotente
│
├── servicio-estadisticas-java/     Microservicio de estadísticas (Java, solo JDK)
│   ├── ServicioEstadisticas.java      main(): carga el CSV, arranca el watcher, levanta el HTTP
│   ├── CargadorAcciones.java          Lee el CSV + watcher que recarga sola si cambia el mtime
│   ├── Accion.java                    record de una fila ya aplanada
│   ├── ManejadorEstadisticas.java     GET /stats: filtra el cohort según los query params
│   ├── EstadisticasCalculo.java       Agrega avg/mediana/p90 del cohort filtrado
│   ├── JsonBuilder.java               Arma objetos JSON campo por campo (sin String.format posicional)
│   └── UtilHttp.java                  Parseo de query string, escape JSON, respuesta HTTP
│
├── servidor-php/                   API backend (PHP), lee de PostgreSQL
│   ├── publico/index.php              Front controller: CORS+cookies, preflight, despacha por la tabla de rutas
│   └── codigo/
│       ├── ConexionBd.php             Singleton PDO hacia PostgreSQL
│       ├── AlmacenDatos.php           Usuarios (paginado/buscable) y grupos de países
│       ├── AlmacenAcciones.php        Acciones de un usuario: paginado, filtro por tipo,
│       │                               resumen diario para el gráfico
│       ├── AlmacenAlertas.php         Las dos reglas de alerta (IP fuera del país, cambio de país
│       │                               imposible): las usan el panel de alertas y el KPI
│       ├── AlmacenConfiguracion.php   Config de alertas (habilitadas/umbral), clave-valor
│       ├── AlmacenFiltros.php         CRUD de combinaciones de filtro guardadas
│       ├── AlmacenKpis.php            Métricas agregadas del dashboard inicial
│       ├── AlmacenNotas.php           Notas por acción (guardar es upsert, texto vacío borra)
│       ├── ClienteEstadisticas.php    Llama al servicio de estadísticas por HTTP
│       ├── Universo.php               Contra quién se compara: países, edad, género y el usuario excluido
│       ├── autenticacion.php          Sesión + CSRF (login/logout, un solo usuario)
│       ├── AlmacenIntentosLogin.php   Rate limiting de /api/login por IP
│       ├── AlmacenAdministradores.php Lee la tabla administradores (login en vivo)
│       ├── credenciales.php           Solo semilla: carga admin/hash a la tabla al generar datos
│       ├── saneador.php               Punto de entrada del saneador (ver saneador/)
│       ├── saneador/                  primitivas, marca-temporal, accion(-monto/-campos/-ip)
│       ├── api.php                    Punto de entrada de los endpoints (ver api/)
│       ├── rutas.php                  La tabla de rutas: qué función atiende cada una y cuáles exigen sesión
│       └── api/                       sesion, usuarios (+ groups, action-types), alertas(-config),
│                                       linea-tiempo (+ linea-tiempo-cohortes), filtros, kpis,
│                                       notas, ayudantes (api_responder/api_error, CSRF, delta %)
│
├── interfaz/                       Panel de administración (HTML/CSS/JS, sin frameworks)
│   ├── index.php                      Ensambla partes/*.php + enlaza los .css
│   ├── partes/                        pantalla-login.php, topbar.php, panel-principal.php
│   ├── css/                           18 archivos chicos (base, login, topbar, tarjetas,
│   │                                   paginacion, grafico, alertas, modal...)
│   └── js/                            Módulos ES: nucleo, formato, sesion, selectores,
│       ├── i18n/es.js, i18n/en.js         tarjeta-usuario, metricas, linea-tiempo, paginacion,
│       ├── idioma.js, idioma-refrescar.js grafico, alertas, idioma(-refrescar), errores, aplicacion,
│       └── aplicacion.js, eventos.js      eventos (entry point), configuracion-alertas, filtros,
│                                           controles-filtro (los 5 filtros de la barra, por nombre),
│                                           respuesta-timeline (pinta lo que trae /api/timeline),
│                                           inactividad, modal (prompt/confirm/aviso propios, ver abajo),
│                                           kpis, nota-bloque, notas, notificaciones
│
├── pruebas/                        Pruebas automatizadas, sin dependencias nuevas
│   ├── marco-pruebas.php / ejecutar-php.php     Framework mínimo + ayudantes (procesos, servidores falsos) + runner
│   ├── php/                                     Unit tests del saneador, los helpers y la tabla de rutas de la API,
│   │                                             la muestra y ejecutar.sh
│   ├── ejecutar-integracion.php                 Runner de integración (Almacen*.php, PostgreSQL real)
│   ├── ayudantes-integracion.php                Schema descartable para probar los esquemas SQL
│   ├── php-integracion/                         Unit tests de Almacen*.php, ClienteEstadisticas.php y la siembra
│   ├── ejecutar-js.sh                           Runner (node:test, ya viene con Node)
│   ├── js/                                      Unit tests de formato, idioma, errores, alertas, los controles
│   │                                             de filtro, el aviso y que cada cosa viva en un solo módulo
│   ├── ejecutar-e2e.sh                          Runner e2e (Playwright, ya instalado)
│   └── e2e/                                     Login, timeline, paginación, filtros,
│                                                 gráfico, alertas, idioma — panel completo;
│                                                 los ejemplos .http contra la API real, las rutas
│                                                 sin sesión y los valores de comparación contra PostgreSQL
│
└── ejecutar.sh                     Levanta PostgreSQL, siembra los datos si faltan y los 3
                                    servicios (--regenerar: recrea los datos desde cero)
```

**Flujo de una request:** el navegador solo habla con el backend PHP. El backend PHP
lee el log de acciones del usuario elegido desde PostgreSQL y, por cada tipo de acción
distinto que aparece en la página actual del timeline, le pregunta una vez al
microservicio Java el promedio de ese tipo de acción para el universo de comparación
actual (el objeto `Universo`: país/grupo, edad, género — excluyendo siempre al propio usuario). Con eso arma
el timeline enriquecido con el delta % de cada acción contra ese promedio.

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

Lo que comparten varios componentes vive en `base.css`: las variables del brillo
(`--accent-glow` para el foco y el mouse encima, `--card-glow` para los cuadros con
borde de acento) y dos piezas, `.campo` (campo de texto o desplegable: el login, los
modales y la barra de filtros) y `.caja-neon` (la tarjeta del login y los modales). Antes
el estilo de los campos estaba escrito tres veces, el brillo del foco cinco y el
resplandor de los cuadros dos. Un campo nuevo solo tiene que llevar `class="campo"`.

## Fechas y horas

Formato canónico `Y-m-d H:i:s` (ej. `2026-09-03 19:47:10`), siempre en UTC — mismo
orden que ISO-8601 así que sigue ordenando bien como texto aunque no lleve `T`/`Z`.
Lo define `saneador_marca_temporal()` en
`servidor-php/codigo/saneador/marca-temporal.php`; el frontend lo muestra tal cual,
sin reformatear (ni siquiera al cambiar de idioma — ver más abajo).

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

Además del aviso pasivo en cada tarjeta, el panel de alertas (ver más abajo) muestra
esto de forma **proactiva**, sin tener que elegir un usuario primero.

## Autenticación

Sesión simple por cookie (PHP `session`), sin roles ni registro — pensada para un
prototipo, no para producción.

- Usuario demo: **admin** / **admin123**. El login valida contra la tabla
  `administradores` (`AlmacenAdministradores`), no contra un archivo -- `credenciales.php`
  es solo el dato semilla que carga esa fila una vez al generar los datos (ver "Base
  de datos"). La contraseña nunca se compara ni se guarda en texto plano (hash bcrypt,
  `password_verify()`); si el usuario no existe, igual se corre `password_verify()`
  contra un hash dummy en vez de cortar antes, para que el tiempo de respuesta no
  filtre qué usuarios existen.
- Como la interfaz y la API corren en puertos distintos, la cookie de sesión viaja
  entre orígenes: `servidor-php/publico/index.php` responde el preflight CORS (OPTIONS)
  y refleja como único origen permitido `http://localhost:8082` (o
  `BACKOFFICE_CORS_ORIGEN`, mismo patrón de variable de entorno con default que
  `BACKOFFICE_BD_*` más abajo) con `Access-Control-Allow-Credentials`, en vez de
  usar `*` (que el navegador rechaza para requests con credenciales, y que sería
  una configuración CORS abierta).
- **CSRF**: `auth_marcar_autenticado()` regenera un token en cada login
  (`bin2hex(random_bytes(32))`, guardado en `$_SESSION`). El frontend lo recibe
  en la respuesta de `/api/login` y `/api/session`, y `postJson`/`deleteJson`
  (`sesion.js`) lo mandan solos en el header `X-CSRF-Token` en cada POST/DELETE
  contra la API; el backend lo valida con `hash_equals()` — sin token válido,
  403. Cubre logout, filtros, notas y configuración de alertas por igual.
- **Auto-logout por inactividad**: `interfaz/js/inactividad.js` cierra la sesión
  a los 30 minutos sin clicks/movimiento/teclas/scroll, avisando con un modal
  ("Continuar activo" / "Cerrar sesión ahora") un minuto antes. Con el aviso
  abierto, mover el mouse, scrollear o tocar la pantalla no lo cierran, porque así
  se llega a los botones: antes desaparecía con el primer movimiento y "Cerrar
  sesión ahora" no se podía pulsar con el mouse. Quien elige "Cerrar sesión ahora" ve
  el login como con el botón "Cerrar sesión" (sin mensaje, los dos usan
  `cerrarSesion()` de `sesion.js`); solo el cierre automático dice que fue por
  inactividad.
- **Rate limiting contra fuerza bruta**: `AlmacenIntentosLogin.php` cuenta
  intentos fallidos por IP (no por usuario: hay uno solo) en la tabla
  `intentos_login`. Al 5to fallo consecutivo, esa IP queda bloqueada 15 minutos
  — `POST /api/login` responde 429 incluso si en ese momento manda la contraseña
  correcta, hasta que expire el bloqueo. Un login exitoso resetea el contador.
  Sin cron en este proyecto, la tabla se poda sola: cada llamada a
  `registrarFallo()` tiene 1/20 de probabilidad de disparar un DELETE de filas
  con más de 24h sin fallar de nuevo, así que no crece sin límite.

## Montos: moneda local y USD

Cada país tiene asignada una moneda (`datos/monedas.json`) y una cotización fija e
ilustrativa frente al USD (no hay acceso a una API de cotizaciones en tiempo real).
Los pagos y reembolsos se generan y guardan en **moneda local** — como llegaría un
pago real — y el saneador deriva `amount_usd` a partir de esa cotización.

El servicio de estadísticas en Java compara **solo en USD**: promediar montos en
monedas distintas sin normalizar no tendría sentido. La interfaz muestra ambos
valores, p. ej. `₹5.412 (≈ 65,19 US$)`, pero el badge de comparación (más
caro/barato) se calcula siempre sobre el monto en USD. El formato del número (coma o
punto decimal, símbolo antes o después) sigue el idioma elegido en la interfaz.

## Paginación y filtro por tipo de acción

`GET /api/users` y `GET /api/timeline` devuelven páginas (`LIMIT`/`OFFSET` en SQL, no
todo el resultado de una vez), con `page`/`per_page` como query params y una
`pagination: {total, page, per_page, total_pages}` en la respuesta. El timeline además
acepta `type=<clave>` (p. ej. `type=payment`) para quedarse solo con un tipo de
acción — el desplegable "Tipo de acción" de la barra superior arma este filtro; se
combina con el resto (universo, edad, género) y cambiar cualquiera vuelve a la
página 1.

### Filtros guardados

Cualquier combinación de universo/edad/género/tipo se puede nombrar y guardar
(`filtros_guardados` en PostgreSQL: `AlmacenFiltros.php`, `GET/POST /api/filtros`,
`DELETE /api/filtros/{id}`) y volver a aplicar después desde un desplegable en la
barra superior. El `scope` se guarda tal cual sale de `#scope-select`
(`country:XX`/`preset:XX`/`all`) — el mismo valor se usa para poblar el selector y
para reconstruirlo al aplicar el filtro, sin una capa de traducción intermedia que
pueda desincronizarse. Guardar (nombre) y eliminar (confirmación) usan
`interfaz/js/modal.js` -- un modal propio con la estética del panel, no los
`prompt()`/`confirm()` nativos del navegador. `abrirModal()` es el modal general (título,
mensaje, campo de texto opcional y los botones que se le pasen; devuelve la elección y un
`descartar()`), y `modalPrompt()`/`modalConfirmar()` son dos usos. El aviso de inactividad
es otro: `inactividad.js` lo abre con sus dos botones en vez de tener su propio cuadro y su
propia hoja de estilos (`inactividad.css`, que tenía 10 líneas iguales a `modal.css`). Como
a cualquier modal, se lo cierra con Escape o con un clic afuera (en el aviso, eso es "seguir"),
y abrir uno cierra el que estuviera abierto.

## Gráfico de evolución temporal

Arriba del timeline, un SVG armado a mano (sin librerías de gráficos, coherente con
el resto del proyecto): barras con la cantidad de acciones por día y una línea con
el gasto acumulado en USD, cada una con su propia escala (eje izquierdo/derecho). Lo
arma `AlmacenAcciones->resumenDiario()` con un `GROUP BY marca_temporal::date` en
SQL; el acumulado se calcula en PHP recorriendo los días en orden. Respeta el mismo
filtro de tipo de acción que el timeline.

## Dashboard de KPIs

`GET /api/kpis` (`AlmacenKpis.php`) agrega, para todo el sistema (no un usuario
puntual), usuarios/acciones/gasto totales, usuarios afectados por alguna alerta
activa, y el tipo de acción y país con más acciones. Se pinta como una fila de
tarjetas arriba de todo (`interfaz/js/kpis.js`) apenas se entra, antes incluso
de elegir un usuario — pensado para tener una foto general del sistema de un
vistazo. El tile de alertas suma `total_users_affected` de cada tipo
habilitado (no deduplica un usuario que tenga ambas anomalías) y se resalta en
rojo cuando es mayor a cero.

## Comparación por percentiles

Además del promedio (`avg_duration_ms`/`avg_amount_usd`, que sigue siendo la base
del badge verde/rojo de cada tarjeta), `GET /stats` del microservicio Java devuelve
la **mediana** y el **percentil 90** de duración y monto del universo elegido
(`EstadisticasCalculo.percentile()`, interpolación lineal sobre la lista ordenada,
`null`-safe si el cohort está vacío o no tiene montos). El promedio se puede
distorsionar con pocos valores extremos; la mediana no. El frontend los muestra
como tooltip (`title`) al pasar el mouse por el badge de comparación, sin agregar
otro elemento visual a la tarjeta (sin cohorte, porque el servicio de estadísticas
no respondió, el badge dice "Sin datos de comparación" y no lleva tooltip).

El badge sale del delta % de la acción contra el promedio (`api_delta_pct()`, en
`api/ayudantes.php`; `null` si no hay promedio útil): gris dentro de ±10%, verde si es
menor (más rápido, más barato) y rojo si es mayor. La franja se mide sobre el porcentaje
**tal como se muestra** (entero, `formatPct`/`classifyDelta` en `formato.js`), así un
badge que dice "+10%" nunca es rojo, ni uno que dice "0%" lleva signo.

## Notas por acción

Cada tarjeta del timeline tiene un campo de texto libre (`interfaz/js/nota-bloque.js`
+ `interfaz/js/notas.js` + `notas_acciones` en PostgreSQL, FK a `acciones` con
`ON DELETE CASCADE`) para que el admin deje una observación puntual — no es un
dato de la acción en sí, es metadata operativa. Se guarda solo con un debounce
de 600ms **por acción** (un `Map` de timers, no un debounce compartido: escribir
en una tarjeta no debe cancelar el guardado pendiente de otra) contra
`POST /api/notes`; texto vacío elimina la fila en vez de guardar un string
vacío. Para evitar N+1 requests, la nota viaja como columna más
(`LEFT JOIN notas_acciones`) en la misma consulta paginada de
`AlmacenAcciones->pagina()`, no en una llamada aparte por tarjeta. Un indicador
chico al lado del textarea muestra el estado del guardado (⏳ guardando, ✓
guardado — se oculta solo a los 2s —, ⚠ error) para que quede claro si se
perdió o no lo que se escribió.

## Alertas proactivas

`GET /api/alerts` agrupa, para **todos** los usuarios (no solo el elegido), dos tipos
de anomalía — a diferencia del aviso rojo en cada tarjeta (que hay que ir a buscar
acción por acción), este panel aparece solo en la barra lateral apenas se entra al
sistema, con los usuarios más afectados primero, una sección por tipo. Un clic en
cualquiera de la lista selecciona ese usuario y carga su timeline.

- **IP fuera del país declarado** (`AlmacenAlertas::ipMismatches()`): igual que el
  aviso individual de cada tarjeta, pero agregado por usuario.
- **Cambios de país imposibles** (`AlmacenAlertas::cambiosPaisImposibles()`): dos
  acciones consecutivas del mismo usuario en países distintos separadas por menos
  tiempo del que tomaría viajar entre ellos. La ventana de tiempo que cuenta como
  "imposible" no es fija: sale de la **sensibilidad** configurable (ver debajo),
  interpolada linealmente entre 0.5h (sensibilidad 0) y 4h (sensibilidad 100); el
  hueco tiene que ser estrictamente menor que la ventana.

Las dos reglas viven solo en `AlmacenAlertas` (constantes SQL y `ventanaHoras()`):
el KPI del dashboard las reutiliza con `usuariosConIpFueraDelPais()` y
`usuariosConCambioPaisImposible()`, que cuentan usuarios sin armar el top, y el
`ip_mismatch` de cada tarjeta usa `esIpFueraDelPais()`, la misma regla de IP para
una acción ya leída. Antes `AlmacenKpis` tenía su propia copia de la consulta y de
la fórmula del umbral, con un comentario que pedía cambiar las dos a la vez.

### Configuración de alertas

`configuracion_alertas` en PostgreSQL (`AlmacenConfiguracion.php`,
`GET/POST /api/alerts-config`) guarda, por tipo de alerta, si está habilitada, más
un umbral de sensibilidad único (0-100) que hoy usa `cambiosPaisImposibles()` como
se describió arriba. Se edita desde el botón **⚙** del panel de alertas en la
interfaz (`interfaz/js/configuracion-alertas.js`): checkboxes por tipo + un
slider, sin recargar la página — guardar dispara un `GET /api/alerts` para
refrescar el panel con la configuración nueva.

## Idioma de la interfaz (ES/EN)

Selector ES/EN en la barra superior (`interfaz/js/idioma.js` +
`interfaz/js/i18n/{es,en}.js`), guardado en `localStorage` para que persista entre
visitas. Alcance deliberado:

- **Se traduce**: toda la interfaz fija (etiquetas, botones, leyenda, mensajes) y el
  contenido armado por el frontend (badges, resumen de filtros, paginación, gráfico,
  alertas, tipos de acción como título de cada tarjeta) — se re-renderiza al vuelo,
  sin volver a pedirle datos al backend. También los **errores del backend**: la API
  manda un `codigo` estable con cada error y la interfaz muestra su traducción
  (`err_<codigo>` en los diccionarios, vía `mensajeDeError()` en `errores.js`); un
  código sin traducir cae al texto en español del servidor. Una prueba exige que
  cada código que manda el backend esté en los dos idiomas, y ninguno de más.
- **No se traduce** (es dato, no interfaz): nombres de usuario y de país, presets de
  grupo de países, proveedor de IP, rutas/endpoints del backend — igual que un
  nombre propio no se traduce al cambiar de idioma. Los nombres en japonés/árabe/
  hebreo/etc. se leen bien sin importar el idioma de la interfaz (`unicode-bidi:
  plaintext` para que el navegador detecte solo la dirección de texto mixta).
- Las fechas quedan siempre en `Y-m-d H:i:s` (ver "Fechas y horas" arriba); el
  formato de los montos sí seguí el idioma elegido (separador decimal, posición del
  símbolo).

## Base de datos: PostgreSQL

El backend en vivo lee de PostgreSQL, no de los JSON (que quedan como artefacto
legible + insumo del CSV para Java). `datos/esquema.sql` (catálogos: `monedas`,
`paises`, `grupos_paises`/`grupo_pais`, `tipos_accion`, más `configuracion_alertas`
y `filtros_guardados`, que no dependen de `usuarios`/`acciones`) y
`datos/esquema-nucleo.sql` (`usuarios`, `administradores`, `acciones` y
`notas_acciones`, con `CHECK`/`FOREIGN KEY` según el tipo) son el DDL real que
corre cada vez que se generan los datos —
`datos/generador/cargar-postgres*.php`, orquestado desde `cargar-postgres.php`, dropea
y recrea las tablas y carga todo dentro de una transacción. Los siete `cargar_*` arman
sus filas ("columna => valor", los nombres de las columnas junto a lo que reciben) y las
insertan con `insertar_filas()` (`generador/insertar-filas.php`), en vez de repetir cada
uno el `prepare`/bucle/`execute`. `preparar-postgres.sh`
dejá el servicio arriba y crea el rol/base si hacen falta (idempotente, seguro
correrlo de nuevo). Variables de entorno (con default si no están seteadas):
`BACKOFFICE_BD_HOST` (`localhost`), `BACKOFFICE_BD_PUERTO` (`5432`),
`BACKOFFICE_BD_NOMBRE` (`backoffice`), `BACKOFFICE_BD_USUARIO` (`backoffice_app`),
`BACKOFFICE_BD_CLAVE` (`backoffice_dev_2026`).

**Sembrar es destructivo.** Ese `DROP` alcanza a todas las tablas, también a las que el
usuario escribe desde el panel (`notas_acciones`, `filtros_guardados`,
`configuracion_alertas`) y al bloqueo de login (`intentos_login`). Por eso `ejecutar.sh`
no corre `generar-datos-semilla.php` directamente sino `sembrar-si-falta.php`
(criterio en `datos/generador/base-sembrada.php`), que solo siembra si la base está
vacía, si le falta alguna tabla de los esquemas (el esquema cambió desde la última vez;
no hay migraciones) o si no tiene acciones: así las notas, los filtros guardados y la
configuración de alertas sobreviven a reiniciar. Recrearlo todo a propósito es
`./ejecutar.sh --regenerar` (o `php datos/generar-datos-semilla.php`), y hay que hacerlo
después de cambiar una tabla que ya existe (solo se detectan tablas que faltan, no
columnas nuevas) o la contraseña demo (`credenciales.php`).

`datos/esquema-datos-ejemplo.sql` es aparte: un puñado de INSERT de ejemplo, para
mirar el esquema con datos sin correr el generador completo — el sistema no lo
carga automáticamente. Va después de `esquema.sql` y `esquema-nucleo.sql`, en ese
orden (tiene INSERT en tablas de los dos; solo con `esquema.sql` falla):
`pruebas/php-integracion/esquema-datos-ejemplo-test.php` lo carga así en un schema
descartable, dentro de una transacción que siempre se revierte.

Ojo si se agrega una tabla nueva con FK hacia `acciones` o `usuarios`: el
`DROP TABLE ... CASCADE` de esas dos en `esquema.sql` borra la *constraint* de FK
en la tabla dependiente, pero no la tabla en sí (`notas_acciones` lo aprendió por
las malas — necesita su propio `DROP TABLE IF EXISTS notas_acciones CASCADE`
al principio de `esquema-nucleo.sql`, si no la segunda corrida del generador
falla con "relation already exists").

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
   descarta el registro completo si le faltan campos esenciales. El resultado
   (`acciones.json` + lo que se carga en PostgreSQL) es lo único que lee el backend
   en vivo — el saneamiento corre una sola vez al generar los datos. La lógica está
   modularizada: `primitivas.php` (validadores sueltos), `marca-temporal.php`
   (fechas), `accion-monto.php`, `accion-campos.php`, `accion-ip.php` (uno por grupo
   de campos) y `accion.php` (orquesta todo). Las primitivas están cubiertas por
   pruebas unitarias (ver "Pruebas automatizadas").

   Se puede verificar que funcionó: `acciones-crudas.json` sí contiene fragmentos
   como `<script>` o `<img onerror=`; en `acciones.json` no queda ninguno. Un
   puñado de ejemplos ya comparados, antes y después, está en
   `datos/ejemplos/muestra-antes-despues.json`.

## Pruebas automatizadas

Sin sumar dependencias nuevas: PHP y Node ya estaban, y Playwright ya viene
instalado globalmente en este entorno.

```bash
# Unit tests en PHP puro: saneador y helpers de la API (framework propio en pruebas/marco-pruebas.php, sin BD)
php pruebas/ejecutar-php.php

# Integración: Almacen*.php contra PostgreSQL real -- requiere el servicio arriba
# con los datos semilla cargados (mismo framework, misma sintaxis assert_igual/verdadero)
php pruebas/ejecutar-integracion.php

# Unit tests de JS (node:test + node:assert, nativos de Node)
./pruebas/ejecutar-js.sh

# e2e (Playwright): requiere los 3 servicios corriendo -- ./ejecutar.sh en otra terminal
./pruebas/ejecutar-e2e.sh
```

- `pruebas/php/`: `saneador_texto`/`id_usuario`/`tipo_accion`/`numero`/`moneda`/
  `codigo_http`/`ip`/`codigo_pais` (primitivas), `saneador_marca_temporal`/
  `duracion_ms` (fechas), y `saneador_accion()` de punta a punta (registro válido,
  campo esencial faltante, usuario inexistente, comentario con HTML, moneda
  inválida cae al país del usuario, hora local derivada de la IP), más
  `api_scope_label()` (etiqueta del universo de comparación: `null` cuando no hay
  filtro de país, coherente con `api_resolve_scope_countries()`; así "todos los
  países" lo traduce el frontend) y los 7 registros de `muestra-antes-despues.json`.
  También `ejecutar.sh`, corrido con `php`/`java`/`javac` falsos que solo anotan cómo los
  llamaron: sin opciones siembra con `sembrar-si-falta.php` (no con el script destructivo),
  `--regenerar` con `generar-datos-semilla.php` y avisa que borra, y una opción
  desconocida es un error de uso en vez de ignorarse. Y la comparación de cada
  badge: `api_delta_pct()` (el % se saca contra el promedio, y sin promedio útil
  da `null`) y `api_timeline_con_cohortes()` contra un servicio de estadísticas
  falso (`estadisticas-falsas-router.php`) con números conocidos: deltas de
  duración y monto, universo vacío, un tipo que falla, y qué universo le llega al
  servicio (países, edad, género y la exclusión del propio usuario). Y `Universo`
  (`universo-test.php`: lo que le pide al servicio, `aQuery()`, y cómo sale de la query de
  `/api/timeline`, `api_universo_desde_query()`: los valores por defecto, el grupo de países,
  un grupo que ya no existe). Y `api_error()`: todo error lleva su texto y su `codigo`, y ningún endpoint arma el
  suyo a mano (se recorre el código de `servidor-php/` buscándolos). Y los helpers de
  los endpoints (`api_responder`, `api_cuerpo_json`, `api_exigir_metodo`,
  `api_exigir_csrf`): su comportamiento, y el mismo recorrido para que la
  serialización, la lectura del cuerpo y el chequeo CSRF existan en un solo lugar.
- `pruebas/php-integracion/`: `AlmacenFiltros` (CRUD completo, age_min=0/null no se
  pierde), `AlmacenNotas` (guardar es upsert, texto vacío borra la fila),
  `AlmacenConfiguracion` (default habilitado si no hay fila, guardar/leer umbral),
  `AlmacenIntentosLogin` (bloqueo justo al 5to fallo, no antes, `limpiar` lo
  resetea), `AlmacenKpis` (invariantes: nada negativo, orden descendente de
  `top_action_types` — no valores pelados, para no romperse si el generador de
  datos semilla cambia sin que `AlmacenKpis` tenga ningún bug real),
  `AlmacenAcciones` y `AlmacenDatos` (empate en `marca_temporal`/`nombre` se
  desempata por `id`, para que la paginación no repita/salte filas),
  `AlmacenAlertas` (mismas invariantes que `AlmacenKpis`, tope de 15 en el top,
  empate en `event_count` también desempatado por `id`, y que las dos alertas
  tengan el mismo sobre y las mismas columnas comunes en cada fila; y sus reglas con
  datos controlados, `alertas-reglas-test.php`: tres usuarios nuevos con huecos de
  0.4 h, 2.25 h exactas y 3.9 h dentro de una transacción que se revierte, para fijar
  la ventana de 0.5 h a 4 h y el borde estricto, que cada cambio de país diga de
  dónde venía el usuario y a dónde fue, que el KPI cuente lo mismo que las alertas
  con cada tipo habilitado y cada umbral, y que la regla de IP en PHP y la de SQL
  coincidan),
  `ClienteEstadisticas` (`statsVarios()`, su único método de pedido: servicio caído
  devuelve `null` sin lanzar excepción y sin tardar segundos; pide varios tipos en
  paralelo, y con el servicio colgado el lote entero paga un solo timeout, no uno por
  tipo, y los pedidos siguientes de la misma instancia ni lo intentan) y
  `AlmacenAdministradores`/`auth_verificar_credenciales` (un admin que solo existe
  en la tabla, no en `credenciales.php`, autentica igual -- prueba que el login lee
  de la BD, no del archivo). Además, `esquema-datos-ejemplo.sql` se carga (junto
  con `esquema.sql` y `esquema-nucleo.sql`) en un schema descartable dentro de una
  transacción que siempre se revierte, para que los ejemplos no se rompan sin avisar.
  Los `cargar_*` de la siembra (`cargar-postgres-test.php`: cada tabla contra lo que tiene
  que quedar, con datos mínimos en un schema descartable, incluidos los valores por defecto
  de un país sin moneda ni huso y lo que queda `NULL`) e `insertar_filas()`
  (`insertar-filas-test.php`: una fila por elemento, `ON CONFLICT`, y que rechace nombres
  que no parezcan del esquema). Y la siembra: `sembrar-si-falta.php` corrido contra la base real, con una nota, un
  filtro guardado y un umbral de alertas puestos encima, no los toca (la prueba que
  habría fallado mientras `ejecutar.sh` sembraba en cada arranque); `motivo_para_sembrar()`
  distingue, en un schema descartable, base vacía, sin acciones, sembrada y esquema viejo.
  Esas pruebas comparten ayudantes (antes cada una tenía su copia): `en_schema_descartable()`
  (`ayudantes-integracion.php`: una transacción que siempre se revierte, el error de PostgreSQL
  vuelve como texto y un nombre de schema raro se rechaza) y, en `marco-pruebas.php`,
  `ejecutar_proceso()`, `puerto_libre()`, `servidor_colgado()` y
  `levantar_servidor_php()`/`detener_servidor_php()`. Tienen sus propias pruebas
  (`marco-pruebas-test.php`, `ayudantes-integracion-test.php`): si uno devolviera "nada" en
  silencio, las que se apoyan en él pasarían sin probar nada.
- `pruebas/js/`: `formato.js` (duración/tamaño de archivo/porcentaje, y
  `classifyDelta`, que decide verde/rojo/gris de cada badge: franja "en el
  promedio" de ±10% con el borde incluido, medida sobre el porcentaje que se
  muestra, y un delta que redondea a cero es "0%", no "-0%"), `errores.js`
  (`mensajeDeError`: gana la traducción del código, si no el texto del servidor, si
  no el genérico; y cada código que manda el backend está traducido al español y
  al inglés, sin sobrantes), `idioma.js`
  (interpolación de `{variables}`, cambio de diccionario, clave inexistente no
  rompe la interfaz), `alertas.js` (`renderAlerts`: un tipo habilitado sin
  resultados no dibuja una sección vacía, sin ninguna alerta real el panel
  entero queda oculto, y los dos tipos se dibujan igual -- cada uno con su título y
  su país, el declarado o el de donde venía -- y el clic lleva al usuario de esa
  fila), `controles-filtro.js` (leer, escribir y escuchar los cinco
  filtros por nombre: los desplegables avisan una sola vez y al instante, las edades
  esperan a que se deje de tipear; escribir no dispara ningún evento; cada id existe
  en `topbar.php`), `tarjeta-usuario.js` (`mostrarAviso`, el aviso de arriba del
  timeline) y `un-solo-lugar.test.mjs`, que vigila que los ids de los filtros, el
  aviso y la secuencia que pinta una respuesta de `/api/timeline` se escriban en un
  solo módulo cada uno (antes estaban copiados en 5, 3 y 2); lo mismo para el CSS: el
  brillo del foco y el resplandor de los cuadros solo en `base.css`, y que todo campo del
  login y de la barra lleve `.campo`, y que ninguna prueba arme su propio `localStorage` o
  `window` falso (lo hace `navegador-falso.mjs`, que Node no trae y los módulos leen al
  importarse; eran 8 copias). Y `modal.js` (`abrirModal`:
  el valor del botón pulsado; Escape y un clic en el fondo devuelven la opción segura y
  el foco va a ella; un modal nuevo cierra el anterior; no queda ningún listener de
  teclado colgado; `modalPrompt` y `modalConfirmar` sobre eso).
- `pruebas/e2e/`: login (credenciales incorrectas/correctas, también el error de
  credenciales y el bloqueo del 429 con la interfaz en inglés, logout), cookie de
  sesión `HttpOnly` (el JS de la página no la puede leer), POST sin token CSRF o
  con uno inválido → 403, elegir usuario, paginación, filtro por tipo, gráfico,
  alertas (clic salta de usuario) e idioma; en `panel-filtros.e2e.cjs`: los cinco
  filtros mandan lo elegido al pedir el timeline (los desplegables al instante, las
  edades esperando a que se deje de tipear), cambiar de idioma los conserva sin
  volver a pedir nada, qué se guarda y qué se aplica de un filtro guardado, y el
  aviso con ⚠ cuando falla el timeline o la carga inicial; en
  `panel-inactividad.e2e.cjs`, con el reloj falso de Playwright (`page.clock`): el
  aviso de sesión por expirar a los 29 minutos, que los dos botones se puedan pulsar
  con el mouse y con una pantalla táctil, el cierre solo a los 30 (sin que mover el
  mouse lo posponga ni que se repita si el servidor tarda en responder), el mensaje
  solo cuando fue por inactividad, Escape y clic afuera, un solo modal a la vez y el
  idioma del aviso; en `panel-estilos.e2e.cjs`, sin comparar píxeles: el campo del login, el de la
  barra y el del modal tienen el mismo aspecto y el mismo brillo al enfocarlos, y la
  tarjeta del login y el cuadro de un modal comparten borde, fondo y resplandor; más, en
  `panel-nuevas-features.e2e.cjs`:
  el tile de KPIs de alertas, el indicador visual al guardar una nota, dos
  guardados de nota superpuestos (gana el último texto escrito, no el que llega
  primero), guardar un filtro con el backend caído (toast de error), guardar/
  cancelar-eliminar/eliminar un filtro por la UI real (modal propio, sin diálogos
  nativos), cambiar rápido entre 2 filtros guardados (gana el más nuevo, no el
  que responde último) y el rate limiting (5 fallos + bloqueo con la contraseña
  correcta) — este último limpia `intentos_login` con `psql` en un `finally`,
  para no dejar la IP del test runner bloqueada 15 minutos si algo falla a
  mitad de camino. Aparte, `peticiones-api.e2e.cjs` (sin navegador) reproduce
  `datos/ejemplos/peticiones-api.http` bloque por bloque contra la API real
  (login, token CSRF reusado en los POST/DELETE, cada código HTTP esperado) y
  exige que el archivo tenga un ejemplo de cada ruta de la tabla de rutas
  (`rutas.php`, que lee con `pruebas/e2e/listar-rutas.php`); con esa misma tabla
  pide cada ruta sin sesión y exige el 401 en todas menos en las que manejan la
  sesión por su cuenta. Y
  `comparacion-valores.e2e.cjs` contrasta contra PostgreSQL, que es el oráculo
  (`percentile_cont` usa la misma definición de percentil pero otra implementación,
  y lee las tablas en vez del CSV de Java), cada eslabón de la comparación: el
  servicio Java (promedio/mediana/p90 de cada tipo, con filtros de país, edad y
  género, sin el propio usuario, con universos de 1 a 3 acciones y con universos
  vacíos), `/api/timeline` (el universo de cada acción y su delta %) y las
  tarjetas del panel (porcentaje, texto y color de cada badge), y esas mismas
  tarjetas sin servicio de estadísticas (aviso visible, "Sin datos de comparación"
  y ningún tooltip con `NaN`).

Las pruebas e2e usan `require()` (CommonJS) en vez de `import`, a propósito: Node
solo resuelve paquetes globales (Playwright no tiene `node_modules` propio acá) vía
`NODE_PATH` con `require()`, no con `import` bajo ESM.

Las e2e comparten `pruebas/e2e/ayudante-e2e.cjs`: `correrPrueba()` y `conNavegador()` abren
Chromium, entran al panel (o no, con `{ entrar: false }`) y lo cierran siempre; `enviarLogin()`,
`paginaEnIngles()`, las URLs del panel, de la API y del servicio Java (`BASE_URL`, `API_URL`,
`JAVA_URL`) y `ejecutarSql()`/`consultarSql()`. Antes cada archivo repetía el lanzamiento del
navegador y el login, y las URLs estaban escritas en tres.

En GitHub, `.github/workflows/pruebas-backoffice.yml` (en la raíz del repositorio)
corre las 4 suites en cada pull request que toque `sistema-nuevo/` y en cada push a
`master`: PostgreSQL 16 como servicio con las mismas credenciales que los defaults
de `ConexionBd.php`, datos semilla, y los 3 componentes levantados para el e2e. Si
algo falla, el último paso imprime los logs de Java, la API y el panel.

## Ejemplos

`datos/ejemplos/`:
- `muestra-antes-despues.json`: 7 registros del log crudo (de una corrida anterior
  del generador: sus `id` ya no coinciden con los de `acciones-crudas.json`),
  elegidos a mano para mostrar cada tipo de inconsistencia (fecha en epoch, monto
  con símbolo, usuario vacío, tipo desconocido, HTML inseguro en comentario, IP con
  puerto) junto a cómo queda cada uno después del saneador (o `null` si se
  descarta). `pruebas/php/muestra-antes-despues-test.php` corre el saneador real
  sobre cada `crudo` y exige el `saneado` documentado: si el saneador cambia, el
  ejemplo no puede quedar mintiendo sin que falle esa prueba.
- `peticiones-api.http`: un ejemplo de cada endpoint, en formato `.http`
  (extensión "REST Client" de VS Code, o el cliente HTTP de JetBrains) — login, los
  GET de catálogos/KPIs/alertas, timeline con distintos `scope` y `type`, y los
  POST/DELETE (configuración de alertas, filtros guardados, notas, logout) con el
  `X-CSRF-Token` que devuelve el login. `pruebas/e2e/peticiones-api.e2e.cjs` lo
  reproduce entero contra la API real y falla si algún bloque deja de andar o si
  alguna ruta de la tabla de rutas queda sin ejemplo.

## Cómo correrlo

Requiere PHP (probado en 8.4), JDK (probado en 21) y PostgreSQL (probado en 16, con
el cliente `psql`). Nada más para el sistema en sí (las pruebas usan Node, ver arriba).

**Opción rápida** — desde `sistema-nuevo/`:

```bash
./ejecutar.sh
```

Deja PostgreSQL arriba (`preparar-postgres.sh`), genera y carga los datos **solo si la
base todavía no los tiene**, y levanta:
- Panel de administración: http://localhost:8082 (usuario demo: `admin` / `admin123`)
- API backend (PHP): http://localhost:8000/api/session
- Stats service (Java): http://localhost:8081/stats?type=login

Las notas, los filtros guardados y la configuración de alertas se guardan en
PostgreSQL y sobreviven a reiniciar con `./ejecutar.sh`. Para empezar de cero a
propósito (**borra** todo eso, y también el bloqueo de login):

```bash
./ejecutar.sh --regenerar
```

**Manual**, en 3 terminales separadas desde `sistema-nuevo/`:

```bash
# 1. PostgreSQL arriba + datos semilla. sembrar-si-falta.php no toca nada si la base ya
#    está sembrada; generar-datos-semilla.php la recrea desde cero (borra notas y filtros)
./preparar-postgres.sh
php datos/sembrar-si-falta.php

# 2. Microservicio de estadísticas (Java) — son 7 archivos, hay que compilarlos juntos
cd servicio-estadisticas-java && javac *.java && java ServicioEstadisticas

# 3. API backend (PHP)
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t servidor-php/publico servidor-php/publico/index.php

# 4. Panel de administración
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8082 -t interfaz
```

`PHP_CLI_SERVER_WORKERS`: el servidor embebido de PHP atiende un solo request
a la vez por default. El panel ya pide varios endpoints en paralelo con
`Promise.all()` (`loadAppData()` en `aplicacion.js`) — sin esto, esos pedidos
se encolan igual del lado del servidor (medido: 5 requests en paralelo bajan
de ~110ms a ~55-90ms con 4 workers). Sin efecto en el estado de sesión ni en
el rate limiting de login: ambos viven en PostgreSQL/archivos de sesión
compartidos, no en memoria de un proceso.

Abrir http://localhost:8082.

## API (backend PHP)

**Rutas** (`codigo/rutas.php`, una sola tabla): cada ruta con la función que la atiende;
`/api/filtros/{id}` captura el id como entero y se lo pasa a la función. Toda ruta exige
sesión salvo que la tabla la marque `'sesion'`, y solo lo están `login`, `logout` y
`session`, que manejan la sesión por su cuenta (por eso no pasan por la barrera de
autenticación y la dejan abierta para escribirla). Agregar un endpoint sin tocar nada
más lo deja protegido; antes había que sumarlo a una lista de rutas protegidas y a un
`if/elseif`, y olvidar la primera lo dejaba abierto. `publico/index.php` solo consulta la
tabla (`api_resolver_ruta()`): una ruta que no figura da 404, una que exige sesión y no
la tiene da 401, el resto va a su función. `pruebas/php/rutas-test.php` prueba la
resolución (y que `/api/users/`, `/api/filtros/abc` o `/api/USERS` no resuelvan), que
las únicas rutas con `'sesion'` sean esas tres, que cada función exista y reciba justo
los argumentos de su ruta, y que ningún otro archivo escriba rutas.

**Piezas compartidas** (`api/ayudantes.php`, un solo lugar cada una): `api_responder()`
serializa toda respuesta (JSON con el UTF-8 crudo; una bandera de `json_encode` olvidada
en un endpoint ya fue una inconsistencia real), `api_cuerpo_json()` lee el cuerpo del
pedido y devuelve siempre un array (vacío, mal formado o un JSON que no es un objeto dan
`[]`), y `api_exigir_metodo()` / `api_exigir_csrf()` responden 405 / 403 y devuelven
`false` para que el endpoint corte. `pruebas/php/api-helpers-test.php` las prueba y
recorre `servidor-php/` para que ningún endpoint vuelva a armar lo suyo.

**Errores.** Toda respuesta de error (4xx/5xx) es `{"error": "<texto en español>",
"codigo": "<identificador>"}`, más datos propios del error si los hay (`retry_after`
en el 429 de `/api/login`). Salen siempre por `api_error()` (`api/ayudantes.php`;
`pruebas/php/api-error-test.php` exige que ningún endpoint arme el suyo a mano).
`error` es para quien lee la respuesta a mano; `codigo` es estable y es lo que la
interfaz traduce (ver "Idioma de la interfaz"): `no_autenticado` (401),
`credenciales_invalidas` (401), `csrf_invalido` (403), `ruta_no_encontrada`,
`usuario_no_encontrado`, `filtro_no_encontrado` y `accion_no_encontrada` (404),
`metodo_no_permitido` (405), `demasiados_intentos` (429), `user_id_requerido`,
`accion_id_requerido`, `filtro_nombre_requerido` y `filtro_nombre_largo` (400) y
`error_interno` (500).

- `POST /api/login` — `{"username": "...", "password": "..."}` → inicia sesión,
  responde con `csrf_token`. 429 tras 5 fallos consecutivos de esa IP (bloqueo de
  15 minutos, ver "Autenticación" arriba).
- `POST /api/logout` — requiere el header `X-CSRF-Token` (igual que todo
  POST/DELETE de la API que cambia estado); sin token válido, 403.
- `GET /api/session` — `{"authenticated": bool, "username": ?string, "csrf_token": ?string}`
  (pública, no requiere login).
- `GET /api/users?page=1&per_page=20&search=` — `{items, pagination}`. **Requiere sesión.**
- `GET /api/groups` — presets de país + catálogo de países. **Requiere sesión.**
- `GET /api/action-types` — `[{key, label}]`, catálogo de tipos de acción. **Requiere sesión.**
- `GET /api/alerts` — `{ip_pais?: {...}, cambio_pais?: {...}}`: las mismas claves que
  `/api/alerts-config`, cada una presente solo si ese tipo está habilitado. Los dos tipos
  tienen la misma forma, `{total_events, total_users_affected, top: [...]}`, y cada fila
  del `top` (los usuarios con más eventos de ese tipo) empieza por lo común, `user_id`,
  `user_name`, `event_count` y `last_seen`, y termina con lo propio: `country` (el país
  que declaró el usuario) en `ip_pais`, y `previous_country`/`current_country` en
  `cambio_pais`. Antes cada tipo nombraba distinto lo mismo (`total_mismatches` y
  `total_changes`, `mismatch_count` y `cambio_count`, `country` y `pais_anterior`) y
  las claves de arriba no coincidían con las de la configuración (`ip_pais_mismatch` y
  `cambios_pais_imposibles`). **Requiere sesión.**
- `GET|POST /api/alerts-config` — GET devuelve `{alertas: {ip_pais, cambio_pais},
  umbral}`; POST guarda cualquier subconjunto de esos campos (tipos fuera de la
  whitelist se ignoran). **Requiere sesión.**
- `GET|POST /api/filtros`, `DELETE /api/filtros/{id}` — CRUD de combinaciones de
  filtro guardadas (`{nombre, scope, age_min, age_max, gender, tipo_accion}`).
  **Requiere sesión.**
- `POST /api/notes` — `{"accion_id": "...", "texto": "..."}`, guarda o (si `texto`
  queda vacío tras `trim()`) borra la nota de una acción. **Requiere sesión.**
- `GET /api/kpis` — `{total_users, total_actions, total_spend_usd, last_action_at,
  active_alerts_users, top_action_types, top_countries}`, agregado de todo el
  sistema. **Requiere sesión.**
- `GET /api/timeline?user_id=u001&scope=preset:latam&age_min=18&age_max=65&gender=all&type=payment&page=1&per_page=20`
  — timeline paginado del usuario con cada acción enriquecida con `cohort` (`avg_*`,
  `median_*`, `p90_*` de duración y monto del universo), `duration_delta_pct`,
  `amount_delta_pct` (calculados contra el promedio), `ip_mismatch` (bool) y `note`
  (texto de la nota si existe), más `pagination` y `chart` (acciones/día + gasto
  acumulado, sin paginar). **Requiere sesión.**
  - `scope`: `all` | `preset:<otan|brics|latam|islamicos|euro|schengen>` | `country:<CODE>`
  - `gender`: `all` | `M` | `F` | `O`
  - `type`: `all` | `<clave de tipo de acción>` (ver `/api/action-types`)

## Notas / alcance

- Los datos son sintéticos (generados con semilla fija) para poder probar el sistema
  sin depender de logs reales; `generar-datos-semilla.php` documenta cómo se arman.
- Autenticación de un solo usuario, sin roles: alcanza para el prototipo, no para un
  despliegue real (ver "Autenticación" arriba).
- Si el servicio de estadísticas en Java no está corriendo, el backend PHP no rompe:
  cada acción queda sin comparación (`cohort: null`) y el panel lo indica con un aviso.
  Los tipos distintos de la página se piden todos juntos, en paralelo
  (`ClienteEstadisticas::statsVarios($tipos, $universo)`, vía `curl_multi`) en vez de uno por uno:
  si el servicio está colgado (acepta la conexión pero no responde), toda la
  página paga un solo timeout (~3 s) sin importar cuántos tipos distintos tenga,
  en vez de uno por tipo. Su URL sale de `BACKOFFICE_JAVA_URL` o, en su defecto,
  de `http://localhost:8081` (mismo patrón de variable de entorno con default que
  `BACKOFFICE_BD_*`, ver "Base de datos").
- El servicio Java recarga solo `acciones-planas.csv` si cambia su mtime (chequeo
  cada 5 segundos, `CargadorAcciones.iniciarWatcher()`) — no hace falta reiniciarlo
  a mano después de correr `generar-datos-semilla.php` de nuevo.
- Las cotizaciones de moneda y los husos horarios son fijos e ilustrativos, no de
  mercado/geolocalización en vivo.
- La generación usa una fecha ancla fija (no `time()`) para que los datos salgan
  byte a byte iguales entre corridas con la misma semilla — no depende de cuándo se
  ejecute `generar-datos-semilla.php`.
