# Imágenes PNG o JPG

Las tiendas de ebooks suelen pedir la cubierta como imagen. `herramientas/a-png.sh` convierte los SVG de una carpeta con lo primero que encuentre instalado (`rsvg-convert`, Inkscape o `resvg`):

```bash
php bin/generar.php libro.json --trazos --salida=salida
bash herramientas/a-png.sh salida/libro 2560 png     # o 1200 jpg: el segundo número es el alto en píxeles
```

Parte de SVG hechos con `--trazos` (esos programas ignoran las fuentes incrustadas). Cada imagen incluye el sangrado de 3 mm; si la tienda pide la portada recortada, hay que quitarlo (con `convert` de ImageMagick: `-crop` de 3 mm a cada lado).
