# Marcalibros — aplicación web

Aplicación independiente para elegir un marcalibro del catálogo (1000 diseños) o crear uno propio con una imagen. **No tiene cuentas ni inicio de sesión** y no está conectada a Plataforma POS: se puede probar y desplegar sola. La integración queda para después.

## Qué hace

| Pantalla | Dirección | Qué se puede hacer |
|---|---|---|
| Catálogo | `/` | Ver muestras de las 20 categorías (10 movimientos literarios y 10 tipos de texto) |
| Categoría | `/?categoria=leyes` | Recorrer los 50 marcalibros de una categoría, de a 25 por página |
| Detalle | `/marcalibro/mb-leyes-07` | Ver uno grande, sus colores, y descargarlo en SVG |
| Crear | `/crear` | Subir una imagen o elegir un paisaje de la marca, encuadrar en 7:1 y ponerle nombre |
| Mío | `/mio/mp-…` | Ver y descargar el marcalibro creado (1400×200 y 2800×400), copiar su enlace o borrarlo |

Sin JavaScript todo sigue funcionando, salvo mover el marco: el marcalibro se recorta por el centro.

## Requisitos

- PHP 8.1 o superior con las extensiones `gd` (con WebP), `fileinfo` y `mbstring`. `exif` es recomendable: sin ella las fotos del celular pueden quedar de lado.
- No necesita base de datos ni paquetes externos para funcionar. Sí necesita la carpeta `defamatory-content-review/` del monorepo junto a `marcalibros-plataforma-pos-brand-identity/`: revisa el texto de cada marcalibro. Al desplegar, copia las dos.

## Probarla en tu máquina

```bash
cd marcalibros-plataforma-pos-brand-identity/app
php -S localhost:8080 -t public public/index.php
```

Abre <http://localhost:8080>. Los marcalibros creados se guardan en `app/storage/` (ignorada por git).

El servidor integrado de PHP trae `upload_max_filesize=2M` por defecto. La aplicación lo detecta y avisa el límite real en el formulario; para llegar a los 5 MB: `php -d upload_max_filesize=5M -d post_max_size=6M -S localhost:8080 -t public public/index.php`.

## Configuración

Se hace con variables de entorno. Todas son opcionales.

| Variable | Por defecto | Para qué sirve |
|---|---|---|
| `MARCALIBROS_STORAGE` | `app/storage` | Dónde se guardan los marcalibros creados. Debe quedar **fuera** de `public/` |
| `MARCALIBROS_PROJECT_ROOT` | carpeta superior a `app/` | Carpeta con `catalog/`, `images/` y `svg/` |
| `MARCALIBROS_RETENTION_DAYS` | `30` | Días que se conserva un marcalibro creado |
| `MARCALIBROS_CREATES_PER_HOUR` | `10` | Intentos de crear por hora y por dirección IP (los fallidos también cuentan) |
| `MARCALIBROS_MAX_STORED` | `5000` | Tope de marcalibros guardados a la vez; al llegar, no se aceptan más |
| `MARCALIBROS_TRUST_FORWARDED_FOR` | apagado | Con `1`, la IP de cada persona es la última de `X-Forwarded-For`. Solo detrás de un proxy propio que la ponga |

## Cómo se protege sin cuentas

No hay sesión ni cookies, así que no hay nada que un tercero pueda hacer "en nombre" de alguien. En su lugar:

- **El enlace es la llave.** Cada marcalibro propio tiene un identificador de 64 bits aleatorios (`mp-` y 16 caracteres hexadecimales); no hay listados ni búsqueda. Quien tiene el enlace puede verlo, descargarlo y borrarlo. Las páginas llevan `noindex`.
- **Se borran solos** a los `MARCALIBROS_RETENTION_DAYS` días (ver más abajo).
- **Límite de intentos** por IP. La IP no se guarda: se usa un HMAC con una clave propia del servidor (`storage/intentos/.clave`, permisos 600).
- **Tope de almacenamiento**, para que nadie llene el disco.
- **Archivos fuera de `public/`.** Las imágenes se sirven con PHP, con `X-Content-Type-Options: nosniff` y `X-Robots-Tag`. Los SVG del catálogo llevan una CSP con `sandbox`.
- **Solo se procesa lo que PHP recibió como subida** (`is_uploaded_file`). El módulo `php/` valida el contenido real de la imagen (no su extensión), la vuelve a codificar —así desaparecen EXIF y cualquier dato añadido al final del archivo— y limita bytes, píxeles y memoria.
- **Se rechazan las peticiones `POST` de otros sitios** (`Sec-Fetch-Site: cross-site`). No hay token CSRF porque no hay sesión que proteger: lo peor que haría una página ajena es crear un marcalibro que nadie verá y gastar intentos del límite de esa persona. Los navegadores actuales ya lo impiden con esa cabecera.
- **CSP estricta**: `default-src 'none'`, solo scripts y estilos de este mismo sitio, sin código ni estilos en línea (una prueba lo vigila), sin `connect-src`, sin marcos.
- Los identificadores y nombres de archivo que llegan en la dirección se comprueban contra el manifiesto o contra el formato exacto antes de tocar el disco.
- **El texto del marcalibro pasa por `defamatory-content-review`** (`src/LabelPolicy.php`), en español e inglés. El marcalibro se comparte por enlace desde nuestro dominio: un «Fulano es un estafador» sería una difamación publicada por la plataforma (ver `PRIVACIDAD-Y-HONRA.md` en la raíz del repositorio). Como aquí nadie revisa a mano, no se aceptan insultos, acusaciones, datos sensibles ni datos personales (RUT, teléfono, correo, dirección): la persona ve un aviso y puede escribir otro texto.

Lo que **no** hace: HTTPS ni `Strict-Transport-Security` (eso lo ponen el servidor web o el proxy), ni comprobar que la imagen subida sea apropiada: una foto con texto escrito no pasa por el filtro.

## Despliegue

Dirige todo lo que no sea un archivo de `public/assets/` a `public/index.php`. Ejemplo para nginx con php-fpm:

```nginx
server {
    server_name marcalibros.example.com;
    root /srv/marcalibros/app/public;
    client_max_body_size 6m;

    location /assets/ { try_files $uri =404; expires 1h; }
    location /        { include fastcgi_params;
                        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
                        fastcgi_pass unix:/run/php/php-fpm.sock; }
    add_header Strict-Transport-Security "max-age=31536000" always;   # solo con HTTPS ya configurado
}
```

En `php.ini` (o el pool de php-fpm): `upload_max_filesize = 5M` y `post_max_size = 6M`. La carpeta de almacenamiento debe pertenecer al usuario de php-fpm y no ser accesible desde la web. El proceso solo necesita leer `catalog/`, `images/` y `svg/`.

Detrás de un proxy, activa `MARCALIBROS_TRUST_FORWARDED_FOR=1` solo si el proxy sobrescribe `X-Forwarded-For`; si no, todos los visitantes parecerán tener la IP del proxy y compartirán el mismo límite.

### Limpieza diaria

```cron
17 3 * * *  cd /srv/marcalibros/app && php bin/limpiar.php
```

Borra los marcalibros vencidos, los archivos sueltos de subidas interrumpidas y los registros de intentos antiguos. Usa las mismas variables de entorno que la aplicación.

## Pruebas

```bash
composer install
./vendor/bin/phpunit
phpstan analyse        # nivel 6
```

Las pruebas armen la aplicación con el catálogo real y una carpeta temporal; no necesitan servidor. Incluyen: rutas y cabeceras, las 1000 SVG, subida y recorte (con los valores de zoom y posición aplicados de verdad), rechazos, límite de intentos, tope de almacenamiento, borrado, escape de HTML, y que ninguna plantilla emita estilos o scripts en línea.

## Estructura

```
app/
├── public/          index.php y assets/ (app.css, crear.js, catalogo.js, mio.js)
├── src/             Application, Request, Response, View, Config, BookmarkStore, RateLimiter
├── templates/       layout, catalogo, detalle, crear, mio, error
├── bin/limpiar.php  borra lo vencido
├── storage/         marcalibros/ e intentos/ (se crean solas; fuera de git)
└── tests/
```

`crear.js` hace el mismo cálculo de recorte que el servidor (`php/src/ImageNormalizer.php`), así que la franja que se ve al encuadrar es la que se guarda.

## Pendiente

- Sin modo oscuro.
- El almacenamiento es disco local: sirve para un solo servidor.
