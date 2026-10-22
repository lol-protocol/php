# lol-protocol/php

Este repositorio aloja varios proyectos independientes, cada uno en su propia carpeta.

> **Antes de tocar cualquier cosa que muestre datos de personas**, lee
> [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md): por qué la privacidad y la
> honra son la condición para que este proyecto exista, qué ya está resuelto y
> qué cabos sueltos quedan.

| Proyecto | Qué es |
|---|---|
| [`defamatory-content-review/`](defamatory-content-review/) | Librería PHP — detección de insultos y ridiculización en nombres, y de insultos, amenazas y acusaciones contra personas en textos libres, para plataformas genealógicas. El proyecto principal del repositorio. |
| [`url-routing/`](url-routing/docs/README_URLS.md) | Routing de URLs para los sitios de genealogía y POS (Contrastocolor): el tipo de recurso se infiere de la forma del primer segmento (cantidad de dígitos, letras de lugar), no de palabras. |
| [`web-animations/`](web-animations/) | Galería de demostración de 36 animaciones HTML/CSS/JS. |
| [`document-formats/`](document-formats/) | Base de datos de formatos de documento y papel por país. |
| [`privacy-laws/`](privacy-laws/) | Base de datos de leyes de privacidad por país, con buscador web, API PHP y esquema MySQL. Informativa: no es asesoría legal. |
| [`copyright-laws/`](copyright-laws/) | Base de datos de leyes de derechos de autor por jurisdicción, con buscador web, API PHP y esquema MySQL. Informativa: no es asesoría legal. |
| [`marketing/`](marketing/) | Frases y textos de marketing para un proyecto de genealogía: lemas, eslóganes, redes sociales y correo. |
| [`phone-directory/`](phone-directory/) | Parser de directorios telefónicos históricos (6 idiomas) para registros genealógicos. |
| [`psychology-and-marketing/`](psychology-and-marketing/) | Guía de psicología humana y neuromarketing ético para diseñar productos sin violar la privacidad del usuario. Solo documentos, sin código ni CI. |
| [`sistema-nuevo/`](sistema-nuevo/) | Backoffice para revisar la actividad de un usuario contra el promedio de su universo comparable (PHP + Java + JS). |
| [`cobros-ingresos-funnels/`](cobros-ingresos-funnels/) | Panel web de cobros, ingresos y funnel de conversión: boletas, pagos, clientes, cohortes y auditoría (PHP + PostgreSQL). |
| [`vps-setup/`](vps-setup/) | Scripts para configurar desde cero un VPS Ubuntu (Nginx, PHP, Python, PostgreSQL, SSL). |
| [`landing-page/`](landing-page/) | Landing page estática que despliegan los scripts de `vps-setup/`. |
| [`iconos-genealogia/`](iconos-genealogia/) | Set de iconos SVG abstractos en 2.5D, sin texto, para árbol genealógico: 172 iconos en 3 variantes. |
| [`iconos-biblioteca/`](iconos-biblioteca/) | Set de iconos SVG abstractos en 2.5D, sin texto, para biblioteca virtual: 151 iconos en 3 variantes. |
| [`iconos-tools/`](iconos-tools/) | Generador y validador en Python de los dos sets de iconos y de la galería compartida `galeria-iconos.html`. |
| [`marcalibros-plataforma-pos-brand-identity/`](marcalibros-plataforma-pos-brand-identity/) | Identidad de marca de Marcalibros (marcadores de lectura) para Plataforma POS: tokens de marca, iconos SVG, 1000 marcalibros (10 movimientos literarios y 10 tipos de texto) con su vista previa JPG, paisajes JPG, el generador en Python, un módulo PHP para crear marcalibros propios a partir de imágenes subidas y una aplicación web independiente, sin cuentas, para explorarlos y crearlos. |
| [`marcalibros_b/`](marcalibros_b/) | Marcalibros (marcadores de lectura verticales 1:7) para Plataforma POS: 1000 piezas generadas (10 movimientos literarios), generador determinista en Python con QA, línea de marca (logos, iconos, cabeceras), modelo de datos del lector y editor de usuario con subidas seguras. El catálogo completo lo publica la CI como artefacto. |
| [`Listado-colores/`](Listado-colores/) | Léxico de colores con nombre para el etiquetado part-of-speech: una lista completa de 31.935 nombres con su hex (con una marca de buenos nombres y su color CSS más cercano) y una tabla anotada de los 148 colores CSS, con sus nombres y dos significados (el psicológico y el místico) en 30 idiomas, un léxico básico de 38 términos de color de uso real, la evidencia de cada significado, matices culturales con sus referencias, exportes para PLN (JSONL, TSV y SQLite) con una forma normalizada para buscar en texto ruidoso y un manifiesto con la versión de los datos, y dos informes generados: las formas que designan más de un color y los nombres sospechosos de la lista completa. |
| [`signos-zodiacales/`](signos-zodiacales/) | Los 12 signos del zodiaco occidental y los 12 animales del horóscopo chino, con una descripción breve de sus supuestas características en 30 idiomas, y un esquema SVG de la constelación de cada signo occidental. Solo referencia cultural: la astrología no tiene respaldo científico. |
| [`Listado-paises/`](Listado-paises/) | Tabla de 208 países y territorios con población, IDH, PIB per cápita, alfabetización, internet y emigración, y dos juegos de índices propios: interés en genealogía y operabilidad (con los países inviables excluidos), e interés en lectura y viabilidad de un etiquetador part-of-speech, medida por las palabras anotadas de cada lengua. Estima además cuántas personas han nacido en cada país desde el 10 000 a. C. Incluye una vista web con mapa. Datos de Our World in Data, Gapminder, el Banco Mundial, Universal Dependencies y Common Crawl. |
| [`portadas-svg/`](portadas-svg/) | Generador en PHP, sin dependencias, de las piezas de la cubierta de un libro en SVG (portada, contratapa, lomo y sobrelomo o cubierta completa) a partir de un JSON, con dos plantillas y las medidas de corte para la imprenta. |

