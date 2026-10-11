# Marcalibros — contexto para quien continúe el trabajo

Proyecto: identidad de marca de Marcalibros (marcadores de lectura 7:1, de 1400×200 y 2800×400) para Plataforma POS, una herramienta de etiquetado de texto (NLP) sobre textos de Project Gutenberg. Vive en esta carpeta del repositorio `lol-protocol/php` (rama `master`). La persona que lo encarga escribe en español: responde y documenta en español.

El estado detallado y los pendientes están en `README.md`; la paleta y las medidas, en `brand-tokens.md`; la lista de categorías, en `catalog/taxonomia.md`; la aplicación, en `app/README.md`. No repitas aquí lo que dicen: léelos.

## Qué hay

| Carpeta | Qué es |
|---|---|
| `generator/` | Python (Pillow y numpy). Genera `catalog/` y `images/`. Determinista: dos ejecuciones dan los mismos archivos |
| `catalog/` | 1000 marcalibros (20 categorías × 50): SVG, vistas previas JPG 700×100, `manifest.json` |
| `php/` | Módulo `Marcalibros\`: valida imágenes subidas, recorta a 7:1, lee el catálogo |
| `app/` | Aplicación web `MarcalibrosApp\`, sin cuentas ni login, que usa el módulo |

## Decisiones de la persona (no las cambies sin preguntar)

- **Realismo mágico se queda** como movimiento propio. Los 10 movimientos literarios se mantienen.
- Además de los movimientos hay **10 tipos de texto** (manuales, libros sagrados, leyes, cartas, diarios, ciencia, cocina, filosofía, teatro, viajes). Ya están **confirmados** junto con los movimientos y sus autores; los autores de inspiración son solo de los movimientos.
- Son **1000 marcalibros** en total.
- La aplicación es **independiente y sin login**. **No la conectes con Plataforma POS** hasta que lo pida; todavía no se sabe dónde vive esa plataforma ni cómo se identificará a las personas.
- Los libros sagrados no llevan símbolos de una religión concreta.
- Formato de la persona (parte está en `README.md`, "Convenciones"): decimales con 1, 2, 4 o 5 cifras (nunca 3); mayúsculas normales salvo siglas (API, POS, SVG, NLP); nombres de archivo en minúsculas, con guiones y sin número al inicio.

## Comandos

```bash
python3 generator/generar_marcalibros.py            # regenera los 1000 (unos 20 s); falla si un SVG pesa 10 KB o más, o si hay duplicados
cd app && php -S localhost:8080 -t public public/index.php
cd php && composer install && ./vendor/bin/phpunit  # igual en app/
```

Después de regenerar, `git status` no debe mostrar cambios si no tocaste el generador.

## Reglas que no se ven a simple vista

- **La cuenta del recorte está duplicada** a propósito: `php/src/ImageNormalizer.php` (servidor) y `app/public/assets/crear.js` (`cropBox`). Si cambias una, cambia la otra y comprueba en un navegador que la franja que se ve al encuadrar es la que se guarda (con una foto vertical y una con orientación EXIF).
- La **CSP** de la aplicación es estricta (`style-src 'self'`, `script-src 'self'`): ninguna plantilla puede llevar `style=`, `<style>`, scripts ni manejadores `on…` en línea. `ViewTest` lo vigila. Los colores se aplican con clases; los estilos dinámicos se asignan por CSSOM desde un archivo `.js`.
- Los identificadores que llegan en la dirección se validan con el formato exacto antes de tocar el disco, con `\z` y no `$` (con `$`, un salto de línea final pasa la validación).
- Todos los intentos de crear un marcalibro gastan del límite por IP, también los fallidos. La IP no se guarda: se usa un HMAC con una clave del servidor.
- Los SVG del catálogo se sirven con `Content-Security-Policy: sandbox`.
- Un motivo nuevo del generador debe pasar el límite de 10 KB y no repetirse: usa menos figuras o menos puntos antes que subir el límite.

## Entorno y verificación

- `composer.lock` de `php/` y `app/` está en el repositorio. En el entorno donde se escribió esto, Packagist respondía pero `github.com` no (403), así que PHPUnit 10.5 se instaló y las pruebas pasaron con PHPUnit real (95 del módulo y 122 de la aplicación; con avisos esperados de pruebas que fuerzan fallos), pero **PHPStan no se pudo instalar ni correr** (su paquete se descarga de GitHub). El CI lo corre en nivel 6: si falla ahí, corrígelo.
- Las pruebas de navegador (subida real, rechazos, límite de intentos, borrado, teclado, móvil, sin JavaScript) se hicieron con Playwright a mano y no están en el repositorio. Si agregas una prueba de extremo a extremo, evita `wait_for_function` con texto: la CSP bloquea `eval`.
- CI: `.github/workflows/pruebas-marcalibros.yml` (PHPUnit en PHP 8.1 y 8.3, PHPStan nivel 6, y comprobación de que el generador no cambia el catálogo).
- Las fuentes (Source Serif 4 e Inter) están incluidas en `app/public/assets/fuentes/`; la CSP lleva `font-src 'self'`. No se comprobó el aspecto en un navegador.
