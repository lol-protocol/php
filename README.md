# lol-protocol/php

Este repositorio aloja varios proyectos independientes, cada uno en su propia carpeta. Buena parte del desarrollo lo hacen sesiones de [Claude Code](https://claude.com/claude-code); [`CLAUDE.md`](CLAUDE.md) tiene las reglas que siguen (un PR por carpeta, cómo evitar que dos sesiones choquen, qué pruebas corre cada proyecto) y también sirve de guía para quien contribuya a mano.

> **Antes de tocar cualquier cosa que muestre datos de personas**, lee
> [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md): por qué la privacidad y la
> honra son la condición para que este proyecto exista, qué ya está resuelto y
> qué cabos sueltos quedan.

Los demás documentos de la raíz: [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md) (el borrador de los
términos de uso, que ningún abogado ha revisado), [`RECLAMOS.md`](RECLAMOS.md) (qué pasa, paso a
paso, cuando alguien reclama), [`PREGUNTAS-PARA-ABOGADO.md`](PREGUNTAS-PARA-ABOGADO.md) (lo que
el equipo no puede decidir solo), [`CUMPLIMIENTO-LEY-21719.md`](CUMPLIMIENTO-LEY-21719.md) (qué
hace el sitio frente a la ley chilena de datos y qué falta antes del 1 de diciembre de 2026),
[`CONTRIBUTING.md`](CONTRIBUTING.md) (cómo abrir un PR a mano)
y [`SECURITY.md`](SECURITY.md) (cómo avisar de una fuga de datos sin publicarla).

| Proyecto | Qué es |
|---|---|
| [`defamatory-content-review/`](defamatory-content-review/) | Librería PHP — detección de insultos y ridiculización en nombres, y de insultos, amenazas y acusaciones contra personas en textos libres, para plataformas genealógicas. El proyecto principal del repositorio. |
| [`url-routing/`](url-routing/docs/README_URLS.md) | Routing de URLs para los sitios de genealogía y POS (Contrastocolor): el tipo de recurso se infiere de la forma del primer segmento (cantidad de dígitos, letras de lugar), no de palabras. |
| [`web-animations/`](web-animations/) | Galería de demostración de 36 animaciones HTML/CSS/JS. |
| [`document-formats/`](document-formats/) | Base de datos de formatos de documento y papel por país. |
| [`privacy-laws/`](privacy-laws/) | Base de datos de leyes de privacidad por país, con buscador web, API PHP y esquema MySQL. Informativa: no es asesoría legal. |
| [`copyright-laws/`](copyright-laws/) | Base de datos de leyes de derechos de autor por jurisdicción, con buscador web, API PHP y esquema MySQL. Informativa: no es asesoría legal. |
| [`leyes-comunes/`](leyes-comunes/) | Núcleo compartido del verificador de enlaces de referencia de `privacy-laws/` y `copyright-laws/`: ninguno de los dos depende del otro, los dos dependen de esta carpeta. |
| [`marketing/`](marketing/) | Frases y textos de marketing para un proyecto de genealogía: lemas, eslóganes, redes sociales y correo. |
| [`citas-pos/`](citas-pos/) | Documentación y plan de un apoyo de marketing para Contrastocolor (el etiquetador POS): citas célebres de dominio público, en varios idiomas, con cada palabra en el color de su categoría gramatical, cada una con su fuente verificada y sus traducciones como borrador. Por ahora hay documentación (ideas, criterios y plan), una paleta de 17 colores con su prototipo web y el registro de revisiones; todavía no hay citas. |
| [`phone-directory/`](phone-directory/) | Parser de directorios telefónicos históricos (6 idiomas) para registros genealógicos. |
| [`psychology-and-marketing/`](psychology-and-marketing/) | Guía de psicología humana y neuromarketing ético para diseñar productos sin violar la privacidad del usuario. Solo documentos, sin código ni CI. |
| [`sistema-nuevo/`](sistema-nuevo/) | Backoffice para revisar la actividad de un usuario contra el promedio de su universo comparable (PHP + Java + JS). |
| [`cobros-ingresos-funnels/`](cobros-ingresos-funnels/) | Panel web de cobros, ingresos y funnel de conversión: boletas, pagos, clientes, cohortes y auditoría (PHP + PostgreSQL). |
| [`vps-setup/`](vps-setup/) | Scripts para configurar desde cero un VPS Ubuntu (Nginx, PHP, Python, PostgreSQL, SSL). |
| [`landing-page/`](landing-page/) | Landing page estática que despliegan los scripts de `vps-setup/`. |
| [`iconos-genealogia/`](iconos-genealogia/) | Set de iconos SVG abstractos en 2.5D, sin texto, para árbol genealógico: 207 iconos en 3 variantes. |
| [`iconos-biblioteca/`](iconos-biblioteca/) | Set de iconos SVG abstractos en 2.5D, sin texto, para biblioteca virtual: 151 iconos en 3 variantes. |
| [`iconos-pos/`](iconos-pos/) | Set de iconos SVG abstractos en 2.5D, sin texto, para el punto de venta (catálogo, pedidos, cuenta del cliente, carrito y pago): 23 iconos en 3 variantes. |
| [`iconos-tools/`](iconos-tools/) | Generador y validador en Python de los tres sets de iconos y de la galería compartida `galeria-iconos.html`. |
| [`marcalibros-plataforma-pos-brand-identity/`](marcalibros-plataforma-pos-brand-identity/) | Identidad de marca de Marcalibros (marcadores de lectura **horizontales 7:1**) para Plataforma POS: tokens de marca, iconos SVG, 1350 marcalibros (11 movimientos literarios y 16 tipos de texto, 50 por categoría) con su vista previa JPG, paisajes JPG, el generador en Python, un módulo PHP para crear marcalibros propios a partir de imágenes subidas y una aplicación web independiente, sin cuentas, para explorarlos y crearlos. |
| [`marcalibros_b/`](marcalibros_b/) | Marcalibros (marcadores de lectura verticales 1:7) para Plataforma POS: 1000 piezas generadas (10 movimientos literarios), generador determinista en Python con QA, línea de marca (logos, iconos, cabeceras), modelo de datos del lector y editor de usuario con subidas seguras. El catálogo completo lo publica la CI como artefacto. |
| [`Listado-colores/`](Listado-colores/) | Léxico de colores con nombre para el etiquetado part-of-speech: una lista completa de 31.935 nombres con su hex (con una marca de buenos nombres y su color CSS más cercano) y una tabla anotada de los 148 colores CSS, con sus nombres y dos significados (el psicológico y el místico) en 30 idiomas, un léxico básico de 38 términos de color de uso real, la evidencia de cada significado, matices culturales con sus referencias, las categorías gramaticales (adjetivo, sustantivo, nombre propio, verbo) de las palabras de color de una sola palabra en inglés y español con una frase de ejemplo, exportes para PLN (JSONL, TSV y SQLite, más patrones de spaCy y un léxico y unos ejemplos en formato de Universal Dependencies) con una forma normalizada para buscar en texto ruidoso y un manifiesto con la versión de los datos, tres informes generados (las formas que designan más de un color, los nombres sospechosos de la lista completa y las categorías gramaticales) y un registro, todavía vacío, de revisión por hablantes nativos con su informe y un visor HTML para hacerla. |
| [`signos-zodiacales/`](signos-zodiacales/) | Los 12 signos del zodiaco occidental y los 12 animales del horóscopo chino, en 30 idiomas: nombre, descripción breve y larga, rasgos (símbolo, fechas tropicales y siderales, elemento y modalidad en el occidental; rama, elemento y polaridad en el chino), consultas en Python (`zodiaco.py`: el signo de una fecha, y el animal y el elemento de un año o de una fecha de nacimiento, con el Año Nuevo lunar de 1900 a 2099) y su port a PHP, probados con los mismos vectores y una ilustración SVG de cada uno, con fondo y sin fondo, la figura en trazo fino con las estrellas encima (los animales chinos no tienen constelación real: es un dibujo propio del proyecto), más una galería HTML para verlas. Solo referencia cultural: la astrología no tiene respaldo científico. |
| [`Listado-paises/`](Listado-paises/) | Tabla de 208 países y territorios con población, IDH, PIB per cápita, alfabetización, internet y emigración, y dos juegos de índices propios: interés en genealogía y operabilidad (con los países inviables excluidos), e interés en lectura y viabilidad de un etiquetador part-of-speech, medida por las palabras anotadas de cada lengua. Estima además cuántas personas han nacido en cada país desde el 10 000 a. C. Incluye una vista web con mapa. Datos de Our World in Data, Gapminder, el Banco Mundial, Universal Dependencies y Common Crawl. |
| [`portadas-svg/`](portadas-svg/) | Generador en PHP, sin dependencias, de las piezas de la cubierta de un libro en SVG (portada, contratapa, lomo y sobrelomo o cubierta completa) a partir de un JSON o de una lista CSV de cientos de libros, con fuentes libres incrustadas (o el texto a trazos), tres plantillas, adornos por tradición cultural, colores y motivos por categoría, y las medidas de corte para la imprenta. |

**Las dos carpetas de Marcalibros son dos líneas independientes, no versiones de lo mismo:** `marcalibros-plataforma-pos-brand-identity/` hace marcalibros horizontales (7:1, 1400×200), y tiene el módulo PHP y la aplicación web; `marcalibros_b/` hace marcalibros verticales (1:7, 200×1400), con su línea de marca, el modelo de datos del lector y un editor, y QA en Chromium. No comparten código ni datos, y cada una tiene su `CLAUDE.md`: antes de trabajar en Marcalibros, confirma en cuál.

Cada carpeta tiene su propio README con instrucciones de instalación y uso. La mayoría tiene
también su propio código y tests; `landing-page/` es un solo archivo; `psychology-and-marketing/`
es solo texto; `marketing/` es texto e imágenes con dos scripts PHP sueltos (`render.php` e
`iconos/vista-previa.php`), sin tests ni CI; los sets de iconos son SVG más el
generador/validador de `iconos-tools/` (ver "CI/CD" más abajo); y `privacy-laws/` y
`copyright-laws/` comparten el núcleo de su verificador de enlaces en `leyes-comunes/`, sin que
uno dependa del otro. Tres proyectos usan `defamatory-content-review/` y necesitan esa carpeta
al lado:

- **`phone-directory/`** usa sus clases de plegado de acentos y claves fonéticas; su
  `composer.json` la declara como dependencia Composer (`lol-protocol/defamatory-content-review`,
  repositorio `path` a `../defamatory-content-review`).
- **`sistema-nuevo/`** pasa las notas del panel por su filtro; la busca en
  `../defamatory-content-review` o en la carpeta de `FILTRO_DIFAMACION_DIR`.
- **`marcalibros-plataforma-pos-brand-identity/`** revisa con su filtro el texto que cada persona
  escribe en su marcalibro; su aplicación carga las clases desde `../defamatory-content-review/src/`.

Por eso los workflows de esos tres corren también cuando cambia `defamatory-content-review/`. El
resto de proyectos no depende de ningún otro.

## Desarrollo

Cada proyecto se instala y se prueba desde su propia carpeta:

```bash
cd phone-directory        # o defamatory-content-review, sistema-nuevo, vps-setup...
composer install          # o npm ci, según el proyecto
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

- `estructura.yml`: seis jobs sobre la estructura del repo. `readme-projects` comprueba que la tabla de proyectos de este README liste todas las carpetas de la raíz; `readme-workflows`, que este apartado nombre todos los archivos de `.github/workflows/` (y ninguno que no exista); `claude-md-rutas`, que las rutas que cita `CLAUDE.md` existan (`python3 .github/scripts/comprobar-rutas-de-claude-md.py`); `enlaces-md`, que los enlaces relativos de los `.md` apunten a algo que exista (`python3 .github/scripts/comprobar-enlaces-md.py`); `composer-validate`, `composer validate` en cada `composer.json` (estricto, salvo el de la raíz); y `landing-page`, que esa carpeta no tenga documentación, scripts ni archivos ocultos, porque `vps-setup/` la copia entera a la raíz del servidor web.
- `pruebas-defamatory-content-review.yml`: `phpunit` (tests, PHPStan y benchmark de `defamatory-content-review/`) y `chat-front-guard` (tests en Node del tope de letras repetidas para el front, `defamatory-content-review/js/`).
- `pruebas-phone-directory.yml` (también corre si cambia `defamatory-content-review/`, que `phone-directory/` usa como dependencia): `phone-directory` (tests de `phone-directory/`, incluidos los ejemplos, contra SQLite y PostgreSQL, y el benchmark) y `phone-directory-ports` (tests de sus ports a Python y Java).
- `pruebas-url-routing.yml`: tests de `url-routing/` contra SQLite y PostgreSQL, que sus migraciones corran limpias en PostgreSQL, y `despliegue`: una prueba de humo del despliegue de su guía con nginx y PHP-FPM de verdad (`url-routing/deploy/smoke-test.sh`: permisos, migraciones, `/ready`, cabeceras, HTTPS, el ciclo de un pedido de retiro y el limitador).
- `pruebas-document-formats.yml`: `document-formats` (tests en Node de `document-formats/`) y `document-formats-database` (importa `document-formats/` a MySQL, dos veces para comprobar que no duplica filas, y lo valida contra los CSV).
- `pruebas-web-animations.yml`: prueba de humo de las animaciones.
- `pruebas-listado-colores.yml`: valida los nueve CSV y `CHANGELOG.md` de `Listado-colores/` y que la tabla del README, `colores-por-idioma.md`, las páginas de `por-idioma/`, `referencias.md`, `ambiguedad.md`, `auditoria-nombres.md`, `categorias.md`, `revisiones.md`, `visor-revision.html` y `exportes/` (con los patrones de spaCy y el manifiesto) estén al día, y construye una base SQLite en memoria (`python3 generar-tabla.py --comprobar`); corre además las pruebas del generador (`python3 -m unittest pruebas`) y repite las dos cosas en Python 3.10 y 3.14 sobre Ubuntu 24.04 y en Python 3.13 sobre macOS 15.
- `pruebas-listado-paises.yml`: valida los CSV de `Listado-paises/` y que `tabla_genealogia.md`, `tabla_pos.md`, `priorizacion.csv` y `vista.html` estén al día (`python3 generar_tabla.py --comprobar`), y corre sus pruebas (`python3 -m unittest pruebas`).
- `pruebas-portadas-svg.yml`: pruebas unitarias de `portadas-svg/` (`php pruebas/ejecutar.php`), que `ejemplos/salida/` coincida con lo que genera el código (`php bin/generar.php --comprobar`), PHPStan 2.1 y `composer validate`, en PHP 8.3.
- `pruebas-signos-zodiacales.yml`: valida `signos-zodiacales/datos/signos.json` (los 30 idiomas en nombres, descripciones y términos; elemento, modalidad y ciclo chino; líneas y estrellas de cada constelación) y la tabla del Año Nuevo lunar, y que `constelaciones/*.svg`, `constelaciones-sin-fondo/*.svg`, `por-idioma/*.md` y `galeria.html` estén al día, sin archivos sueltos (`python3 generar.py --comprobar`); corre además sus pruebas (`python3 -m unittest pruebas`): coherencia de los datos, las consultas de `zodiaco.py`, que `validar()` atrape datos rotos y que los SVG sean válidos y sin scripts; y los ejemplos de `zodiaco.py` (`python3 -m doctest zodiaco.py`). Un segundo job abre `galeria.html` en Chromium con Playwright (`python pruebas_navegador.py`) y la recorre en los 30 idiomas y las dos variantes: sin errores de JavaScript, con las imágenes cargadas y la interfaz en el idioma elegido. Un tercero corre el port a PHP (`php php/pruebas/ejecutar.php`, en PHP 8.1 y 8.4) contra `datos/vectores.json`, las respuestas que genera `zodiaco.py`.
- `pruebas-citas-pos.yml`: comprueba la paleta de `citas-pos/` (las 17 categorías, contraste de 4,5:1 en modo claro y oscuro y que ningún par de colores se parezca demasiado), que `prototipo/index.html` y `salida/` (las piezas de cita coloreada para el correo y el sitio) coincidan con lo que genera el código, que cada cita de `datos/citas.json` tenga su ficha completa y ninguna figure como verificada sin texto, edición y dos fuentes, y que el registro de revisiones sea válido, en PHP 8.3.
- `marcalibros-b.yml`: genera los 1000 marcalibros de `marcalibros_b/` con Chromium para el QA, pasa sus pruebas (determinismo, hashes de referencia, modelo de datos y editor) y sube el catálogo como artefacto `marcalibros-catalogo`. Se puede lanzar a mano con `workflow_dispatch` (entrada `forzar` para ignorar la caché).

`sistema-nuevo/`, `vps-setup/`, `cobros-ingresos-funnels/` y `marcalibros-plataforma-pos-brand-identity/` tienen sus propios workflows (`pruebas-backoffice.yml`, `vps-setup.yml`, `pruebas-cobros-ingresos-funnels.yml` y `pruebas-marcalibros.yml`, que también corre si cambia `defamatory-content-review/`, del que depende). `vps-setup/` tiene además `vps-setup-e2e.yml`: instala todo de verdad (`apt`, `systemd`, servicios) en un Ubuntu 24.04 desechable; solo corre si cambian los scripts, cada lunes o a mano.

`iconos.yml` valida los tres sets de iconos: que los SVG commiteados coincidan con lo que produce
`iconos-tools/build.py`, `iconos-tools/validate.py` y `iconos-tools/similares.py` (iconos casi
idénticos). Solo corre si cambia algo de `iconos-genealogia/`, `iconos-biblioteca/`, `iconos-pos/`,
`iconos-tools/` o `galeria-iconos.html`.

`informe-de-ramas.yml` lista cada lunes, en el resumen de la corrida, las ramas remotas vacías o ya fusionadas y, si sobran 10 o más, mantiene una issue con la lista
(`.github/scripts/informe-de-ramas.sh`). Solo informa: borrarlas lo decide una persona.

`privacy-laws/` y `copyright-laws/` también tienen los suyos: `laws-modules.yml` (validación de los CSV maestros, archivos generados al día, pruebas unitarias, de la API y de la caché de textos en PHP 8.1 y 8.4, pruebas en navegador con axe-core, y la importación a MySQL 8.0 y MariaDB 10.11 comparada columna por columna con el CSV), `laws-links.yml` (cada lunes comprueba que los enlaces de referencia sigan respondiendo, y mantiene una issue con los que fallan; en un PR revisa los países que toca, y no corre en cada push a `master`) y `laws-freshness.yml` (cada lunes busca datos que quedaron viejos: filas sin revisar hace más de un año, cambios previstos cuya fecha ya pasó, valores que cambiaron después de revisar la fuente, y mantiene una issue con lo que encuentre).

## Licencia

MIT — ver [LICENSE](LICENSE).