Cada carpeta tiene su propio README con instrucciones de instalación y uso. La mayoría tiene
también su propio código y tests; `landing-page/` es un solo archivo; `psychology-and-marketing/`
es solo texto; `marketing/` es texto e imágenes con dos scripts PHP sueltos (`render.php` e
`iconos/vista-previa.php`), sin tests ni CI; y los sets de iconos son SVG más el
generador/validador de `iconos-tools/` (ver "CI/CD" más abajo). La única dependencia entre
proyectos es:
**`phone-directory/` requiere `defamatory-content-review/`** (usa sus clases de plegado de
acentos y claves fonéticas) — su `composer.json` la declara como dependencia Composer
(`lol-protocol/defamatory-content-review`, repositorio `path` a `../defamatory-content-review`),
así que ambas carpetas deben estar presentes. El resto de proyectos no depende de ningún otro.

## Desarrollo

Cada proyecto se instala y se prueba desde su propia carpeta:

```bash
cd phone-directory        # o defamatory-content-review, sistema-nuevo, vps-setup...
composer install          # o npm install, según el proyecto
./vendor/bin/phpunit
```

El `composer.json` de la raíz es opcional: declara `defamatory-content-review/` y
`phone-directory/` como repositorios `path` para instalar los dos juntos desde código externo
al monorepo. No sustituye al `composer install` de cada carpeta (Composer no instala las
`require-dev` — y por tanto los tests — de una dependencia `path`).

## Carpetas `_Garbage/`

