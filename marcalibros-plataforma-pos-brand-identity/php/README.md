# Marcalibros propios (módulo PHP)

Permite que cada usuario cree su marcalibro eligiendo una imagen de la biblioteca de la marca o subiendo la suya. Valida el archivo, le quita los metadatos, lo recorta a proporción 7:1 y guarda dos versiones JPEG: 1400×200 y 2800×400 (pantallas de alta densidad).

No depende de ningún framework: recibe la ruta de un archivo y devuelve un objeto con los nombres de los archivos guardados.

## Requisitos

- PHP 8.1 o superior.
- Extensiones `gd` (con soporte WebP), `fileinfo` y `mbstring`.
- Extensión `exif` recomendada: sin ella, las fotos tomadas con el celular pueden quedar de lado.

## Instalación y pruebas

```bash
cd marcalibros-plataforma-pos-brand-identity/php
composer install
./vendor/bin/phpunit
phpstan analyse        # nivel 6, igual que defamatory-content-review
```

## Uso

```php
use Marcalibros\BookmarkMaker;
use Marcalibros\BookmarkRejectedException;
use Marcalibros\CropSettings;
use Marcalibros\LandscapeLibrary;

$maker = new BookmarkMaker(
    storageDirectory: '/var/www/almacen/marcalibros',
    library: new LandscapeLibrary(__DIR__ . '/../images'),
);

try {
    // Una imagen subida. $crop viene del recorte que el usuario ajustó en pantalla.
    $bookmark = $maker->fromUpload(
        $_FILES['imagen']['tmp_name'],
        CropSettings::fromArray($_POST),      // claves: zoom, x, y
        $_POST['texto'] ?? null,               // texto opcional
    );

    // O una imagen de la biblioteca de la marca.
    $bookmark = $maker->fromLibraryImage('paisaje-costa-1920x1080.jpg');
} catch (BookmarkRejectedException $e) {
    http_response_code(422);
    echo $e->userMessage();                    // texto en español para la persona
}

// En la base de datos se guarda solo esto:
$bookmark->toArray();   // id, source, label, files ('1x' y '2x'), width, height
```

Los marcalibros predefinidos se consultan con `Catalog`:

```php
$catalog = new \Marcalibros\Catalog(__DIR__ . '/../catalog/manifest.json');
$catalog->get('mb-gotico-01');           // lanza BookmarkRejectedException si no existe
$catalog->categories();                  // 20 categorías: movimientos literarios y tipos de texto
$catalog->byCategory('realismo-magico');
$catalog->byFamily('tipo de texto');
```

## Qué se valida y por qué

| Medida | Qué evita |
|--------|-----------|
| El tipo se decide por el contenido (`finfo` y cabecera de imagen), nunca por la extensión ni por el tipo que declara el navegador | Que alguien suba código PHP o HTML con nombre de imagen |
| Solo JPG, PNG y WebP. SVG y GIF se rechazan | SVG puede llevar scripts |
| Tamaño máximo del archivo (5 MB) y de píxeles (16 millones) | Archivos pequeños que al abrirlos ocupan gigas de memoria |
| Se comprueba la memoria disponible antes de decodificar | Que una imagen grande tire el proceso |
| La imagen se decodifica y se vuelve a codificar | Metadatos (EXIF, GPS, comentarios) y datos escondidos al final del archivo no pasan a la salida |
| Se aplica la orientación del EXIF antes de descartarlo | Fotos de celular de lado |
| Las zonas transparentes se rellenan con el color papel de la marca | Fondos negros en el JPEG |
| El nombre de archivo lo genera el módulo (`mp-` más 16 caracteres al azar) | Nombres controlados por el usuario y choques entre archivos |
| Escritura en archivo temporal y renombrado | Que se sirva un archivo a medio escribir |
| El identificador del catálogo y el nombre de la biblioteca solo se aceptan si están en la lista | Rutas como `../../etc/passwd` |
| El texto se limpia de caracteres de control, ceros de ancho y marcas de inversión, y se recorta a 40 caracteres | Texto invisible o que desordena la pantalla |

## Qué debe hacer quien lo integre

- Comprobar `$_FILES['imagen']['error']` y `is_uploaded_file()` antes de llamar al módulo.
- Ajustar `upload_max_filesize` y `post_max_size` de `php.ini` a un valor coherente con `Limits::maxBytes`.
- Guardar los marcalibros en una carpeta que no ejecute PHP, o servirlos siempre con tipo `image/jpeg`.
- Escapar el texto (`label`) al imprimirlo en HTML. El módulo lo limpia, pero no lo escapa.
- Limitar cuántos marcalibros puede crear cada usuario y cada cuánto: eso queda fuera del módulo.

## Límites configurables

`new Limits(maxBytes: …, maxPixels: …, minCropWidth: …, jpegQuality: …, memoryBudget: …)` y se pasa como tercer argumento de `BookmarkMaker`. El ancho mínimo de la zona recortada es de 700 px: con menos, la imagen se vería borrosa al ampliarla.