Cuatro proyectos (`defamatory-content-review/`, `cobros-ingresos-funnels/`, `web-animations/` y
`vps-setup/`) tienen una carpeta `_Garbage/` con material obsoleto: código que se sacó de uso,
versiones viejas y restos de iteraciones tempranas. **Se conserva a propósito**: la decisión del
repositorio es guardar lo obsoleto como referencia en vez de borrarlo (la de
`defamatory-content-review/` se restauró en #23 después de que #14 la borrara como "peso muerto").
A cambio, nada la usa: ni el código, ni los tests, ni la CI, ni el análisis estático la cargan, y
el README de cada una explica qué hay y por qué está ahí. No hace falta tocarlas al limpiar un
proyecto.

## CI/CD

Cada proyecto tiene su workflow en `.github/workflows/` y solo corre cuando cambia su carpeta (o el archivo del propio workflow), en los pull requests y en `master`: un PR que toca una sola carpeta dispara solo lo suyo. Si un proyecto usa a otro, su workflow también mira la carpeta del otro. Las carpetas sin workflow (`landing-page/`, `marketing/`, `psychology-and-marketing/`) solo disparan `estructura.yml`, que corre siempre.

- `estructura.yml`: tres jobs sobre la documentación de la raíz. `readme-projects` comprueba que la tabla de proyectos de este README liste todas las carpetas de la raíz; `readme-workflows`, que este apartado nombre todos los archivos de `.github/workflows/` (y ninguno que no exista); `claude-md-rutas`, que las rutas que cita `CLAUDE.md` existan (`python3 .github/scripts/comprobar-rutas-de-claude-md.py`).
- `pruebas-defamatory-content-review.yml`: `phpunit` (tests, PHPStan y benchmark de `defamatory-content-review/`) y `chat-front-guard` (tests en Node del tope de letras repetidas para el front, `defamatory-content-review/js/`).
- `pruebas-phone-directory.yml` (también corre si cambia `defamatory-content-review/`, que `phone-directory/` usa como dependencia): `phone-directory` (tests de `phone-directory/`, incluidos los ejemplos, contra SQLite y PostgreSQL, y el benchmark) y `phone-directory-ports` (tests de sus ports a Python y Java).
- `pruebas-url-routing.yml`: tests de `url-routing/` contra SQLite y PostgreSQL, y que sus migraciones corran limpias en PostgreSQL.
- `pruebas-document-formats.yml`: `document-formats` (tests en Node de `document-formats/`) y `document-formats-database` (importa `document-formats/` a MySQL, dos veces para comprobar que no duplica filas, y lo valida contra los CSV).
- `pruebas-web-animations.yml`: prueba de humo de las animaciones.
- `pruebas-listado-colores.yml`: valida los siete CSV y `CHANGELOG.md` de `Listado-colores/` y que la tabla del README, `colores-por-idioma.md`, las páginas de `por-idioma/`, `referencias.md`, `ambiguedad.md`, `auditoria-nombres.md` y `exportes/` (con su manifiesto) estén al día, y construye una base SQLite en memoria (`python3 generar-tabla.py --comprobar`); corre además las pruebas del generador (`python3 -m unittest pruebas`) y repite las dos cosas en Python 3.10 y 3.14 sobre Ubuntu 24.04 y en Python 3.13 sobre macOS 15.
- `pruebas-listado-paises.yml`: valida los CSV de `Listado-paises/` y que `tabla_genealogia.md`, `tabla_pos.md`, `priorizacion.csv` y `vista.html` estén al día (`python3 generar_tabla.py --comprobar`), y corre sus pruebas (`python3 -m unittest pruebas`).
- `pruebas-portadas-svg.yml`: pruebas unitarias de `portadas-svg/` (`php pruebas/ejecutar.php`) y que `ejemplos/salida/` coincida con lo que genera el código (`php bin/generar.php --comprobar`), en PHP 8.3.
- `pruebas-signos-zodiacales.yml`: valida `signos-zodiacales/datos/signos.json` (los 30 idiomas en cada nombre y descripción, y que las líneas de cada constelación apunten a estrellas que existan) y que `constelaciones/*.svg` y `signos-por-idioma.md` estén al día (`python3 generar.py --comprobar`).
- `marcalibros-b.yml`: genera los 1000 marcalibros de `marcalibros_b/` con Chromium para el QA, pasa sus pruebas (determinismo, hashes de referencia, modelo de datos y editor) y sube el catálogo como artefacto `marcalibros-catalogo`. Se puede lanzar a mano con `workflow_dispatch` (entrada `forzar` para ignorar la caché).

`sistema-nuevo/`, `vps-setup/`, `cobros-ingresos-funnels/` y `marcalibros-plataforma-pos-brand-identity/` tienen sus propios workflows (`pruebas-backoffice.yml`, `vps-setup.yml`, `pruebas-cobros-ingresos-funnels.yml` y `pruebas-marcalibros.yml`, que también corre si cambia `defamatory-content-review/`, del que depende). `vps-setup/` tiene además `vps-setup-e2e.yml`: instala todo de verdad (`apt`, `systemd`, servicios) en un Ubuntu 24.04 desechable; solo corre si cambian los scripts, cada lunes o a mano.

`iconos.yml` valida los dos sets de iconos: que los SVG commiteados coincidan con lo que produce
`iconos-tools/build.py`, `iconos-tools/validate.py` y `iconos-tools/similares.py` (iconos casi
idénticos). Solo corre si cambia algo de `iconos-genealogia/`, `iconos-biblioteca/`,
`iconos-tools/` o `galeria-iconos.html`.

`informe-de-ramas.yml` lista cada lunes, en el resumen de la corrida, las ramas remotas vacías o ya fusionadas
(`.github/scripts/informe-de-ramas.sh`). Solo informa: borrarlas lo decide una persona.

`privacy-laws/` y `copyright-laws/` también tienen los suyos: `laws-modules.yml` (validación de los CSV maestros, archivos generados al día, pruebas unitarias, de la API y de la caché de textos en PHP 8.1 y 8.4, y pruebas en navegador con axe-core), `laws-links.yml` (cada lunes comprueba que los enlaces de referencia sigan respondiendo, y mantiene una issue con los que fallan) y `laws-freshness.yml` (cada lunes busca datos que quedaron viejos: filas sin revisar hace más de un año, cambios previstos cuya fecha ya pasó, valores que cambiaron después de revisar la fuente).

## Licencia

MIT — ver [LICENSE](LICENSE).
